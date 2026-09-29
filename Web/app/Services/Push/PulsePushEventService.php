<?php

namespace App\Services\Push;

use App\Models\EconomicEvent;
use App\Models\NewsArticle;
use App\Models\PulseSystemSetting;
use App\Models\PushNotificationLog;
use App\Models\UserServiceAccess;
use App\Services\PulseMarketDataService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Server-side producers for Pulse push notifications. Each producer reads an
 * existing ABS data source (central price feed, economic calendar, ABS News,
 * Pulse package access) and queues pushes with dedupe keys. No new external
 * data source is introduced and nothing is fabricated: a producer with no
 * fresh/genuine data simply queues nothing.
 */
class PulsePushEventService
{
    /** symbol => [enabled setting, percent setting, default percent, label] */
    public const FAST_MOVE_SYMBOLS = [
        'BTCUSDT' => ['btc_fast_move_enabled', 'btc_fast_move_percent', 3.0, 'BTC'],
        'ETHUSDT' => ['eth_fast_move_enabled', 'eth_fast_move_percent', 4.0, 'ETH'],
    ];

    private const SAMPLE_KEY = 'abs:push:fast-move:samples:';
    private const SAMPLE_RETENTION_MINUTES = 30;

    public function __construct(
        private readonly PushNotificationService $push,
        private readonly PulseMarketDataService $market,
    ) {}

    /** @return array<string,int> queued count per producer */
    public function runAll(): array
    {
        if (! $this->push->enabled()) return [];
        return [
            'market_move' => $this->marketFastMoves(),
            'macro_event' => $this->macroAlerts(),
            'breaking_news' => $this->breakingNews(),
            'daily_brief' => $this->dailyBrief(),
            'package_expiry' => $this->packageExpiry(),
        ];
    }

    /**
     * BTC/ETH rapid moves: absolute % change between the latest central
     * price and any sample inside the window (default 5 min). Per-symbol
     * cooldown (default 30 min) and a global hourly cap (default 3).
     */
    public function marketFastMoves(): int
    {
        if (! (bool) PulseSystemSetting::value('market_fast_move_enabled', true)) return 0;

        $window = max(1, (int) PulseSystemSetting::value('market_fast_move_window_minutes', 5));
        $cooldown = max(1, (int) PulseSystemSetting::value('market_fast_move_cooldown_minutes', 30));
        $hourlyCap = max(0, (int) PulseSystemSetting::value('max_market_alerts_per_hour', 3));
        $defaultPercent = (float) PulseSystemSetting::value('market_fast_move_default_percent', 3.0);

        // Only fresh central prices (written every minute by the existing
        // market sync); stale data never produces an alert.
        $prices = $this->market->latestPrices(array_keys(self::FAST_MOVE_SYMBOLS), 180);
        $now = now();
        $queued = 0;

        foreach (self::FAST_MOVE_SYMBOLS as $symbol => [$enabledKey, $percentKey, $symbolDefault, $label]) {
            $row = $prices->get($symbol);
            if (! $row || ! is_numeric($row->price) || (float) $row->price <= 0) continue;
            $price = (float) $row->price;
            $observedAt = Carbon::parse($row->observed_at);

            $samples = $this->recordSample($symbol, $observedAt, $price);
            if (! (bool) PulseSystemSetting::value($enabledKey, true)) continue;

            $threshold = (float) PulseSystemSetting::value($percentKey, $symbolDefault);
            if ($threshold <= 0) $threshold = $defaultPercent > 0 ? $defaultPercent : $symbolDefault;

            $move = $this->largestMove($samples, $observedAt, $price, $window);
            if ($move === null || abs($move['percent']) < $threshold) continue;

            if ($this->recentMarketAlert($symbol, $now->copy()->subMinutes($cooldown))) continue;
            if (PushNotificationLog::query()->where('type', 'market_move')->where('created_at', '>=', $now->copy()->subHour())->count() >= $hourlyCap) {
                break;
            }

            $percent = round($move['percent'], 1);
            $signed = ($percent > 0 ? '+' : '').number_format($percent, 1).'%';
            $minutes = $move['minutes'];
            $title = "$label moved $signed in $minutes ".($minutes === 1 ? 'minute' : 'minutes');
            $body = "$symbol is now $".number_format($price, $price >= 100 ? 2 : 4).'. Tap to open the Pulse market view.';
            $bucket = intdiv($now->getTimestamp(), $cooldown * 60);

            if ($this->push->queueTopic('abs_market_alerts', 'market_move', $title, $body, [
                'symbol' => $symbol,
                'change_percent' => $percent,
                'window_minutes' => $minutes,
                'price' => $price,
            ], "market_move:$symbol:$bucket", $symbol)) {
                $queued++;
            }
        }
        return $queued;
    }

