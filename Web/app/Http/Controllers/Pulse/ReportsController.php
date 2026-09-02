<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseSignalDailyMetric;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategyDailyMetric;
use App\Models\PulseStrategyLearningState;
use App\Services\PulseMarketDataService;
use App\Models\PulseTrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class ReportsController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $userId = $request->user()->id;
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $activityBase = PulseTrade::query()->where('user_id', $userId)->whereBetween('created_at', [$from, $to]);
        $closedBase = PulseTrade::query()->where('user_id', $userId)->where('status', 'closed')->whereBetween('closed_at', [$from, $to]);
        $realizedPnl = (float) (clone $closedBase)->sum('realized_pnl');
        $usdtFees = (float) (clone $closedBase)->where('commission_asset', 'USDT')->sum('fees');
        $closedCount = (clone $closedBase)->count();
        $wins = (clone $closedBase)->where('realized_pnl', '>', 0)->count();
        $losses = (clone $closedBase)->where('realized_pnl', '<', 0)->count();

        $signalDaily = Schema::hasTable('pulse_signal_daily_metrics')
            ? PulseSignalDailyMetric::query()->where('user_id', $userId)->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])->get()
            : collect();
        $signalWins = (int) $signalDaily->sum('wins');
        $signalLosses = (int) $signalDaily->sum('losses');
        $signalEntries = (int) $signalDaily->sum('entries');
        $signalCount = (int) $signalDaily->sum('signals');
        $plan = $request->user()->pulsePlan();
        $strategySlugs = $plan && $plan->strategies()->exists()
            ? $plan->strategies()->pluck('pulse_strategies.slug')
            : collect();
        $weightedAverage = static function ($rows, string $valueField, string $weightField): ?float {
            $numerator = 0.0; $denominator = 0;
            foreach ($rows as $row) {
                if ($row->{$valueField} === null) continue;
                $weight = max(0, (int) ($row->{$weightField} ?? 0));
                if ($weight <= 0) continue;
                $numerator += ((float) $row->{$valueField}) * $weight;
                $denominator += $weight;
            }
            return $denominator > 0 ? $numerator / $denominator : null;
        };
        $strategyDaily = Schema::hasTable('pulse_strategy_daily_metrics')
            ? PulseStrategyDailyMetric::query()->where('market_regime', 'ALL')
                ->when($strategySlugs->isNotEmpty(), fn ($q) => $q->whereIn('strategy_slug', $strategySlugs))
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])->get()
            : collect();
        $byStrategy = $strategyDaily->groupBy(fn ($row) => $row->strategy_slug.'|'.$row->strategy_version.'|'.$row->timeframe.'|'.$row->direction)
            ->map(function ($group, $key) use ($weightedAverage) {
                [$slug,$version,$timeframe,$direction] = explode('|', $key, 4);
                $wins = (int) $group->sum('wins'); $losses = (int) $group->sum('losses');
                return [
                    'strategy_slug' => $slug, 'strategy_version' => $version, 'timeframe' => $timeframe, 'direction' => $direction,
                    'samples' => (int) $group->sum('sample_count'), 'wins' => $wins, 'losses' => $losses,
                    'ambiguous' => (int) $group->sum('ambiguous'),
                    'win_rate' => ($wins + $losses) > 0 ? ($wins / ($wins + $losses)) * 100 : null,
                    'avg_mfe_r' => $weightedAverage($group, 'avg_mfe_r', 'entries'),
                    'avg_mae_r' => $weightedAverage($group, 'avg_mae_r', 'entries'),
                ];
            })->sortByDesc('samples')->take(20)->values();
        $learningStates = Schema::hasTable('pulse_strategy_learning_states')
            ? PulseStrategyLearningState::query()->where('market_regime', 'ALL')
                ->when($strategySlugs->isNotEmpty(), fn ($q) => $q->whereIn('strategy_slug', $strategySlugs))
                ->orderByDesc('sample_size')->orderByDesc('reliability_score')->limit(20)->get()
            : collect();
        $recentValidations = Schema::hasTable('pulse_signal_validations')
            ? PulseSignalValidation::query()->where('user_id', $userId)->whereBetween('generated_at', [$from, $to])->latest('generated_at')->limit(30)->get()
            : collect();
        $marketHealth = app(PulseMarketDataService::class)->health();

        return view('pulse.reports', [
            'from' => $from, 'to' => $to,
            'summary' => [
                'trades' => (clone $activityBase)->count(),
                'closed' => $closedCount,
                'wins' => $wins,
                'losses' => $losses,
                'breakeven' => max(0, $closedCount - $wins - $losses),
                'win_rate' => $closedCount > 0 ? round(($wins / $closedCount) * 100, 2) : null,
                'realized_pnl' => $realizedPnl,
                'usdt_fees' => $usdtFees,
                'net_after_usdt_fees' => $realizedPnl - $usdtFees,
                'signals' => PulseSignal::query()->where('user_id', $userId)->whereBetween('generated_at', [$from, $to])->count(),
                'scanner_runs' => PulseScannerRun::query()->where('user_id', $userId)->whereBetween('started_at', [$from, $to])->count(),
            ],
            'signalSummary' => [
                'signals' => $signalCount, 'entries' => $signalEntries,
                'entry_rate' => $signalCount > 0 ? ($signalEntries / $signalCount) * 100 : null,
                'wins' => $signalWins, 'losses' => $signalLosses,
                'ambiguous' => (int) $signalDaily->sum('ambiguous'),
                'expired_no_entry' => (int) $signalDaily->sum('expired_no_entry'),
                'expired_after_entry' => (int) $signalDaily->sum('expired_after_entry'),
                'decisive_win_rate' => ($signalWins + $signalLosses) > 0 ? ($signalWins / ($signalWins + $signalLosses)) * 100 : null,
                'avg_mfe_r' => $weightedAverage($signalDaily, 'avg_mfe_r', 'entries'),
                'avg_mae_r' => $weightedAverage($signalDaily, 'avg_mae_r', 'entries'),
            ],
            'byStrategy' => $byStrategy,
            'learningStates' => $learningStates,
            'recentValidations' => $recentValidations,
            'marketHealth' => $marketHealth,
            'bySymbol' => (clone $closedBase)->select('symbol', DB::raw('COUNT(*) as trades'), DB::raw('SUM(realized_pnl) as realized_pnl'))
                ->groupBy('symbol')->orderByDesc('trades')->get(),
            'feesByAsset' => (clone $closedBase)->selectRaw("COALESCE(commission_asset, 'UNKNOWN') as asset, SUM(fees) as fees")
                ->groupBy('commission_asset')->orderBy('asset')->get(),
            'recent' => (clone $activityBase)->latest()->limit(30)->get(),
        ]);
    }
}
