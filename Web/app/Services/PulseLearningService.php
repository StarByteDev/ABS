<?php

namespace App\Services;

use App\Models\PulseSignalDailyMetric;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategyDailyMetric;
use App\Models\PulseStrategyLearningState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PulseLearningService
{
    public function reliabilityFor(array $strategies, string $timeframe, string $direction, ?string $marketRegime = null): array
    {
        if (! Schema::hasTable('pulse_strategy_learning_states')) {
            return ['score' => 50.0, 'evidence' => [], 'source' => 'neutral_prior'];
        }
        $scores = [];
        $evidence = [];
        foreach ($strategies as $strategy) {
            if (! is_array($strategy) || isset($strategy['_meta'])) continue;
            $slug = (string) ($strategy['slug'] ?? '');
            if ($slug === '') continue;
            $bias = strtoupper((string) ($strategy['bias'] ?? 'NEUTRAL'));
            if ((float) ($strategy['points'] ?? 0) <= 0 || ! in_array($bias, [strtoupper($direction), 'NEUTRAL'], true)) continue;
            $version = (string) ($strategy['version'] ?? '1.0');
            $state = $this->bestState($slug, $version, strtolower($timeframe), strtoupper($direction), $marketRegime);
            if ($state) {
                $scores[] = (float) $state->reliability_score;
                $evidence[] = ['strategy' => $slug, 'version' => $version, 'samples' => $state->sample_size, 'level' => $state->evidence_level, 'scope' => $state->market_regime];
            }
        }

        $score = $scores === [] ? 50.0 : array_sum($scores) / count($scores);
        return ['score' => round($score, 2), 'evidence' => $evidence, 'source' => $scores === [] ? 'neutral_prior' : 'learning_state'];
    }

    public function rebuildDate(Carbon|string $date): void
    {
        foreach (['pulse_signal_validations','pulse_signal_daily_metrics','pulse_strategy_daily_metrics','pulse_strategy_learning_states'] as $table) {
            if (! Schema::hasTable($table)) return;
        }
        $date = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();
        $from = $date->copy()->startOfDay(); $to = $date->copy()->endOfDay();
        $validations = PulseSignalValidation::query()->whereNotNull('resolved_at')->whereBetween('resolved_at', [$from, $to])->get();

        $userGroups = $validations->groupBy(fn ($v) => ($v->user_id ?: 0).'|'.$v->timeframe.'|'.$v->direction);
        foreach ($userGroups as $key => $group) {
            [$userId, $tf, $dir] = explode('|', $key, 3);
            $this->upsertSignalDaily($date, (int) $userId ?: null, $tf, $dir, $group);
        }

        // Platform-level reporting is also deduplicated by frozen market setup so
        // one signal seen by several users is counted once in system quality metrics.
        $deduped = $validations->unique(fn ($v) => $v->signal_fingerprint ?: 'signal-'.$v->signal_id)->values();
        $globalGroups = $deduped->groupBy(fn ($v) => $v->timeframe.'|'.$v->direction);
        foreach ($globalGroups as $key => $group) {
            [$tf, $dir] = explode('|', $key, 2);
            $this->upsertSignalDaily($date, null, $tf, $dir, $group);
        }

        // Learning must not overweight the same market setup because several users scanned it.
        $strategyBuckets = [];
        foreach ($deduped as $validation) {
            $regime = $validation->market_regime ?: 'ALL';
            foreach ((array) $validation->strategy_snapshot as $strategy) {
                if (! is_array($strategy) || isset($strategy['_meta'])) continue;
                $slug = (string) ($strategy['slug'] ?? ''); if ($slug === '') continue;
                $bias = strtoupper((string) ($strategy['bias'] ?? 'NEUTRAL'));
                if ((float) ($strategy['points'] ?? 0) <= 0 || ! in_array($bias, [strtoupper((string) $validation->direction), 'NEUTRAL'], true)) continue;
                $version = (string) ($strategy['version'] ?? $validation->strategy_version ?? '1.0');
                foreach (array_values(array_unique(['ALL', $regime])) as $scope) {
                    $bucketKey = implode('|', [$slug, $version, $validation->timeframe, $validation->direction, $scope]);
                    $strategyBuckets[$bucketKey] ??= collect();
                    $strategyBuckets[$bucketKey]->push($validation);
                }
            }
        }
        foreach ($strategyBuckets as $key => $group) {
            [$slug,$version,$tf,$dir,$regime] = explode('|', $key, 5);
            $this->upsertStrategyDaily($date, $slug, $version, $tf, $dir, $regime, $group);
        }

        $this->refreshLearningStates();
    }

    public function refreshLearningStates(): void
    {
        if (! Schema::hasTable('pulse_strategy_daily_metrics') || ! Schema::hasTable('pulse_strategy_learning_states')) return;
        $groups = PulseStrategyDailyMetric::query()->select('strategy_slug','strategy_version','timeframe','direction','market_regime')->distinct()->get();
        foreach ($groups as $group) {
            $rows = PulseStrategyDailyMetric::query()
                ->where('strategy_slug', $group->strategy_slug)->where('strategy_version', $group->strategy_version)
                ->where('timeframe', $group->timeframe)->where('direction', $group->direction)->where('market_regime', $group->market_regime)
                ->orderBy('metric_date')->get();
            $totalResolved = (int) $rows->sum('sample_count'); $wins = (int) $rows->sum('wins'); $losses = (int) $rows->sum('losses'); $ambiguous = (int) $rows->sum('ambiguous');
            $samples = max(0, $wins + $losses + $ambiguous);
            $decisive = max(0, $wins + $losses);
            $priorSamples = max(1, (int) config('pulse.learning.prior_samples', 20));
            $bayesWin = ($wins + ($priorSamples * 0.5)) / max(1, $decisive + $priorSamples);
            $ambiguityRate = $samples > 0 ? $ambiguous / $samples : 0;
            $reliability = max(0, min(100, ($bayesWin * 100) * (1 - min(0.25, $ambiguityRate * 0.5))));

            $weightedNumerator = 0.0; $weightedDenominator = 0.0;
            foreach ($rows as $row) {
                $age = max(0, Carbon::parse($row->metric_date)->diffInDays(now()));
                $weight = pow((float) config('pulse.learning.daily_decay', 0.97), $age);
                $rowDecisive = (int) $row->wins + (int) $row->losses;
                if ($rowDecisive <= 0) continue;
                $weightedNumerator += (((int) $row->wins / $rowDecisive) * 100) * $rowDecisive * $weight;
                $weightedDenominator += $rowDecisive * $weight;
            }
            $recency = $weightedDenominator > 0 ? $weightedNumerator / $weightedDenominator : 50.0;
            $level = $samples >= (int) config('pulse.learning.established_samples', 50) ? 'established'
                : ($samples >= (int) config('pulse.learning.minimum_samples', 20) ? 'developing' : 'insufficient');

            PulseStrategyLearningState::query()->updateOrCreate([
                'strategy_slug'=>$group->strategy_slug,'strategy_version'=>$group->strategy_version,'timeframe'=>$group->timeframe,
                'direction'=>$group->direction,'market_regime'=>$group->market_regime,
            ], [
                'sample_size'=>$samples,'win_rate'=>$decisive > 0 ? $wins/$decisive : null,'ambiguous_rate'=>$samples > 0 ? $ambiguous/$samples : null,
                'reliability_score'=>round(($reliability * 0.65) + ($recency * 0.35),2),'recency_weighted_score'=>round($recency,2),
                'evidence_level'=>$level,'meta'=>['wins'=>$wins,'losses'=>$losses,'ambiguous'=>$ambiguous,'total_resolved_signals'=>$totalResolved,'prior_samples'=>$priorSamples], 'calculated_at'=>now(),
            ]);
        }
    }

    private function bestState(string $slug, string $version, string $timeframe, string $direction, ?string $regime): ?PulseStrategyLearningState
    {
        $contextMin = (int) config('pulse.learning.context_minimum_samples', 40);
        if ($regime) {
            $exact = PulseStrategyLearningState::query()->where(compact('timeframe','direction'))
                ->where('strategy_slug',$slug)->where('strategy_version',$version)->where('market_regime',$regime)->first();
            if ($exact && $exact->sample_size >= $contextMin) return $exact;
        }
        $base = PulseStrategyLearningState::query()->where('strategy_slug',$slug)->where('strategy_version',$version)
            ->where('timeframe',$timeframe)->where('direction',$direction)->where('market_regime','ALL')->first();
        if ($base) return $base;
        return PulseStrategyLearningState::query()->where('strategy_slug',$slug)->where('strategy_version',$version)
            ->where('timeframe',$timeframe)->where('market_regime','ALL')->orderByDesc('sample_size')->first();
    }

    private function upsertSignalDaily(Carbon $date, ?int $userId, string $tf, string $dir, Collection $group): void
    {
        $entries = $group->whereNotNull('entry_hit_at')->count();
        PulseSignalDailyMetric::query()->updateOrCreate(['metric_date'=>$date->toDateString(),'user_id'=>$userId,'timeframe'=>$tf,'direction'=>$dir], [
            'signals'=>$group->count(),'entries'=>$entries,'wins'=>$group->where('outcome','tp')->count(),'losses'=>$group->where('outcome','sl')->count(),
            'ambiguous'=>$group->where('outcome','ambiguous')->count(),'expired_no_entry'=>$group->where('outcome','expired_no_entry')->count(),
            'expired_after_entry'=>$group->where('outcome','expired_after_entry')->count(),'avg_mfe_r'=>$group->whereNotNull('mfe_r')->avg('mfe_r'),
            'avg_mae_r'=>$group->whereNotNull('mae_r')->avg('mae_r'),'avg_duration_seconds'=>(int) round((float) $group->whereNotNull('duration_seconds')->avg('duration_seconds')),
        ]);
    }

    private function upsertStrategyDaily(Carbon $date, string $slug, string $version, string $tf, string $dir, string $regime, Collection $group): void
    {
        PulseStrategyDailyMetric::query()->updateOrCreate([
            'metric_date'=>$date->toDateString(),'strategy_slug'=>$slug,'strategy_version'=>$version,'timeframe'=>$tf,'direction'=>$dir,'market_regime'=>$regime,
        ], [
            'sample_count'=>$group->count(),'entries'=>$group->whereNotNull('entry_hit_at')->count(),'wins'=>$group->where('outcome','tp')->count(),'losses'=>$group->where('outcome','sl')->count(),
            'ambiguous'=>$group->where('outcome','ambiguous')->count(),'expired_no_entry'=>$group->where('outcome','expired_no_entry')->count(),
            'avg_mfe_r'=>$group->whereNotNull('mfe_r')->avg('mfe_r'),'avg_mae_r'=>$group->whereNotNull('mae_r')->avg('mae_r'),
            'avg_duration_seconds'=>(int) round((float) $group->whereNotNull('duration_seconds')->avg('duration_seconds')),
        ]);
    }
}