    /** High-impact economic events, once each, $lead minutes (default 30) ahead. */
    public function macroAlerts(): int
    {
        if (! (bool) PulseSystemSetting::value('macro_alerts_enabled', true)) return 0;
        if (! Schema::hasTable('economic_events')) return 0;
        $lead = max(1, (int) PulseSystemSetting::value('macro_alert_lead_minutes', 30));
        $now = now();

        $queued = 0;
        EconomicEvent::query()
            ->whereRaw('LOWER(impact) = ?', ['high'])
            ->where('event_at', '>', $now)
            ->where('event_at', '<=', $now->copy()->addMinutes($lead))
            ->orderBy('event_at')
            ->limit(10)
            ->get()
            ->each(function (EconomicEvent $event) use ($now, &$queued): void {
                $minutes = max(1, (int) ceil($now->diffInSeconds($event->event_at) / 60));
                $who = trim(($event->currency ?: $event->country ?: '').' '.$event->title);
                $body = $event->crypto_impact_summary
                    ? Str::limit(strip_tags((string) $event->crypto_impact_summary), 150)
                    : 'High-impact release at '.$event->event_at->format('H:i').' UTC. Expect crypto volatility.';
                if ($this->push->queueTopic('abs_macro_alerts', 'macro_event', "In $minutes min: $who", $body, [
                    'event_id' => $event->id,
                    'event_at' => $event->event_at->toIso8601String(),
                ], 'macro_event:'.$event->id, (string) $event->id)) {
                    $queued++;
                }
            });
        return $queued;
    }

