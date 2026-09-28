<?php

namespace App\Services;

use App\Models\PulseSystemSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PulseStrategyCycleService
{
    public function __construct(
        private readonly PulseMarketDataService $market,
        private readonly PulseStrategyResearchService $research,
        private readonly PulseSignalValidationService $validation,
        private readonly PulseRuntimeCadenceService $cadence,
    ) {}

    /**
     * Run one complete research/paper-validation cycle without enabling or placing
     * any live exchange order. This is the Admin's deterministic local test path.
     */
    public function run(bool $force = true): array
    {
        if (! $force) {
            if (! $this->cadence->scheduledEnabled()) {
                return ['status' => 'disabled', 'message' => 'Automatic Strategy Lab cycles are disabled in Manual Only mode.'];
            }
            if (! $this->cadence->marketDataDue()) {
                return ['status' => 'not_due', 'message' => 'The next Strategy Lab cycle is not due yet.'];
            }
        }

        $lock = Cache::lock('pulse:strategy-lab-full-cycle', 180);
        if (! $lock->get()) {
            return ['status' => 'locked', 'message' => 'Another Pulse Strategy Lab cycle is already running.'];
        }

        $started = now();
        $stages = [];
        try {
            $this->write('pulse_strategy_cycle_last_status', 'running');
            $this->write('pulse_strategy_cycle_last_started_at', $started->toIso8601String());

            $marketRun = $this->market->syncCentral();
            $stages['market'] = [
                'status' => 'completed',
                'run_id' => (int) $marketRun->id,
                'prices_updated' => (int) $marketRun->prices_updated,
                'candle_symbols_updated' => (int) $marketRun->candle_symbols_updated,
                'validation_symbols_updated' => (int) $marketRun->validation_symbols_updated,
            ];

            // Reconcile signals that existed before this cycle using the newly synced market data.
            // New signals generated below begin paper-entry observation on the next cycle; this avoids
            // treating pre-signal price action from the same market update as a paper execution.
            $validation = $this->validation->process(500);
            $this->cadence->markValidationRun();
            $stages['validation'] = [
                'status' => 'completed',
                'processed' => (int) ($validation['processed'] ?? 0),
                'resolved' => (int) ($validation['resolved'] ?? 0),
            ];

            $scan = $this->research->runIfDue($force);
            $stages['scan'] = $scan;
            if (($scan['status'] ?? '') === 'failed') {
                throw new \RuntimeException((string) ($scan['message'] ?? 'Strategy scan failed.'));
            }

            $finished = now();
            $result = [
                'status' => 'completed',
                'started_at' => $started->toIso8601String(),
                'completed_at' => $finished->toIso8601String(),
                'duration_seconds' => $started->diffInSeconds($finished),
                'mode' => $this->cadence->mode(),
                'stages' => $stages,
                'message' => 'Pulse cycle completed: prices refreshed, existing paper positions checked, and new qualified signals queued for entry validation on the next market cycle.',
            ];
            $this->write('pulse_strategy_cycle_last_status', 'completed');
            $this->write('pulse_strategy_cycle_last_completed_at', $finished->toIso8601String());
            $this->write('pulse_strategy_cycle_last_summary', json_encode($result, JSON_UNESCAPED_SLASHES), 'json');
            return $result;
        } catch (Throwable $e) {
            $result = [
                'status' => 'failed',
                'started_at' => $started->toIso8601String(),
                'completed_at' => now()->toIso8601String(),
                'duration_seconds' => $started->diffInSeconds(now()),
                'mode' => $this->cadence->mode(),
                'stages' => $stages,
                'message' => $e->getMessage(),
            ];
            $this->write('pulse_strategy_cycle_last_status', 'failed');
            $this->write('pulse_strategy_cycle_last_completed_at', now()->toIso8601String());
            $this->write('pulse_strategy_cycle_last_summary', json_encode($result, JSON_UNESCAPED_SLASHES), 'json');
            return $result;
        } finally {
            try { $lock->release(); } catch (Throwable) {}
        }
    }

    public function lastCycle(): array
    {
        $summary = PulseSystemSetting::value('pulse_strategy_cycle_last_summary');
        if (is_array($summary)) return $summary;
        if (is_string($summary) && trim($summary) !== '') {
            $decoded = json_decode($summary, true);
            if (is_array($decoded)) return $decoded;
        }
        return [
            'status' => (string) PulseSystemSetting::value('pulse_strategy_cycle_last_status', 'never'),
            'started_at' => PulseSystemSetting::value('pulse_strategy_cycle_last_started_at'),
            'completed_at' => PulseSystemSetting::value('pulse_strategy_cycle_last_completed_at'),
            'stages' => [],
        ];
    }

    private function write(string $key, string $value, string $type = 'string'): void
    {
        PulseSystemSetting::updateOrCreate(['key' => $key], [
            'value' => $value,
            'type' => $type,
            'group' => 'strategy_lab',
            'description' => 'Pulse Strategy Lab cycle state.',
        ]);
    }
}
