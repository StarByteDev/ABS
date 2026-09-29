<?php

namespace App\Services\Push;

use App\Models\MobileDevice;
use App\Models\PulseAlert;
use App\Models\PulseSystemSetting;
use App\Models\PushNotificationLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Central Pulse push pipeline.
 *
 * Producers enqueue rows in push_notification_logs (unique dedupe key, so an
 * event is pushed at most once); dispatch() sends them through FCM from the
 * scheduler, so web requests never wait on Firebase.
 *
 * PUBLIC topics carry only public market content. Private/account types
 * (security, package expiry, trades, risk, signals, support...) are ONLY ever
 * sent to the owning user's registered device tokens.
 */
class PushNotificationService
{
    public const TOPICS = [
        'abs_market_alerts', 'abs_breaking_news', 'abs_pulse_signals',
        'abs_entry_watch', 'abs_macro_alerts', 'abs_daily_brief',
    ];

    /** Types that may be broadcast on a public topic. Everything else is private. */
    public const PUBLIC_TYPES = [
        'market_move', 'breaking_news', 'macro_event', 'daily_brief', 'entry_watch',
    ];

    private const MAX_ATTEMPTS = 3;

    public function __construct(private readonly FcmClient $fcm) {}

    public function enabled(): bool
    {
        return (bool) PulseSystemSetting::value('notifications_enabled', true)
            && Schema::hasTable('push_notification_logs');
    }

    /** Queue a PUBLIC topic push. Returns false when disabled or already queued. */
    public function queueTopic(string $topic, string $type, string $title, string $body, array $data = [], ?string $dedupeKey = null, ?string $subject = null): bool
    {
        if (! in_array($topic, self::TOPICS, true)) {
            throw new InvalidArgumentException("Unknown push topic [$topic].");
        }
        if (! in_array($type, self::PUBLIC_TYPES, true)) {
            // Hard guarantee: private/account notifications never use topics.
            throw new InvalidArgumentException("Push type [$type] is private and cannot use a public topic.");
        }
        return $this->insert('topic', $topic, $type, $title, $body, $data, $dedupeKey, $subject);
    }

    /**
     * Queue a PRIVATE push to one user's registered devices.
     * $preference is the user's notification-preference key (null = always,
     * used for security notices).
     */
    public function queueUser(User|int $user, string $type, string $title, string $body, array $data = [], ?string $dedupeKey = null, ?string $preference = null): bool
    {
        $user = $user instanceof User ? $user : User::query()->with('pulseSettings')->find($user);
        if (! $user) return false;
        if ($preference !== null && ! $this->userWants($user, $preference)) return false;
        return $this->insert('user', (string) $user->id, $type, $title, $body, $data, $dedupeKey, null);
    }

    /**
     * Account-security notice to the user's own devices. Always private and
     * sent regardless of preferences (while pushes are enabled).
     */
    public function queueSecurity(User $user, string $title, string $body, string $event): bool
    {
        return $this->queueUser($user, 'security', $title, $body, ['event' => $event], 'security:'.$event.':'.$user->id.':'.now()->format('YmdHis'));
    }

    /** Mirrors a genuine in-app PulseAlert to the owner's devices. */
    public function queueFromAlert(PulseAlert $alert): bool
    {
        if (! $alert->user_id) return false;
        return $this->queueAlert((int) $alert->user_id, (string) $alert->type, (string) $alert->title, (string) $alert->message, array_filter([
            'alert_id' => $alert->id,
            'signal_id' => data_get($alert->data, 'signal_id'),
            'symbol' => data_get($alert->data, 'symbol'),
            'trade_id' => data_get($alert->data, 'trade_id'),
        ], static fn ($value) => $value !== null && $value !== ''), 'alert:'.$alert->id);
    }

    /**
     * Private device push for an in-app alert type (also used by Admin
     * broadcasts, which bulk-insert alerts without model events).
     */
    public function queueAlert(int $userId, string $alertType, string $title, string $message, array $data, string $dedupeKey): bool
    {
        [$type, $preference] = match ($alertType) {
            'signal' => ['qualified_signal', 'signals'],
            'trade' => ['trade', 'trades'],
            'risk' => ['risk', 'risk'],
            'market' => ['market_notice', 'market'],
            'plan' => ['package_expiry', 'plan_expiry'],
            'support' => ['support', 'system'],
            'private_investor' => ['private_investor', 'system'],
            default => ['system', 'system'],
        };
        if ($type === 'qualified_signal' && ! (bool) PulseSystemSetting::value('qualified_signal_notifications_enabled', true)) {
            return false;
        }
        return $this->queueUser($userId, $type, $title, $message, $data, $dedupeKey, $preference);
    }

