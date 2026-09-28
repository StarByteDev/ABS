<?php

namespace App\Services;

use App\Models\PulseMarketDataRun;
use App\Models\PulseSystemSetting;
use Illuminate\Support\Carbon;

class PulseRuntimeCadenceService
{
    public const ALLOWED_SECONDS = [5, 30, 60, 120, 300];
    public const ALLOWED_MODES = ['cron', 'internal', 'manual'];

    public function mode(): string
    {
        $mode = strtolower(trim((string) PulseSystemSetting::value('pulse_background_mode', 'cron')));
        return in_array($mode, self::ALLOWED_MODES, true) ? $mode : 'cron';
    }

    public function configuredSeconds(): int
    {
        $seconds = (int) PulseSystemSetting::value('pulse_background_interval_seconds', 60);
        return in_array($seconds, self::ALLOWED_SECONDS, true) ? $seconds : 60;
    }

    public function effectiveSeconds(): int
    {
        $configured = $this->configuredSeconds();

        // Production/shared hosting and explicit cron mode are never presented as
        // sub-minute execution. The configured interval is kept for a later VPS/local switch.
        if ((bool) config('pulse.scheduler.hostgator_shared', false) || $this->mode() === 'cron') {
            return max(60, $configured);
        }

        return $configured;
    }

    public function profile(): array
    {
        $mode = $this->mode();
        $hostgator = (bool) config('pulse.scheduler.hostgator_shared', false);
        $effective = $this->effectiveSeconds();

        return [
            'profile' => (string) config('pulse.scheduler.profile', 'standard'),
            'hostgator_shared' => $hostgator,
            'mode' => $mode,
            'mode_label' => match ($mode) {
                'internal' => 'ABS Internal Scheduler',
                'manual' => 'Manual Only',
                default => 'Production Cron',
            },
            'configured_seconds' => $this->configuredSeconds(),
            'effective_seconds' => $effective,
            'subminute_available' => ! $hostgator,
            'automatic_cycles' => $mode !== 'manual',
            'runner_required' => $mode === 'internal' ? 'php artisan schedule:work' : ($mode === 'cron' ? 'server cron / schedule:run' : 'none'),
        ];
    }

    public function scheduledEnabled(): bool
    {
        return $this->mode() !== 'manual';
    }

    public function marketDataDue(): bool
    {
        if (! $this->scheduledEnabled()) return false;
        $latest = PulseMarketDataRun::query()->latest('id')->first();
        $at = $latest?->completed_at ?: $latest?->started_at ?: $latest?->created_at;
        return $this->dueFrom($at);
    }

    public function validationDue(): bool
    {
        if (! $this->scheduledEnabled()) return false;
        $raw = PulseSystemSetting::value('pulse_validation_last_run_at');
        return $this->dueFrom($raw ? Carbon::parse((string) $raw) : null);
    }

    public function markValidationRun(): void
    {
        $this->put('pulse_validation_last_run_at', now()->toIso8601String(), 'string', 'runtime', 'Last background validation cycle.');
    }

    public function dueFrom(mixed $at, ?int $seconds = null): bool
    {
        if (! $at) return true;
        $timestamp = $at instanceof Carbon ? $at : Carbon::parse((string) $at);
        return $timestamp->lte(now()->subSeconds($seconds ?? $this->effectiveSeconds()));
    }

    private function put(string $key, string $value, string $type, string $group, string $description): void
    {
        PulseSystemSetting::updateOrCreate(['key' => $key], compact('value','type','group','description'));
    }
}
