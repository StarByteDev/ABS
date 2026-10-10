<?php

namespace App\Services;

use App\Models\EconomicEvent;
use App\Models\MobileDevice;
use App\Models\PulseMacroDeviceDelivery;
use App\Models\PulseMacroReleaseAlert;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PulseMacroReleaseService
{
    public function __construct(private readonly PulseFirebasePushService $push) {}

    public function queueIfNewActual(EconomicEvent $event): void
    {
        if (! $event->wasChanged('actual_value') || filled($event->getRawOriginal('actual_value'))) return;
        if (! filled($event->actual_value) || ! in_array($event->impact, ['high', 'medium'], true)) return;
        if (! $event->event_at || $event->event_at->lt(now()->subHours(3)) || $event->event_at->gt(now()->addMinutes(30))) return;
        // During first deploy before artisan migrate, do not break normal calendar syncing.
        if (! Schema::hasTable('pulse_macro_release_alerts')) return;
        PulseMacroReleaseAlert::firstOrCreate(['economic_event_id' => $event->id], [
            'actual_hash' => hash('sha256', trim((string) $event->actual_value)), 'status' => 'pending',
        ]);
    }

    public function queueIfFreshAlreadyReleased(EconomicEvent $event): void
    {
        // Some providers first discover an event with Actual already populated.
        // Only allow it during a continuously running calendar (not initial
        // bootstrap, production deploy, or historical catch-up).
        if (! filled($event->actual_value) || ! in_array($event->impact, ['high', 'medium'], true)) return;
        if (! $event->event_at || $event->event_at->lt(now()->subMinutes(20)) || $event->event_at->gt(now()->addMinutes(5))) return;
        $last = SiteSetting::query()->where('key', 'economic_calendar_last_sync_at')->value('value');
        if (! $last) return;
        try {
            if (now()->diffInMinutes(\Carbon\CarbonImmutable::parse($last), true) > 60) return;
        } catch (Throwable) { return; }
        if (! Schema::hasTable('pulse_macro_release_alerts')) return;
        PulseMacroReleaseAlert::firstOrCreate(['economic_event_id' => $event->id], [
            'actual_hash' => hash('sha256', trim((string) $event->actual_value)), 'status' => 'pending',
        ]);
    }

    public function deliver(int $maximum = 60): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'invalid' => 0, 'pending' => 0, 'configuration' => $this->push->configured()];
        if (! $stats['configuration']) return $stats;
        if (! Schema::hasTable('pulse_macro_release_alerts') || ! Schema::hasTable('pulse_macro_device_deliveries')) return $stats;
        $alerts = PulseMacroReleaseAlert::query()->with('event')
            ->where('status', 'pending')->oldest('id')->limit(20)->get();
        foreach ($alerts as $alert) {
            $event = $alert->event;
            if (! $event || $alert->created_at->lt(now()->subHours(3))) {
                $alert->update(['status' => 'expired']);
                continue;
            }
            $body = $this->notificationBody($event);
            $exhausted = true;
            MobileDevice::query()->with(['user.pulseSettings'])
                ->where('is_active', true)->whereIn('platform', ['ios', 'android'])
                ->whereNotNull('push_token')->where('push_token', '<>', '')
                ->orderBy('id')->chunkById(50, function ($devices) use (&$stats, $maximum, &$exhausted, $event, $alert, $body) {
                    foreach ($devices as $device) {
                        $user = $device->user;
                        if (! $user || $user->status !== 'active') continue;
                        $prefs = $user->pulseSettings?->notification_preferences ?? [];
                        if (array_key_exists('market', $prefs) && ! $prefs['market']) continue;

                        $delivery = PulseMacroDeviceDelivery::firstOrCreate([
                            'alert_id' => $alert->id, 'mobile_device_id' => $device->id,
                        ], ['status' => 'pending', 'attempts' => 0]);
                        if (in_array($delivery->status, ['sent', 'invalid'], true) || $delivery->attempts >= 3) continue;
                        if ($stats['sent'] + $stats['failed'] + $stats['invalid'] >= $maximum) {
                            $exhausted = false;
                            return false;
                        }
                        try {
                            $answer = $this->push->send(
                                (string) $device->push_token, 'Pulse · '.$event->title, $body,
                                ['type' => 'macro_release', 'event_id' => (string) $event->id,
                                    'screen' => 'economic_calendar',
                                    'url' => url('/news?calendar=all#macro-event-'.$event->id),
                                    'impact' => (string) $event->impact]
                            );
                            $delivery->attempts++;
                            if ($answer['ok']) {
                                $delivery->status = 'sent'; $delivery->sent_at = now(); $stats['sent']++;
                            } elseif ($answer['invalid_token']) {
                                $delivery->status = 'invalid';
                                $device->update(['is_active' => false]); $stats['invalid']++;
                            } else {
                                $delivery->status = 'failed'; $stats['failed']++;
                            }
                            $delivery->error_code = $answer['ok'] ? null : $answer['error'];
                        } catch (Throwable $e) {
                            $delivery->attempts++; $delivery->status = 'failed';
                            $delivery->error_code = 'TRANSPORT_ERROR'; $stats['failed']++;
                            Log::warning('Pulse macro push send failed', ['event_id' => $event->id, 'message' => $e->getMessage()]);
                        }
                        $delivery->save();
                    }
                });
            if ($exhausted && ! PulseMacroDeviceDelivery::query()->where('alert_id', $alert->id)
                ->whereIn('status', ['failed', 'pending'])->where('attempts', '<', 3)->exists()) {
                $alert->update(['status' => 'completed', 'sent_at' => now()]);
            }
            else break;
        }
        $stats['pending'] = PulseMacroReleaseAlert::where('status', 'pending')->count();
        return $stats;
    }

    public function notificationBody(EconomicEvent $event): string
    {
        $direction = match ((string) $event->crypto_impact) {
            'supportive' => 'Possible crypto support',
            'pressure' => 'Possible crypto pressure',
            'volatile' => 'Higher crypto volatility risk',
            default => 'Mixed crypto impact',
        };
        $values = 'Actual '.$event->actual_value;
        if (filled($event->forecast_value)) $values .= ' vs forecast '.$event->forecast_value;
        else $values .= ' (no consensus published)';
        $context = trim((string) $event->crypto_impact_summary);
        if ($context === '') $context = 'Monitor BTC, USD and Treasury yields for confirmation.';
        $message = $direction.'. '.$values.'. Pulse analysis: '.$context;
        return mb_strlen($message) > 280 ? mb_substr($message, 0, 277).'…' : $message;
    }

}