    /**
     * Sends queued pushes. Returns counts for the scheduler log.
     *
     * @return array{sent:int,skipped:int,failed:int}
     */
    public function dispatch(int $limit = 100): array
    {
        $counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        if (! Schema::hasTable('push_notification_logs')) return $counts;

        $rows = PushNotificationLog::query()->where('status', 'queued')->orderBy('id')->limit($limit)->get();
        if ($rows->isEmpty()) return $counts;

        if (! $this->fcm->isConfigured()) {
            foreach ($rows as $row) {
                $row->update(['status' => 'skipped', 'error' => 'FCM is not configured (FIREBASE_CREDENTIALS).']);
                $counts['skipped']++;
            }
            return $counts;
        }

        foreach ($rows as $row) {
            $outcome = $row->channel === 'topic' ? $this->sendTopic($row) : $this->sendUser($row);
            $counts[$outcome]++;
        }
        return $counts;
    }

    private function sendTopic(PushNotificationLog $row): string
    {
        $result = $this->fcm->send(['topic' => $row->target], $row->title, $row->body, (array) $row->data);
        return $this->finish($row, $result['ok'] ? 1 : 0, $result['ok'] ? null : $result['error'], ! $result['ok']);
    }

    private function sendUser(PushNotificationLog $row): string
    {
        $devices = MobileDevice::query()
            ->where('user_id', (int) $row->target)
            ->where('is_active', true)
            ->whereNotNull('push_token')
            ->where('push_token', '!=', '')
            ->get();
        if ($devices->isEmpty()) {
            $row->update(['status' => 'skipped', 'attempts' => $row->attempts + 1, 'error' => 'No registered device push token.']);
            return 'skipped';
        }

        $delivered = 0;
        $errors = [];
        foreach ($devices as $device) {
            $result = $this->fcm->send(['token' => $device->push_token], $row->title, $row->body, (array) $row->data);
            if ($result['ok']) {
                $delivered++;
                continue;
            }
            if ($result['unregistered']) {
                // Stale install: stop targeting this token.
                $device->forceFill(['push_token' => null])->save();
                continue;
            }
            $errors[] = $result['error'];
        }

        if ($delivered === 0 && $errors === []) {
            $row->update(['status' => 'skipped', 'attempts' => $row->attempts + 1, 'error' => 'All device tokens were unregistered.']);
            return 'skipped';
        }
        return $this->finish($row, $delivered, $errors === [] ? null : implode(' | ', array_unique($errors)), $delivered === 0);
    }

    private function finish(PushNotificationLog $row, int $delivered, ?string $error, bool $failed): string
    {
        $attempts = $row->attempts + 1;
        if ($failed) {
            $final = $attempts >= self::MAX_ATTEMPTS;
            $row->update(['status' => $final ? 'failed' : 'queued', 'attempts' => $attempts, 'error' => $error]);
            return 'failed';
        }
        $row->update(['status' => 'sent', 'attempts' => $attempts, 'delivered' => $delivered, 'error' => $error, 'sent_at' => now()]);
        return 'sent';
    }

    private function insert(string $channel, string $target, string $type, string $title, string $body, array $data, ?string $dedupeKey, ?string $subject): bool
    {
        if (! $this->enabled()) return false;
        if ($dedupeKey !== null && PushNotificationLog::query()->where('dedupe_key', $dedupeKey)->exists()) return false;
        try {
            PushNotificationLog::query()->create([
                'dedupe_key' => $dedupeKey,
                'channel' => $channel,
                'target' => $target,
                'type' => $type,
                'subject' => $subject,
                'title' => mb_substr($title, 0, 191),
                'body' => mb_substr($body, 0, 1000),
                'data' => ['type' => $type] + $data,
                'status' => 'queued',
            ]);
            return true;
        } catch (QueryException) {
            // Unique dedupe key raced with another scheduler run: already queued.
            return false;
        }
    }

    private function userWants(User $user, string $preference): bool
    {
        $preferences = (array) ($user->pulseSettings?->notification_preferences ?? []);
        $defaults = ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true];
        return (bool) ($preferences[$preference] ?? $defaults[$preference] ?? true);
    }

    /** Never let a push side effect break the primary action that produced it. */
    public static function safely(callable $callback): void
    {
        try {
            $callback(app(self::class));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
