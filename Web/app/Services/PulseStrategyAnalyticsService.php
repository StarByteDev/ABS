<?php

namespace App\Services;

use App\Models\PulseSignalDailyMetric;
use App\Models\PulseStrategyDailyMetric;
use App\Models\PulseTrade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PulseStrategyAnalyticsService
{
    public function simulation(Carbon $from, Carbon $to, array $filters = [], ?int $userId = null): array
    {
        $strategy = trim((string) ($filters['strategy'] ?? ''));
        $timeframe = strtolower(trim((string) ($filters['timeframe'] ?? '')));
        $direction = strtoupper(trim((string) ($filters['direction'] ?? '')));

        if ($strategy !== '' && Schema::hasTable('pulse_strategy_daily_metrics')) {
            $query = PulseStrategyDailyMetric::query()
                ->where('market_regime', 'ALL')
                ->where('strategy_slug', $strategy)
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()]);
            if ($timeframe !== '') $query->where('timeframe', $timeframe);
            if ($direction !== '') $query->where('direction', $direction);
            $rows = $query->get();
        } elseif (Schema::hasTable('pulse_signal_daily_metrics')) {
            $query = PulseSignalDailyMetric::query()
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()]);
            if ($userId === null) $query->whereNull('user_id'); else $query->where('user_id', $userId);
            if ($timeframe !== '') $query->where('timeframe', $timeframe);
            if ($direction !== '') $query->where('direction', $direction);
            $rows = $query->get();
        } else {
            $rows = collect();
        }

        return $this->aggregateSimulationRows($rows);
    }

    public function strategyProfitability(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if (! Schema::hasTable('pulse_strategy_daily_metrics')) return collect();
        $query = PulseStrategyDailyMetric::query()
            ->where('market_regime', 'ALL')
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()]);
        if (($filters['strategy'] ?? '') !== '') $query->where('strategy_slug', $filters['strategy']);
        if (($filters['timeframe'] ?? '') !== '') $query->where('timeframe', $filters['timeframe']);
        if (($filters['direction'] ?? '') !== '') $query->where('direction', $filters['direction']);

        return $query->get()->groupBy('strategy_slug')->map(function (Collection $rows, string $slug): array {
            $summary = $this->aggregateSimulationRows($rows);
            $wins = (int) $rows->sum('wins');
            $losses = (int) $rows->sum('losses');
            return [
                'strategy_slug' => $slug,
                'samples' => (int) $rows->sum('sample_count'),
                'entries' => (int) $rows->sum('entries'),
                'wins' => $wins,
                'losses' => $losses,
                'ambiguous' => (int) $rows->sum('ambiguous'),
                'win_rate' => ($wins + $losses) > 0 ? ($wins / ($wins + $losses)) * 100 : null,
                ...$summary,
            ];
        })->sortByDesc('model_net_r')->values();
    }

    public function dailySimulationTrend(Carbon $from, Carbon $to, array $filters = [], ?int $userId = null): Collection
    {
        $strategy = trim((string) ($filters['strategy'] ?? ''));
        if ($strategy !== '' && Schema::hasTable('pulse_strategy_daily_metrics')) {
            $query = PulseStrategyDailyMetric::query()->where('market_regime', 'ALL')->where('strategy_slug', $strategy)
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()]);
        } elseif (Schema::hasTable('pulse_signal_daily_metrics')) {
            $query = PulseSignalDailyMetric::query()->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()]);
            if ($userId === null) $query->whereNull('user_id'); else $query->where('user_id', $userId);
        } else {
            return collect();
        }
        if (($filters['timeframe'] ?? '') !== '') $query->where('timeframe', $filters['timeframe']);
        if (($filters['direction'] ?? '') !== '') $query->where('direction', $filters['direction']);

        $cumulativeR = 0.0;
        $cumulativePct = 0.0;
        return $query->get()->groupBy(fn ($row) => $row->metric_date?->format('Y-m-d') ?? (string) $row->metric_date)
            ->sortKeys()->map(function (Collection $rows, string $date) use (&$cumulativeR, &$cumulativePct): array {
                $dayR = (float) $rows->sum('model_net_r');
                $dayPct = (float) $rows->sum('model_return_pct');
                $cumulativeR += $dayR;
                $cumulativePct += $dayPct;
                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('d M'),
                    'trades' => (int) $rows->sum('model_trades'),
                    'net_r' => round($dayR, 4),
                    'cumulative_r' => round($cumulativeR, 4),
                    'return_pct' => round($dayPct, 4),
                    'cumulative_return_pct' => round($cumulativePct, 4),
                ];
            })->values();
    }

    public function actualExecution(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasTable('pulse_trades')) return $this->emptyExecution();
        $createdQuery = PulseTrade::query()->whereBetween('created_at', [$from, $to]);
        $closed = PulseTrade::query()->with('signal')->where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to])->get();
        $tp = $closed->where('close_reason', 'take_profit')->count();
        $sl = $closed->where('close_reason', 'stop_loss')->count();
        $other = max(0, $closed->count() - $tp - $sl);
        $realized = (float) $closed->sum('realized_pnl');
        $fees = (float) $closed->sum('fees');

        $strategy = [];
        foreach ($closed as $trade) {
            $signal = $trade->signal;
            if (! $signal) continue;
            foreach ((array) ($signal->strategy_snapshot ?: $signal->strategy_breakdown) as $item) {
                if (! is_array($item) || isset($item['_meta']) || (float) ($item['points'] ?? 0) <= 0) continue;
                $bias = strtoupper((string) ($item['bias'] ?? 'NEUTRAL'));
                if (! in_array($bias, [strtoupper((string) $signal->direction), 'NEUTRAL'], true)) continue;
                $slug = (string) ($item['slug'] ?? '');
                if ($slug === '') continue;
                $strategy[$slug] ??= ['strategy_slug'=>$slug,'closed_trades'=>0,'profitable_trades'=>0,'losing_trades'=>0,'realized_pnl'=>0.0,'fees'=>0.0];
                $strategy[$slug]['closed_trades']++;
                $pnl = (float) $trade->realized_pnl;
                if ($pnl > 0) $strategy[$slug]['profitable_trades']++;
                elseif ($pnl < 0) $strategy[$slug]['losing_trades']++;
                $strategy[$slug]['realized_pnl'] += $pnl;
                $strategy[$slug]['fees'] += (float) $trade->fees;
            }
        }
        $strategy = collect($strategy)->map(function (array $row): array {
            $row['realized_pnl'] = round($row['realized_pnl'], 6);
            $row['fees'] = round($row['fees'], 6);
            $row['profitability_rate'] = $row['closed_trades'] > 0 ? ($row['profitable_trades'] / $row['closed_trades']) * 100 : null;
            return $row;
        })->sortByDesc('realized_pnl')->values();

        return [
            'trades_created' => (clone $createdQuery)->count(),
            'closed_trades' => $closed->count(),
            'open_trades' => PulseTrade::query()->whereIn('status', ['submitting','pending','open','closing','protection_failed'])->count(),
            'profitable_trades' => $closed->where('realized_pnl', '>', 0)->count(),
            'losing_trades' => $closed->where('realized_pnl', '<', 0)->count(),
            'tp_hits' => $tp,
            'sl_hits' => $sl,
            'other_exits' => $other,
            'tp_hit_ratio' => ($tp + $sl) > 0 ? ($tp / ($tp + $sl)) * 100 : null,
            'realized_pnl' => round($realized, 8),
            'fees' => round($fees, 8),
            'strategy_attribution' => $strategy,
            'strategy_attribution_note' => 'A trade can contain multiple contributing strategies; attributed P&L is diagnostic and must not be summed across strategies.',
        ];
    }

    private function aggregateSimulationRows(Collection $rows): array
    {
        $trades = (int) $rows->sum('model_trades');
        $netR = (float) $rows->sum('model_net_r');
        $grossProfitR = (float) $rows->sum('model_gross_profit_r');
        $grossLossR = (float) $rows->sum('model_gross_loss_r');
        $returnPct = (float) $rows->sum('model_return_pct');
        return [
            'model_trades' => $trades,
            'model_net_r' => round($netR, 6),
            'model_gross_profit_r' => round($grossProfitR, 6),
            'model_gross_loss_r' => round($grossLossR, 6),
            'model_expectancy_r' => $trades > 0 ? round($netR / $trades, 6) : null,
            'model_profit_factor' => $grossLossR > 0 ? round($grossProfitR / $grossLossR, 4) : null,
            'model_return_pct' => round($returnPct, 8),
            'methodology' => 'Resolved TP/SL only; entry must be observed first; equal-risk/equal-notional research model; no leverage, fees, funding, slippage or compounding; ambiguous and unresolved exits excluded.',
        ];
    }

    private function emptyExecution(): array
    {
        return ['trades_created'=>0,'closed_trades'=>0,'open_trades'=>0,'profitable_trades'=>0,'losing_trades'=>0,'tp_hits'=>0,'sl_hits'=>0,'other_exits'=>0,'tp_hit_ratio'=>null,'realized_pnl'=>0.0,'fees'=>0.0,'strategy_attribution'=>collect(),'strategy_attribution_note'=>''];
    }
}
