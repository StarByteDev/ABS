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

    public function rebuildDate(Carbon|string $date, bool $refreshStates = true): void
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

        if ($refreshStates) {
            $this->refreshLearningStates();
        }
    }

    public function refreshLearningStates(): void
    {
        if (! Schema::hasTable('pulse_strategy_daily_metrics') || ! Schema::hasTable('pulse_strategy_learning_states')) return;
        $groups = PulseStrategyDailyMetric::query()->select('strategy_slug','strategy_version','timeframe','direction','market_regime')->distinct()->get();

        // Build milestone and R:R optimization evidence once per refresh. The optimizer
        // uses observed MFE/MAE in R units and never rewrites historical outcomes.
        $milestoneIndex = [];
        $optimizerIndex = [];
        $rrCandidates = array_values(array_unique(array_map('floatval', (array) config('pulse.optimizer.rr_candidates', [1.0,1.25,1.5,1.75,2.0,2.25,2.5,3.0]))));
        sort($rrCandidates);
        $lookbackDays = max(14, (int) config('pulse.optimizer.lookback_days', 90));
        $resolvedValidations = PulseSignalValidation::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subDays($lookbackDays))
            ->get();

        $resolvedValidations->each(function ($validation) use (&$milestoneIndex, &$optimizerIndex, $rrCandidates) {
            foreach ((array) $validation->strategy_snapshot as $strategy) {
                if (!is_array($strategy) || isset($strategy['_meta'])) continue;
                $slug=(string)($strategy['slug']??''); if($slug==='') continue;
                $version=(string)($strategy['version']??$validation->strategy_version??'1.0');
                $bias=strtoupper((string)($strategy['bias']??'NEUTRAL'));
                if((float)($strategy['points']??0)<=0 || !in_array($bias,[strtoupper((string)$validation->direction),'NEUTRAL'],true)) continue;

                $tf = strtolower((string)$validation->timeframe);
                $dir = strtoupper((string)$validation->direction);
                $regime = (string)($validation->market_regime ?: 'balanced');
                $milestoneKey=implode('|',[$slug,$version,$tf,$dir]);
                $milestoneIndex[$milestoneKey] ??= ['count'=>0,'tp1'=>0,'tp2'=>0,'tp3'=>0];
                $milestoneIndex[$milestoneKey]['count']++;
                $level=(int)$validation->highest_tp_level_hit;
                if($level>=1)$milestoneIndex[$milestoneKey]['tp1']++;
                if($level>=2)$milestoneIndex[$milestoneKey]['tp2']++;
                if($level>=3)$milestoneIndex[$milestoneKey]['tp3']++;

                if (!$validation->entry_hit_at || (string)$validation->outcome === 'ambiguous') continue;
                $mfeR = is_numeric($validation->mfe_r) ? max(0.0, (float)$validation->mfe_r) : 0.0;
                $maeR = is_numeric($validation->mae_r) ? max(0.0, (float)$validation->mae_r) : 0.0;
                foreach (array_values(array_unique(['ALL', $regime])) as $scope) {
                    $key=implode('|',[$slug,$version,$tf,$dir,$scope]);
                    foreach ($rrCandidates as $rr) {
                        $rrKey = number_format($rr, 2, '.', '');
                        $optimizerIndex[$key][$rrKey] ??= ['rr'=>$rr,'wins'=>0,'losses'=>0,'ambiguous'=>0,'unresolved'=>0];
                        // Conservative counterfactual: if a historical SL trade reached both
                        // the alternative target and 1R adverse excursion, minute OHLC cannot
                        // prove which came first, so do not count it as a synthetic win.
                        if ($mfeR >= $rr) {
                            if ((string)$validation->outcome === 'sl' && $maeR >= 1.0) $optimizerIndex[$key][$rrKey]['ambiguous']++;
                            else $optimizerIndex[$key][$rrKey]['wins']++;
                        } elseif ((string)$validation->outcome === 'sl' || $maeR >= 1.0) {
                            $optimizerIndex[$key][$rrKey]['losses']++;
                        } else {
                            $optimizerIndex[$key][$rrKey]['unresolved']++;
                        }
                    }
                }
            }
        });

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

            // Intermediate TP milestones provide a small positive evidence bonus. Final
            // TP/SL remains dominant; partial targets cannot make a poor strategy look strong.
            $milestoneKey = implode('|',[(string)$group->strategy_slug,(string)$group->strategy_version,strtolower((string)$group->timeframe),strtoupper((string)$group->direction)]);
            $milestones = $milestoneIndex[$milestoneKey] ?? ['count'=>0,'tp1'=>0,'tp2'=>0,'tp3'=>0];
            $tp1Hits = (int)$milestones['tp1'];
            $tp2Hits = (int)$milestones['tp2'];
            $tp3Hits = (int)$milestones['tp3'];
            $partialUniverse = max(1, (int)$milestones['count']);
            $partialBonus = min(12.0, (($tp1Hits / $partialUniverse) * 3.0) + (($tp2Hits / $partialUniverse) * 4.0) + (($tp3Hits / $partialUniverse) * 5.0));
            $reliability = max(0, min(100, (($bayesWin * 100) * (1 - min(0.25, $ambiguityRate * 0.5))) + $partialBonus));

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

            $optimizerKey = implode('|',[(string)$group->strategy_slug,(string)$group->strategy_version,strtolower((string)$group->timeframe),strtoupper((string)$group->direction),(string)$group->market_regime]);
            $rrOptimizer = $this->optimizerSummary($optimizerIndex[$optimizerKey] ?? [], $priorSamples);

            PulseStrategyLearningState::query()->updateOrCreate([
                'strategy_slug'=>$group->strategy_slug,'strategy_version'=>$group->strategy_version,'timeframe'=>$group->timeframe,
                'direction'=>$group->direction,'market_regime'=>$group->market_regime,
            ], [
                'sample_size'=>$samples,'win_rate'=>$decisive > 0 ? $wins/$decisive : null,'ambiguous_rate'=>$samples > 0 ? $ambiguous/$samples : null,
                'reliability_score'=>round(($reliability * 0.65) + ($recency * 0.35),2),'recency_weighted_score'=>round($recency,2),
                'evidence_level'=>$level,'meta'=>[
                    'wins'=>$wins,'losses'=>$losses,'ambiguous'=>$ambiguous,'tp1_hits'=>$tp1Hits,'tp2_hits'=>$tp2Hits,'tp3_hits'=>$tp3Hits,
                    'partial_tp_bonus'=>round($partialBonus,2),'total_resolved_signals'=>$totalResolved,'prior_samples'=>$priorSamples,
                    'rr_optimizer'=>$rrOptimizer,
                ], 'calculated_at'=>now(),
            ]);
        }
    }

    private function optimizerSummary(array $candidates, int $priorSamples): array
    {
        $minimum = max(10, (int) config('pulse.optimizer.minimum_samples', 30));
        $evaluated = [];
        foreach ($candidates as $row) {
            $wins = (int)($row['wins'] ?? 0); $losses = (int)($row['losses'] ?? 0); $rr = (float)($row['rr'] ?? 0);
            $resolved = $wins + $losses;
            $expectancy = $resolved > 0 ? (($wins * $rr) - $losses) / $resolved : null;
            $profitFactor = $losses > 0 ? ($wins * $rr) / $losses : ($wins > 0 ? 99.0 : null);
            $winRate = $resolved > 0 ? $wins / $resolved : null;
            $adjusted = $expectancy === null ? null : $expectancy * ($resolved / max(1, $resolved + $priorSamples));
            $evaluated[] = [
                'rr'=>round($rr,2),'samples'=>$resolved,'wins'=>$wins,'losses'=>$losses,'ambiguous'=>(int)($row['ambiguous']??0),'unresolved'=>(int)($row['unresolved']??0),
                'win_rate'=>$winRate===null?null:round($winRate,4),'expectancy_r'=>$expectancy===null?null:round($expectancy,4),
                'adjusted_expectancy_r'=>$adjusted===null?null:round($adjusted,4),'profit_factor'=>$profitFactor===null?null:round($profitFactor,4),
            ];
        }
        $eligible = array_values(array_filter($evaluated, fn($row)=>(int)$row['samples'] >= $minimum && $row['adjusted_expectancy_r'] !== null));
        usort($eligible, fn($a,$b)=>(float)$b['adjusted_expectancy_r'] <=> (float)$a['adjusted_expectancy_r']);
        $best = $eligible[0] ?? null;
        return [
            'method'=>'mfe_mae_counterfactual_v1','minimum_samples'=>$minimum,'candidates'=>$evaluated,
            'recommended_rr'=>$best['rr']??null,'recommended_samples'=>$best['samples']??0,
            'recommended_win_rate'=>$best['win_rate']??null,'recommended_expectancy_r'=>$best['adjusted_expectancy_r']??null,
            'recommended_profit_factor'=>$best['profit_factor']??null,
        ];
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
        $simulation = $this->simulationMetrics($group);
        PulseSignalDailyMetric::query()->updateOrCreate(['metric_date'=>$date->toDateString(),'user_id'=>$userId,'timeframe'=>$tf,'direction'=>$dir], [
            'signals'=>$group->count(),'entries'=>$entries,'wins'=>$group->where('outcome','tp')->count(),'losses'=>$group->where('outcome','sl')->count(),
            'ambiguous'=>$group->where('outcome','ambiguous')->count(),'expired_no_entry'=>$group->where('outcome','expired_no_entry')->count(),
            'expired_after_entry'=>$group->where('outcome','expired_after_entry')->count(),'avg_mfe_r'=>$group->whereNotNull('mfe_r')->avg('mfe_r'),
            'avg_mae_r'=>$group->whereNotNull('mae_r')->avg('mae_r'),'avg_duration_seconds'=>(int) round((float) $group->whereNotNull('duration_seconds')->avg('duration_seconds')),
            ...$simulation,
        ]);
    }

    private function upsertStrategyDaily(Carbon $date, string $slug, string $version, string $tf, string $dir, string $regime, Collection $group): void
    {
        $simulation = $this->simulationMetrics($group);
        PulseStrategyDailyMetric::query()->updateOrCreate([
            'metric_date'=>$date->toDateString(),'strategy_slug'=>$slug,'strategy_version'=>$version,'timeframe'=>$tf,'direction'=>$dir,'market_regime'=>$regime,
        ], [
            'sample_count'=>$group->count(),'entries'=>$group->whereNotNull('entry_hit_at')->count(),'wins'=>$group->where('outcome','tp')->count(),'losses'=>$group->where('outcome','sl')->count(),
            'ambiguous'=>$group->where('outcome','ambiguous')->count(),'expired_no_entry'=>$group->where('outcome','expired_no_entry')->count(),
            'avg_mfe_r'=>$group->whereNotNull('mfe_r')->avg('mfe_r'),'avg_mae_r'=>$group->whereNotNull('mae_r')->avg('mae_r'),
            'avg_duration_seconds'=>(int) round((float) $group->whereNotNull('duration_seconds')->avg('duration_seconds')),
            ...$simulation,
        ]);
    }

    /**
     * Equal-risk / equal-notional research model for resolved TP/SL outcomes.
     * It intentionally excludes ambiguous and unresolved/expired-after-entry records.
     * No leverage, fees, funding, slippage or compounding are assumed.
     */
    private function simulationMetrics(Collection $group): array
    {
        $trades = 0;
        $netR = 0.0;
        $grossProfitR = 0.0;
        $grossLossR = 0.0;
        $returnPct = 0.0;

        foreach ($group as $validation) {
            if (! in_array((string) $validation->outcome, ['tp','sl'], true) || ! $validation->entry_hit_at) continue;
            $entry = (float) $validation->entry_price;
            $stop = (float) $validation->stop_loss;
            if ($entry <= 0 || $stop <= 0) continue;
            $risk = abs($entry - $stop);
            if ($risk <= 0) continue;

            $trades++;
            if ($validation->outcome === 'sl') {
                $netR -= 1.0;
                $grossLossR += 1.0;
                $returnPct -= (abs($entry - $stop) / $entry) * 100;
                continue;
            }

            $levels = array_values(array_filter(array_map('floatval', (array) $validation->take_profit_levels), fn ($value) => $value > 0));
            $target = $levels !== [] ? (float) end($levels) : 0.0;
            if ($target <= 0) {
                $trades--;
                continue;
            }
            $reward = abs($target - $entry);
            $r = $reward / $risk;
            $netR += $r;
            $grossProfitR += $r;
            $returnPct += ($reward / $entry) * 100;
        }

        return [
            'model_trades' => $trades,
            'model_net_r' => round($netR, 6),
            'model_gross_profit_r' => round($grossProfitR, 6),
            'model_gross_loss_r' => round($grossLossR, 6),
            'model_return_pct' => round($returnPct, 8),
        ];
    }
}
