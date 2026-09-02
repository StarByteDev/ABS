<?php

namespace App\Services;

use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\User;

class PulseUsageService
{
    public function today(User $user): array
    {
        $plan = $user->pulsePlan();
        $scanLimit = max(0, (int) ($plan?->scanner_runs_per_day ?? 0));
        $signalLimit = max(0, (int) ($plan?->signals_per_day ?? 0));

        $scansUsed = PulseScannerRun::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
        $signalsUsed = PulseSignal::query()
            ->where('user_id', $user->id)
            ->whereBetween('generated_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        return [
            'plan' => $plan?->name,
            'scans' => $this->quota($scansUsed, $scanLimit),
            'signals' => $this->quota($signalsUsed, $signalLimit),
            'resets_at' => now()->endOfDay()->toIso8601String(),
            'resets_in' => now()->diffForHumans(now()->endOfDay(), true),
        ];
    }

    private function quota(int $used, int $limit): array
    {
        $unlimited = $limit === 0;
        return [
            'used' => $used,
            'limit' => $unlimited ? null : $limit,
            'remaining' => $unlimited ? null : max(0, $limit - $used),
            'unlimited' => $unlimited,
            'percent_used' => $unlimited ? 0 : min(100, ($used / max(1, $limit)) * 100),
        ];
    }
}
