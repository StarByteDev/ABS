<?php

namespace App\Services;

use App\Models\PulseMarketDataRun;
use App\Models\PulseScannerRun;
use App\Models\PulseSystemSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PulseStrategyResearchService
{
    public function __construct(
        private readonly PulseScannerService $scanner,
        private readonly PulseRuntimeCadenceService $cadence,
    ) {}

    public function settings(): array
    {
        $profile = $this->cadence->profile();
        return [
            'enabled' => (bool) PulseSystemSetting::value('strategy_research_enabled', true),
            'configured_seconds' => $profile['configured_seconds'],
            'effective_seconds' => $profile['effective_seconds'],
            'profile' => $profile['profile'],
            'hostgator_shared' => $profile['hostgator_shared'],
            'subminute_available' => $profile['subminute_available'],
            'last_run_at' => PulseSystemSetting::value('strategy_research_last_run_at'),
            'last_status' => (string) PulseSystemSetting::value('strategy_research_last_status', 'never'),
            'last_run_id' => (int) PulseSystemSetting::value('strategy_research_last_run_id', 0),
            'last_signal_id' => (int) PulseSystemSetting::value('strategy_research_last_signal_id', 0),
            'last_error' => (string) PulseSystemSetting::value('strategy_research_last_error', ''),
        ];
    }

    public function due(): bool
    {
        $settings = $this->settings();
        if (! $settings['enabled'] || ! $this->cadence->scheduledEnabled()) return false;
        return $this->cadence->dueFrom($settings['last_run_at'] ?: null, (int) $settings['effective_seconds']);
    }

    /** @return array{ran:bool,status:string,run_id:int|null,signal_id:int|null,message:string} */
    public function runIfDue(bool $force = false): array
    {
        $settings = $this->settings();
        if (! $settings['enabled'] && ! $force) {
            return ['ran'=>false,'status'=>'disabled','run_id'=>null,'signal_id'=>null,'message'=>'Strategy research engine is disabled.'];
        }
        if (! $force && ! $this->due()) {
            return ['ran'=>false,'status'=>'not_due','run_id'=>null,'signal_id'=>null,'message'=>'Strategy research engine is not due yet.'];
        }

        $ttl = max(90, (int) $settings['effective_seconds'] + 45);
        $lock = Cache::lock('pulse:strategy-research-engine', $ttl);
        if (! $lock->get()) {
            return ['ran'=>false,'status'=>'locked','run_id'=>null,'signal_id'=>null,'message'=>'Another strategy research cycle is already running.'];
        }

        try {
            $this->write('strategy_research_last_status', 'running', 'string', 'research_engine', 'Last strategy research engine status.');
            $this->write('strategy_research_last_run_at', now()->toIso8601String(), 'string', 'research_engine', 'Last strategy research engine start time.');
            $run = $this->scanner->run(null, null, 'all');
            $run->loadMissing(['bestSignal','signals']);
            // The scanner now handles duplicate suppression for every qualified
            // system candidate before persistence. Do not run a second, best-only
            // dedup pass here because one research scan can legitimately persist
            // several independent signals.
            $run->refresh()->loadMissing(['bestSignal','signals']);

            $summary = array_values((array) ($run->summary ?? []));
            $marketRun = PulseMarketDataRun::query()->latest('id')->first();
            $summary[] = [
                'type' => 'engine_meta',
                'source' => 'strategy_research_engine',
                'cycle_mode' => $this->cadence->mode(),
                'configured_interval_seconds' => (int) $settings['configured_seconds'],
                'effective_interval_seconds' => (int) $settings['effective_seconds'],
                'market_run_id' => $marketRun?->id,
                'market_run_status' => $marketRun?->status,
                'market_run_completed_at' => $marketRun?->completed_at?->toIso8601String(),
                'market_prices_updated' => (int) ($marketRun?->prices_updated ?? 0),
                'deduplication_scope' => 'per_qualified_signal',
                'qualified_candidates' => (int) data_get(collect((array) $run->summary)->first(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'qualified_signal_set'), 'qualified_count', $run->best_signal_id ? 1 : 0),
                'new_signals_created' => (int) $run->signals_created,
            ];
            $run->forceFill(['summary' => $summary])->save();

            $signalId = $run->bestSignal?->id;
            $this->write('strategy_research_last_status', 'completed', 'string', 'research_engine', 'Last strategy research engine status.');
            $this->write('strategy_research_last_run_id', (string) $run->id, 'integer', 'research_engine', 'Last strategy research scanner-run id.');
            $this->write('strategy_research_last_signal_id', (string) ($signalId ?: 0), 'integer', 'research_engine', 'Last strategy research signal id.');
            $this->write('strategy_research_last_error', '', 'string', 'research_engine', 'Last strategy research error.');

            $signalSet = collect((array) $run->summary)->first(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'qualified_signal_set');
            $qualifiedCount = (int) data_get($signalSet, 'qualified_count', $signalId ? 1 : 0);
            $newCount = (int) data_get($signalSet, 'new_signals_created', $run->signals_created);
            $reusedCount = (int) data_get($signalSet, 'reused_open_setups', 0);

            return [
                'ran'=>true,
                'status'=>'completed',
                'run_id'=>(int) $run->id,
                'signal_id'=>$signalId ? (int) $signalId : null,
                'qualified_signals'=>$qualifiedCount,
                'new_signals'=>$newCount,
                'reused_signals'=>$reusedCount,
                'message'=>$qualifiedCount > 0
                    ? "Research scan completed with {$qualifiedCount} qualified setup".($qualifiedCount === 1 ? '' : 's')."; {$newCount} new and {$reusedCount} already-open setup".($reusedCount === 1 ? '' : 's').'.'
                    : 'Research scan completed; no setup reached the qualification threshold.',
            ];
        } catch (Throwable $e) {
            $this->write('strategy_research_last_status', 'failed', 'string', 'research_engine', 'Last strategy research engine status.');
            $this->write('strategy_research_last_error', mb_substr($e->getMessage(), 0, 1000), 'text', 'research_engine', 'Last strategy research error.');
            return ['ran'=>true,'status'=>'failed','run_id'=>null,'signal_id'=>null,'message'=>$e->getMessage()];
        } finally {
            try { $lock->release(); } catch (Throwable) {}
        }
    }

    private function write(string $key, string $value, string $type, string $group, string $description): void
    {
        PulseSystemSetting::updateOrCreate(['key'=>$key], compact('value','type','group','description'));
    }
}
