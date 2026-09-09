<?php

namespace App\Services;

use App\Models\BinanceConnection;
use App\Models\PulseAutomationRun;
use App\Models\PulseSignal;
use App\Models\PulseUserSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PulseAutomationService
{
    public function __construct(
        private readonly PulseScannerService $scanner,
        private readonly PulseTradeService $trades,
        private readonly PulseAccessService $access,
        private readonly PulseAuditService $audit,
    ) {}

    public function runFor(User $user): PulseAutomationRun
    {
        $this->access->assertActive($user);
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], [
            'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false,
            'emergency_stop' => false, 'minimum_signal_score' => 70, 'selected_pairs' => ['BTCUSDT', 'ETHUSDT'],
        ]);

        if (! config('pulse.allow_automatic_trading', false)
            || ! $this->access->systemEnabled('automatic_trading_enabled', false)
            || ! $this->access->allows($user, 'auto_trading', false)
            || ! $settings->auto_trade_enabled
            || $settings->execution_mode !== 'automatic') {
            throw new RuntimeException('Pulse automatic trading is not enabled for this installation, plan or account.');
        }
        if ($settings->emergency_stop) {
            throw new RuntimeException('Pulse emergency stop is active.');
        }

        $this->access->assertEnvironment($user, $settings->environment);
        $connection = BinanceConnection::query()->where('user_id', $user->id)
            ->where('environment', $settings->environment)->where('is_active', true)->first();
        if (! $connection || ! (bool) data_get($connection->permissions, 'can_trade', false)) {
            throw new RuntimeException('A tested Binance trading connection is required for automatic trading.');
        }

        $lock = Cache::lock("pulse:auto:{$user->id}", (int) config('pulse.automation.lock_seconds', 240));
        if (! $lock->get()) {
            throw new RuntimeException('An automatic Pulse run is already active for this user.');
        }

        $run = PulseAutomationRun::create([
            'user_id' => $user->id, 'status' => 'running', 'environment' => $settings->environment,
            'started_at' => now(), 'summary' => [],
        ]);

        try {
            // V15 automation consumes only the Admin-controlled Best Signal returned
            // by the one-click scanner. User pair/threshold fields are legacy storage
            // and must never influence automatic signal selection.
            $scannerRun = $this->scanner->run($user, null, 'all');
            $scannerRun->loadMissing('bestSignal');
            $signals = collect();
            if ($scannerRun->bestSignal && $scannerRun->bestSignal->isActionable()) {
                $signals = collect([$scannerRun->bestSignal]);
            }

            $created = 0;
            $errors = [];
            foreach ($signals as $signal) {
                try {
                    if ($signal->trades()->where('user_id', $user->id)->exists()) {
                        continue;
                    }
                    $this->trades->execute($user, $signal, [
                        'environment' => $settings->environment,
                        'order_type' => $settings->default_order_type,
                        'leverage' => $settings->default_leverage,
                        'position_side' => $settings->position_mode,
                    ], true);
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = ['signal_id' => $signal->id, 'message' => $e->getMessage()];
                }
            }

            $run->update([
                'status' => $errors === [] ? 'completed' : 'completed_with_errors',
                'signals_reviewed' => $signals->count(), 'trades_created' => $created,
                'summary' => ['scanner_run_id' => $scannerRun->id, 'errors' => $errors], 'completed_at' => now(),
            ]);
            $this->audit->record('automation.completed', $user, 'PulseAutomationRun', $run->id, $settings->environment, $run->summary ?: []);
            return $run->fresh();
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            $this->audit->record('automation.failed', $user, 'PulseAutomationRun', $run->id, $settings->environment, ['error' => $e->getMessage()]);
            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function runEligibleUsers(): array
    {
        $result = ['attempted' => 0, 'completed' => 0, 'failed' => 0, 'errors' => []];
        if (! config('pulse.allow_automatic_trading', false) || ! $this->access->systemEnabled('automatic_trading_enabled', false)) {
            return $result + ['disabled' => true];
        }

        User::query()->whereHas('pulseSettings', fn ($q) => $q->where('auto_trade_enabled', true)->where('execution_mode', 'automatic')->where('emergency_stop', false))
            ->whereHas('pulseAccess', fn ($q) => $q->where('service', 'pulse')->where('status', 'active'))
            ->chunkById(50, function ($users) use (&$result): void {
                foreach ($users as $user) {
                    $result['attempted']++;
                    try { $this->runFor($user); $result['completed']++; }
                    catch (\Throwable $e) { $result['failed']++; $result['errors'][] = ['user_id' => $user->id, 'message' => $e->getMessage()]; }
                }
            });

        return $result;
    }
}
