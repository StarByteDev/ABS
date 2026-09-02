<?php

namespace App\Services;

use App\Models\PulsePlan;
use App\Models\PulseSystemSetting;
use App\Models\User;
use App\Models\UserServiceAccess;
use RuntimeException;

class PulseAccessService
{
    /** @var array<int, UserServiceAccess|null> */
    private array $resolvedAccess = [];

    public function access(User $user): ?UserServiceAccess
    {
        if (array_key_exists($user->id, $this->resolvedAccess)) {
            return $this->resolvedAccess[$user->id];
        }

        return $this->resolvedAccess[$user->id] = $user->pulseAccess()->with('plan')->first();
    }

    public function plan(User $user): ?PulsePlan
    {
        return $this->access($user)?->plan;
    }

    public function assertActive(User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $access = $this->access($user);
        if (! $access?->isActive()) {
            throw new RuntimeException('Pulse access is not active for this ABS account.');
        }
    }

    public function allows(User $user, string $permission, bool $default = true): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $access = $this->access($user);
        if (! $access?->isActive() || ! $access->plan) {
            return false;
        }

        // The plan is the maximum feature envelope. User-level permissions can
        // further restrict a plan but can never grant a feature excluded by it.
        $planAllows = $access->plan->allows($permission, $default);
        if (! $planAllows) {
            return false;
        }

        $permissions = is_array($access->permissions) ? $access->permissions : [];
        if (array_key_exists($permission, $permissions)) {
            return (bool) $permissions[$permission];
        }

        return true;
    }

    public function capabilityMatrix(User $user): array
    {
        $matrix = [];
        foreach (PulsePlan::CAPABILITIES as $key => $label) {
            $matrix[$key] = [
                'label' => $label,
                'enabled' => $this->allows($user, $key, false),
            ];
        }

        return $matrix;
    }

    public function systemEnabled(string $key, bool $default = true): bool
    {
        return (bool) PulseSystemSetting::value($key, $default);
    }

    public function assertEnvironment(User $user, string $environment): void
    {
        $environment = strtolower($environment);
        if (! in_array($environment, ['testnet', 'live'], true)) {
            throw new RuntimeException('Unsupported Pulse trading environment.');
        }

        if ($environment === 'testnet') {
            if (! $this->systemEnabled('testnet_trading_enabled', true) || ! $this->allows($user, 'testnet_trading', false)) {
                throw new RuntimeException('Practice trading is not available for this Pulse plan or account.');
            }
            return;
        }

        if (! config('pulse.allow_live_trading', false)
            || ! $this->systemEnabled('live_trading_enabled', false)
            || ! $this->allows($user, 'live_trading', false)) {
            throw new RuntimeException('Live trading is not currently available for this Pulse plan, account or installation.');
        }
    }
}
