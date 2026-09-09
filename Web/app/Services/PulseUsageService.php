<?php

namespace App\Services;

use App\Models\User;

class PulseUsageService
{
    /**
     * V15.1 compatibility summary. Daily scan/signal quotas and the former
     * wallet economy are not part of the active commerce model.
     */
    public function today(User $user): array
    {
        $plan = $user->pulsePlan();
        return [
            'plan' => $plan?->name,
            'commerce_model' => 'direct_usdt_admin_verification',
            'commerce_label' => 'Direct USDT · Admin verified',
            'best_signal_included' => true,
            'per_signal_charge' => 0,
            'can_unlock_best_signal' => $user->hasPulseAccess(),
            'quota_model' => 'active_package_access',
        ];
    }
}
