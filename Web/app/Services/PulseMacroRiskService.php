<?php

namespace App\Services;

use App\Models\EconomicEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class PulseMacroRiskService
{
    public function snapshot(CarbonInterface $at): array
    {
        $enabled = (bool) config('pulse.optimizer.macro_event_gate_enabled', true);
        $before = max(0, (int) config('pulse.optimizer.macro_event_minutes_before', 45));
        $after = max(0, (int) config('pulse.optimizer.macro_event_minutes_after', 30));

        if (! $enabled || ! Schema::hasTable('economic_events')) {
            return [
                'enabled' => $enabled,
                'blocked' => false,
                'events' => [],
                'window_before_minutes' => $before,
                'window_after_minutes' => $after,
                'source' => $enabled ? 'calendar_unavailable' : 'disabled',
            ];
        }

        $from = $at->copy()->subMinutes($after);
        $to = $at->copy()->addMinutes($before);
        $events = EconomicEvent::query()
            ->where('impact', 'high')
            ->whereBetween('event_at', [$from, $to])
            ->where(function ($query) {
                $query->where('is_crypto_relevant', true)
                    ->orWhere('currency', 'USD')
                    ->orWhereRaw('LOWER(COALESCE(country, ?)) LIKE ?', ['', '%united states%']);
            })
            ->orderBy('event_at')
            ->limit(5)
            ->get(['title','country','currency','impact','event_at','crypto_impact','is_crypto_relevant']);

        return [
            'enabled' => true,
            'blocked' => $events->isNotEmpty(),
            'events' => $events->map(fn ($event) => [
                'title' => (string) $event->title,
                'currency' => $event->currency,
                'country' => $event->country,
                'impact' => (string) $event->impact,
                'event_at' => $event->event_at?->toIso8601String(),
                'crypto_impact' => $event->crypto_impact,
            ])->values()->all(),
            'window_before_minutes' => $before,
            'window_after_minutes' => $after,
            'source' => 'economic_events',
        ];
    }
}
