<?php

namespace App\Services;

use App\Models\PulseUserSetting;
use App\Models\User;

class PulseSignalThresholdService
{
    public function resolve(?User $user, ?PulseUserSetting $settings = null): array
    {
        $profile = $settings?->minimum_signal_score;
        if ($profile !== null && is_numeric($profile)) {
            return ['score' => $this->clamp((float) $profile), 'source' => 'profile'];
        }

        $package = $user?->pulsePlan()?->minimum_signal_score;
        if ($package !== null && is_numeric($package)) {
            return ['score' => $this->clamp((float) $package), 'source' => 'package'];
        }

        return [
            'score' => $this->clamp((float) config('pulse.scanner.signal_threshold', 65)),
            'source' => 'system',
        ];
    }

    public function score(?User $user, ?PulseUserSetting $settings = null): float
    {
        return (float) $this->resolve($user, $settings)['score'];
    }

    public function packageDefault(?User $user): float
    {
        $package = $user?->pulsePlan()?->minimum_signal_score;
        return $this->clamp(is_numeric($package) ? (float) $package : (float) config('pulse.scanner.signal_threshold', 65));
    }

    private function clamp(float $score): float
    {
        return max(0, min(100, $score));
    }
}