    /** Featured/breaking ABS News articles, once each, when published. */
    public function breakingNews(): int
    {
        if (! (bool) PulseSystemSetting::value('breaking_news_enabled', true)) return 0;
        if (! Schema::hasTable('news_articles')) return 0;
        $now = now();

        $queued = 0;
        NewsArticle::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [$now->copy()->subMinutes(30), $now])
            ->where(function ($query): void {
                $query->where('is_featured', true)->orWhereRaw('LOWER(category) LIKE ?', ['%breaking%']);
            })
            ->orderBy('published_at')
            ->limit(5)
            ->get()
            ->each(function (NewsArticle $article) use (&$queued): void {
                $body = Str::limit(trim(strip_tags((string) ($article->excerpt ?: $article->body))), 150) ?: 'Tap to read on Pulse.';
                if ($this->push->queueTopic('abs_breaking_news', 'breaking_news', Str::limit('Breaking: '.$article->title, 120), $body, [
                    'article_id' => $article->id,
                    'slug' => (string) $article->slug,
                ], 'breaking_news:'.$article->id, (string) $article->id)) {
                    $queued++;
                }
            });
        return $queued;
    }

    /** One daily brief after 08:15 server time, built from live central prices only. */
    public function dailyBrief(): int
    {
        if (! (bool) PulseSystemSetting::value('daily_brief_enabled', true)) return 0;
        $now = now();
        $start = $now->copy()->setTime(8, 15);
        if ($now->lt($start) || $now->gt($start->copy()->addHours(4))) return 0;

        $prices = $this->market->latestPrices(['BTCUSDT', 'ETHUSDT'], 300);
        if ($prices->count() < 2) return 0; // no live data, no brief

        $parts = collect(['BTCUSDT' => 'BTC', 'ETHUSDT' => 'ETH'])->map(function (string $label, string $symbol) use ($prices): string {
            $row = $prices->get($symbol);
            $change = is_numeric($row->change_percent_24h) ? (float) $row->change_percent_24h : null;
            return $label.' $'.number_format((float) $row->price, 0).($change === null ? '' : ' ('.($change >= 0 ? '+' : '').number_format($change, 1).'%)');
        })->implode(' · ');

        return $this->push->queueTopic('abs_daily_brief', 'daily_brief', 'Your Pulse daily market brief', $parts.'. Tap for today’s market overview.', [
            'date' => $now->toDateString(),
        ], 'daily_brief:'.$now->toDateString()) ? 1 : 0;
    }

    /** Private device reminders mirroring the Admin expiry schedule (default 7/3/1/0 days). */
    public function packageExpiry(): int
    {
        if (! (bool) PulseSystemSetting::value('package_expiry_notifications_enabled', true)) return 0;
        if (! Schema::hasTable('user_service_access')) return 0;
        $now = now();
        if ($now->hour < 8 || $now->hour >= 21) return 0; // no overnight account pushes
        if (! Cache::add('abs:push:package-expiry-pass', true, now()->addMinutes(30))) return 0;

        $configured = PulseSystemSetting::value('expiry_reminder_days', [7, 3, 1, 0]);
        if (! is_array($configured)) $configured = explode(',', (string) $configured);
        $days = collect($configured)->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 0 && $d <= 90)->unique()->values()->all() ?: [7, 3, 1, 0];
        $today = $now->copy()->startOfDay();

        $queued = 0;
        UserServiceAccess::query()
            ->with(['user.pulseSettings', 'plan'])
            ->where('service', 'pulse')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$today->copy()->subDay(), $today->copy()->addDays(max($days) + 1)->endOfDay()])
            ->chunkById(200, function ($accesses) use ($days, $today, &$queued): void {
                foreach ($accesses as $access) {
                    $user = $access->user;
                    if (! $user || $user->status !== 'active') continue;
                    $daysLeft = (int) $today->diffInDays($access->ends_at->copy()->startOfDay(), false);
                    $plan = $access->plan?->name ?? 'Pulse';

                    if ($daysLeft >= 0 && in_array($daysLeft, $days, true)) {
                        $title = $daysLeft === 0 ? "Your $plan access ends today" : "Your $plan access ends in $daysLeft ".($daysLeft === 1 ? 'day' : 'days');
                        $key = "package_expiry:{$access->id}:{$daysLeft}";
                    } elseif ($daysLeft < 0) {
                        $title = "Your $plan access has ended";
                        $key = "package_expired:{$access->id}";
                    } else {
                        continue;
                    }
                    if ($this->push->queueUser($user, 'package_expiry', $title, 'Renew in Pulse to keep signals and scanner access.', [
                        'access_id' => $access->id,
                        'days_left' => $daysLeft,
                    ], $key, 'plan_expiry')) {
                        $queued++;
                    }
                }
            });
        return $queued;
    }

    /** @return list<array{t:int,p:float}> samples within retention, oldest first */
    private function recordSample(string $symbol, Carbon $observedAt, float $price): array
    {
        $key = self::SAMPLE_KEY.$symbol;
        $samples = (array) Cache::get($key, []);
        $t = $observedAt->getTimestamp();
        $last = end($samples);
        if (! is_array($last) || (int) $last['t'] !== $t) {
            $samples[] = ['t' => $t, 'p' => $price];
        }
        $cutoff = $t - self::SAMPLE_RETENTION_MINUTES * 60;
        $samples = array_values(array_filter($samples, static fn ($s) => is_array($s) && (int) $s['t'] >= $cutoff));
        Cache::put($key, $samples, now()->addMinutes(self::SAMPLE_RETENTION_MINUTES + 5));
        return $samples;
    }

    /** @return array{percent:float,minutes:int}|null */
    private function largestMove(array $samples, Carbon $observedAt, float $price, int $windowMinutes): ?array
    {
        $now = $observedAt->getTimestamp();
        $best = null;
        foreach ($samples as $sample) {
            $age = $now - (int) $sample['t'];
            if ($age <= 0 || $age > $windowMinutes * 60 || (float) $sample['p'] <= 0) continue;
            $percent = ($price - (float) $sample['p']) / (float) $sample['p'] * 100;
            if ($best === null || abs($percent) > abs($best['percent'])) {
                $best = ['percent' => $percent, 'minutes' => max(1, (int) round($age / 60))];
            }
        }
        return $best;
    }

    private function recentMarketAlert(string $symbol, Carbon $since): bool
    {
        return PushNotificationLog::query()
            ->where('type', 'market_move')
            ->where('subject', $symbol)
            ->where('created_at', '>=', $since)
            ->exists();
    }
}
