<?php

namespace App\Services;

use App\Models\PulseUserSetting;
use RuntimeException;

class PulsePairSelectionLockService
{
    public function status(PulseUserSetting $settings): array
    {
        $until = $settings->pair_selection_locked_until;
        $locked = $until !== null && $until->isFuture();

        return [
            'locked' => $locked,
            'saved_at' => $settings->pair_selection_saved_at?->toIso8601String(),
            'locked_until' => $until?->toIso8601String(),
            'remaining_minutes' => $locked ? max(1, now()->diffInMinutes($until)) : 0,
            'remaining_human' => $locked ? now()->diffForHumans($until, true) : null,
            'lock_hours' => max(1, (int) config('pulse.settings.pair_change_lock_hours', 50)),
        ];
    }

    public function guardAndAttributes(PulseUserSetting $settings, array $selected, array $eligibleCurrent = []): array
    {
        $current = $eligibleCurrent !== [] ? $eligibleCurrent : (array) ($settings->selected_pairs ?? []);
        $current = $this->normalize($current);
        $next = $this->normalize($selected);
        $changed = $current !== $next;

        if (! $changed) {
            return [];
        }

        $status = $this->status($settings);
        if ($status['locked']) {
            throw new RuntimeException('Trading markets are locked after a saved change. You can change them again in '.$status['remaining_human'].'.');
        }

        $hours = $status['lock_hours'];
        return [
            'pair_selection_saved_at' => now(),
            'pair_selection_locked_until' => now()->addHours($hours),
        ];
    }

    private function normalize(array $symbols): array
    {
        $symbols = array_values(array_unique(array_filter(array_map(
            fn ($symbol) => strtoupper(trim((string) $symbol)),
            $symbols,
        ))));
        sort($symbols);
        return $symbols;
    }
}
