<?php

namespace App\Services;

use App\Models\PulseStrategy;
use App\Models\PulseStrategyLearningState;
use App\Models\PulseStrategyDailyMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PulseAdaptiveStrategyService
{
    private ?Collection $states = null;
    private ?Collection $recentEvidence = null;

    public function profile(PulseStrategy $strategy, string $timeframe, string $direction, ?string $marketRegime = null): array
    {
        $settings = (array) ($strategy->settings ?? []);
        $baseRr = $this->clamp((float) ($settings['risk_reward_target'] ?? 2.0), 1.0, 5.0);
        $baseWeight = max(0.0, (float) ($strategy->weight ?? 1.0));
        if (($settings['adaptive_enabled'] ?? true) === false) {
            return [
                'samples'=>0,'reliability'=>50.0,'base_rr'=>round($baseRr,2),'effective_rr'=>round($baseRr,2),
                'base_weight'=>round($baseWeight,3),'weight_multiplier'=>1.0,'effective_weight'=>round($baseWeight,3),
                'expected_r'=>null,'profit_factor'=>null,'member_eligible'=>true,'status'=>'manual','source'=>'strategy_override','scope'=>'NONE',
            ];
        }
        $direction = strtoupper(trim($direction));
        $timeframe = strtolower(trim($timeframe));
        $state = in_array($direction, ['LONG', 'SHORT'], true)
            ? $this->bestState((string) $strategy->slug, (string) ($strategy->version ?? '1.0'), $timeframe, $direction, $marketRegime)
            : null;

        if (! $state) {
            return [
                'samples' => 0,
                'reliability' => 50.0,
                'base_rr' => round($baseRr, 2),
                'effective_rr' => round($baseRr, 2),
                'base_weight' => round($baseWeight, 3),
                'weight_multiplier' => 1.0,
                'effective_weight' => round($baseWeight, 3),
                'expected_r' => null,
                'profit_factor' => null,
                'member_eligible' => true,
                'status' => 'collecting',
                'source' => 'neutral_prior',
                'scope' => 'NONE',
            ];
        }

        $samples = (int) $state->sample_size;
        $reliability = (float) $state->reliability_score;
        $meta = (array) ($state->meta ?? []);
        $optimizer = (array) ($meta['rr_optimizer'] ?? []);
        $recommendedRr = isset($optimizer['recommended_rr']) && is_numeric($optimizer['recommended_rr'])
            ? (float) $optimizer['recommended_rr']
            : null;
        $expectedR = isset($optimizer['recommended_expectancy_r']) && is_numeric($optimizer['recommended_expectancy_r'])
            ? (float) $optimizer['recommended_expectancy_r']
            : null;
        $profitFactor = isset($optimizer['recommended_profit_factor']) && is_numeric($optimizer['recommended_profit_factor'])
            ? (float) $optimizer['recommended_profit_factor']
            : null;
        $optimizerSamples = (int) ($optimizer['recommended_samples'] ?? 0);

        // Backward-compatible expectancy fallback for evidence created before V15.8.3.
        // IMPORTANT: do not infer a new target from old win rate alone. Changing R:R also
        // changes the probability of a target hit, so live R:R stays at the configured
        // value until MFE/MAE counterfactual evidence has produced a recommendation.
        if ($expectedR === null) {
            $winRate = is_numeric($state->win_rate) ? (float) $state->win_rate : null;
            if ($winRate !== null) $expectedR = ($winRate * $baseRr) - (1.0 - $winRate);
        }

        $recommendedRr ??= $baseRr;
        // Prevent live tuning from making abrupt target jumps. The optimizer may test a
        // wider grid, but member-facing target changes are deliberately conservative.
        $effectiveRr = $this->clamp($recommendedRr, max(1.0, $baseRr - 0.50), min(3.50, $baseRr + 0.50));

        $minimum = (int) config('pulse.optimizer.minimum_samples', 30);
        $established = (int) config('pulse.optimizer.established_samples', 75);
        $multiplier = 1.0;
        $eligible = true;
        $status = 'collecting';

        if ($samples >= $minimum) {
            $status = 'adaptive';
            $e = $expectedR ?? 0.0;
            $multiplier = match (true) {
                $e <= -0.20 => 0.45,
                $e <= -0.08 => 0.62,
                $e < 0.00 => 0.78,
                $e < 0.08 => 0.92,
                $e < 0.20 => 1.03,
                default => 1.12,
            };

            // Reliability is a secondary stabilizer. It can modestly change the
            // multiplier but cannot overpower realized expectancy evidence.
            if ($reliability < 30) $multiplier *= 0.82;
            elseif ($reliability > 65) $multiplier *= 1.06;

            if ($samples >= $established && $e <= -0.22 && $reliability < 38) {
                $eligible = false;
                $status = 'research_only';
                $multiplier = min($multiplier, 0.35);
            } elseif ($e < 0) {
                $status = 'throttled';
            } elseif ($e >= 0.12) {
                $status = 'preferred';
            }
        }

        // Short-horizon outcomes are a safety override, never a reason to inflate
        // weights. Decisive evidence is aggregated from the unique ALL regime rows,
        // independently for each strategy/timeframe/direction, with no user P/L mixing.
        $recent = $this->recentFor((string) $strategy->slug, (string) ($strategy->version ?? '1.0'), $timeframe, $direction);
        $recentResolved = (int) ($recent['decisive'] ?? 0);
        $recentExpectancy = $recentResolved >= 10 ? (float) ($recent['net_r'] ?? 0.0) / $recentResolved : null;
        if ($recentExpectancy !== null && $recentExpectancy < -0.12 && $samples >= $minimum) {
            $multiplier = min($multiplier, $recentExpectancy < -0.30 ? 0.40 : 0.65);
            if ($status !== 'research_only') $status = 'recent_loss_throttle';
        }
        // Do not let an optimistic counterfactual R:R overcome negative observed
        // expectancy. Only recommend an alternative reward target when evidence is
        // sufficient AND the validated expectation is positive.
        $actualR = $samples >= $minimum && isset($meta['wins'], $meta['losses'])
            ? (((float)$meta['wins'] * $baseRr) - (float)$meta['losses']) / max(1, (int)$meta['wins'] + (int)$meta['losses'])
            : null;
        if ($actualR !== null && $actualR <= 0.0) {
            $effectiveRr = $baseRr;
            $multiplier = min($multiplier, 0.78);
            if ($status !== 'research_only') $status = 'throttled';
        }
        $multiplier = $this->clamp($multiplier, 0.25, 1.20);
        $effectiveWeight = $baseWeight * $multiplier;

        return [
            'recent_samples' => $recentResolved,
            'recent_expectancy_r' => $recentExpectancy === null ? null : round($recentExpectancy, 4),
            'observed_expectancy_r' => $actualR === null ? null : round($actualR, 4),
            'samples' => $samples,
            'optimizer_samples' => $optimizerSamples,
            'reliability' => round($reliability, 2),
            'base_rr' => round($baseRr, 2),
            'effective_rr' => round($effectiveRr, 2),
            'base_weight' => round($baseWeight, 3),
            'weight_multiplier' => round($multiplier, 3),
            'effective_weight' => round($effectiveWeight, 3),
            'expected_r' => $expectedR === null ? null : round($expectedR, 4),
            'profit_factor' => $profitFactor === null ? null : round($profitFactor, 4),
            'member_eligible' => $eligible,
            'status' => $status,
            'source' => $optimizer !== [] ? 'mfe_mae_optimizer' : 'learning_fallback',
            'scope' => (string) $state->market_regime,
        ];
    }

    public function summaryForStrategy(PulseStrategy $strategy): array
    {
        $settings = (array) ($strategy->settings ?? []);
        $baseRr = $this->clamp((float) ($settings['risk_reward_target'] ?? 2.0), 1.0, 5.0);
        $states = $this->allStates()->filter(fn ($state) =>
            (string) $state->strategy_slug === (string) $strategy->slug &&
            (string) $state->strategy_version === (string) ($strategy->version ?? '1.0') &&
            (string) $state->market_regime === 'ALL'
        );

        if ($states->isEmpty()) {
            return [
                'status' => 'collecting', 'samples' => 0, 'effective_rr' => round($baseRr, 2),
                'weight_multiplier' => 1.0, 'expected_r' => null, 'member_eligible' => true,
            ];
        }

        $profiles = $states->map(fn ($state) => $this->profile($strategy, (string) $state->timeframe, (string) $state->direction, 'ALL'));
        $den = max(1, (int) $profiles->sum(fn ($p) => max(1, (int) ($p['samples'] ?? 0))));
        $weighted = static function (Collection $rows, string $key, int $den): ?float {
            $withValue = $rows->filter(fn ($p) => isset($p[$key]) && is_numeric($p[$key]));
            if ($withValue->isEmpty()) return null;
            $localDen = max(1, (int) $withValue->sum(fn ($p) => max(1, (int) ($p['samples'] ?? 0))));
            return (float) $withValue->sum(fn ($p) => (float) $p[$key] * max(1, (int) ($p['samples'] ?? 0))) / $localDen;
        };

        $eligible = $profiles->contains(fn ($p) => (bool) ($p['member_eligible'] ?? false));
        $allResearchOnly = $profiles->every(fn ($p) => ! (bool) ($p['member_eligible'] ?? true));
        $hasThrottle = $profiles->contains(fn ($p) => in_array((string)($p['status'] ?? ''), ['throttled','recent_loss_throttle','research_only'], true));
        $hasPreferred = $profiles->contains(fn ($p) => (string)($p['status'] ?? '') === 'preferred');
        $hasAdaptive = $profiles->contains(fn ($p) => (string)($p['status'] ?? '') === 'adaptive');
        $status = $allResearchOnly ? 'research_only' : ($hasThrottle ? 'throttled' : ($hasPreferred ? 'preferred' : ($hasAdaptive ? 'adaptive' : 'collecting')));

        return [
            'status' => $status,
            'samples' => (int) $profiles->sum('samples'),
            'effective_rr' => round($weighted($profiles, 'effective_rr', $den) ?? $baseRr, 2),
            'weight_multiplier' => round($weighted($profiles, 'weight_multiplier', $den) ?? 1.0, 2),
            'expected_r' => ($value = $weighted($profiles, 'expected_r', $den)) === null ? null : round($value, 4),
            'observed_expectancy_r' => ($actual = $weighted($profiles, 'observed_expectancy_r', $den)) === null ? null : round($actual, 4),
            'recent_expectancy_r' => ($recent = $weighted($profiles, 'recent_expectancy_r', $den)) === null ? null : round($recent, 4),
            'member_eligible' => $eligible,
        ];
    }

    private function recentFor(string $slug, string $version, string $timeframe, string $direction): array
    {
        if ($this->recentEvidence === null) {
            $this->recentEvidence = Schema::hasTable('pulse_strategy_daily_metrics')
                ? PulseStrategyDailyMetric::query()->where('market_regime', 'ALL')
                    ->where('metric_date', '>=', now()->subDays(13)->toDateString())->get()
                : collect();
        }
        $rows = $this->recentEvidence->filter(fn ($row) =>
            (string)$row->strategy_slug === $slug &&
            (string)$row->strategy_version === $version &&
            strtolower((string)$row->timeframe) === $timeframe &&
            strtoupper((string)$row->direction) === $direction
        );
        return ['decisive' => (int)$rows->sum('wins') + (int)$rows->sum('losses'),
            'net_r' => (float)$rows->sum('model_net_r')];
    }

    private function bestState(string $slug, string $version, string $timeframe, string $direction, ?string $marketRegime): ?PulseStrategyLearningState
    {
        $states = $this->allStates()->filter(fn ($state) =>
            (string) $state->strategy_slug === $slug &&
            (string) $state->strategy_version === $version &&
            strtolower((string) $state->timeframe) === $timeframe &&
            strtoupper((string) $state->direction) === $direction
        );
        if ($states->isEmpty()) return null;

        $contextMinimum = (int) config('pulse.learning.context_minimum_samples', 40);
        if ($marketRegime) {
            $exact = $states->first(fn ($state) => (string) $state->market_regime === (string) $marketRegime && (int) $state->sample_size >= $contextMinimum);
            if ($exact) return $exact;
        }
        return $states->firstWhere('market_regime', 'ALL') ?: $states->sortByDesc('sample_size')->first();
    }

    private function allStates(): Collection
    {
        if ($this->states !== null) return $this->states;
        if (! Schema::hasTable('pulse_strategy_learning_states')) return $this->states = collect();
        return $this->states = PulseStrategyLearningState::query()->get();
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
