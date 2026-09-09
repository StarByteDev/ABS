<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulseAlert;
use App\Models\PulseAuditLog;
use App\Models\PulsePair;
use App\Models\PulseMembershipRequest;
use App\Models\PulsePromotionCode;
use App\Models\PulsePlan;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseSignalDailyMetric;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategyDailyMetric;
use App\Models\PulseStrategyLearningState;
use App\Models\PulseMarketDataRun;
use App\Models\PulseStrategy;
use App\Models\PulseSystemSetting;
use App\Models\PulseTrade;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Services\BinanceFuturesService;
use App\Services\BrandedMailService;
use App\Services\PulseAuditService;
use App\Services\PulseMembershipService;
use App\Services\PulseMarketDataService;
use App\Services\PulseStrategyAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminPulseController extends Controller
{
    public function dashboard()
    {
        return view('admin.pulse.dashboard', [
            'activeUsers' => UserServiceAccess::query()->where('service', 'pulse')->where('status', 'active')->count(),
            'activeSignals' => PulseSignal::query()->where('status', 'active')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'openTrades' => PulseTrade::query()->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed'])->count(),
            'unprotectedTrades' => PulseTrade::query()->whereIn('status', ['open', 'protection_failed'])->where('protection_status', '!=', 'confirmed')->count(),
            'scannerRunsToday' => PulseScannerRun::query()->whereDate('created_at', today())->count(),
            'realizedPnl' => (float) PulseTrade::query()->sum('realized_pnl'),
            'latestRuns' => PulseScannerRun::query()->with('user')->latest()->limit(10)->get(),
            'latestTrades' => PulseTrade::query()->with('user')->latest()->limit(10)->get(),
            'latestLogs' => PulseAuditLog::query()->with('user')->latest('created_at')->limit(10)->get(),
        ]);
    }

    public function intelligence(Request $request, PulseMarketDataService $market, PulseStrategyAnalyticsService $analytics)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'strategy' => ['nullable', 'string', 'max:100'],
            'timeframe' => ['nullable', Rule::in(['15m', '4h'])],
            'direction' => ['nullable', Rule::in(['LONG', 'SHORT'])],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $filters = [
            'strategy' => trim((string) ($data['strategy'] ?? '')),
            'timeframe' => strtolower(trim((string) ($data['timeframe'] ?? ''))),
            'direction' => strtoupper(trim((string) ($data['direction'] ?? ''))),
        ];
        $catalog = Schema::hasTable('pulse_strategies')
            ? PulseStrategy::query()->orderBy('sort_order')->orderBy('name')->get()
            : collect();
        if ($filters['strategy'] !== '' && ! $catalog->contains('slug', $filters['strategy'])) {
            $filters['strategy'] = '';
        }

        $daily = Schema::hasTable('pulse_signal_daily_metrics')
            ? PulseSignalDailyMetric::query()->whereNull('user_id')->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])->get()
            : collect();
        if ($filters['timeframe'] !== '') $daily = $daily->where('timeframe', $filters['timeframe']);
        if ($filters['direction'] !== '') $daily = $daily->where('direction', $filters['direction']);
        $wins = (int) $daily->sum('wins'); $losses = (int) $daily->sum('losses');
        $entries = (int) $daily->sum('entries'); $signals = (int) $daily->sum('signals');
        $weighted = static function ($rows, string $value, string $weight): ?float {
            $n = 0.0; $d = 0;
            foreach ($rows as $row) {
                if ($row->{$value} === null) continue;
                $w = max(0, (int) ($row->{$weight} ?? 0));
                if ($w <= 0) continue;
                $n += ((float) $row->{$value}) * $w; $d += $w;
            }
            return $d > 0 ? $n / $d : null;
        };

        $strategyRows = collect();
        if (Schema::hasTable('pulse_strategy_daily_metrics')) {
            $strategyQuery = PulseStrategyDailyMetric::query()
                ->where('market_regime', 'ALL')
                ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()]);
            if ($filters['strategy'] !== '') $strategyQuery->where('strategy_slug', $filters['strategy']);
            if ($filters['timeframe'] !== '') $strategyQuery->where('timeframe', $filters['timeframe']);
            if ($filters['direction'] !== '') $strategyQuery->where('direction', $filters['direction']);
            $strategyRows = $strategyQuery->get();
        }
        $allTimeStrategyRows = collect();
        if (Schema::hasTable('pulse_strategy_daily_metrics')) {
            $allTimeQuery = PulseStrategyDailyMetric::query()->where('market_regime', 'ALL');
            if ($filters['strategy'] !== '') $allTimeQuery->where('strategy_slug', $filters['strategy']);
            if ($filters['timeframe'] !== '') $allTimeQuery->where('timeframe', $filters['timeframe']);
            if ($filters['direction'] !== '') $allTimeQuery->where('direction', $filters['direction']);
            $allTimeStrategyRows = $allTimeQuery->get();
        }
        if ($filters['strategy'] !== '') {
            // Keep the KPI strip honest when Admin drills into one strategy.
            $signals = (int) $strategyRows->sum('sample_count');
            $entries = (int) $strategyRows->sum('entries');
            $wins = (int) $strategyRows->sum('wins');
            $losses = (int) $strategyRows->sum('losses');
        }
        $strategies = $strategyRows->groupBy(fn ($row) => $row->strategy_slug.'|'.$row->strategy_version.'|'.$row->timeframe.'|'.$row->direction)
            ->map(function ($group, $key) use ($weighted) {
                [$slug,$version,$tf,$direction] = explode('|', $key, 4);
                $w = (int) $group->sum('wins'); $l = (int) $group->sum('losses');
                return [
                    'slug'=>$slug,'version'=>$version,'timeframe'=>$tf,'direction'=>$direction,
                    'samples'=>(int)$group->sum('sample_count'),'entries'=>(int)$group->sum('entries'),'wins'=>$w,'losses'=>$l,
                    'ambiguous'=>(int)$group->sum('ambiguous'),'win_rate'=>($w+$l)>0?($w/($w+$l))*100:null,
                    'avg_mfe_r'=>$weighted($group,'avg_mfe_r','entries'),'avg_mae_r'=>$weighted($group,'avg_mae_r','entries'),
                ];
            })->sortByDesc('samples')->values()->take(60);

        $learningRows = collect();
        if (Schema::hasTable('pulse_strategy_learning_states')) {
            $learningQuery = PulseStrategyLearningState::query()->where('market_regime', 'ALL');
            if ($filters['strategy'] !== '') $learningQuery->where('strategy_slug', $filters['strategy']);
            if ($filters['timeframe'] !== '') $learningQuery->where('timeframe', $filters['timeframe']);
            if ($filters['direction'] !== '') $learningQuery->where('direction', $filters['direction']);
            $learningRows = $learningQuery->get();
        }

        $visibleCatalog = $filters['strategy'] === '' ? $catalog : $catalog->where('slug', $filters['strategy']);
        $evidenceRank = ['insufficient' => 1, 'developing' => 2, 'established' => 3];
        $strategyRollups = $visibleCatalog->map(function (PulseStrategy $strategy) use ($strategyRows, $allTimeStrategyRows, $learningRows, $weighted, $evidenceRank) {
            $metrics = $strategyRows->where('strategy_slug', $strategy->slug);
            $allMetrics = $allTimeStrategyRows->where('strategy_slug', $strategy->slug);
            $learning = $learningRows->where('strategy_slug', $strategy->slug);
            $samples = (int) $metrics->sum('sample_count');
            $entries = (int) $metrics->sum('entries');
            $wins = (int) $metrics->sum('wins');
            $losses = (int) $metrics->sum('losses');
            $ambiguous = (int) $metrics->sum('ambiguous');
            $decisive = $wins + $losses;
            $allSamples = (int) $allMetrics->sum('sample_count');
            $allEntries = (int) $allMetrics->sum('entries');
            $allWins = (int) $allMetrics->sum('wins');
            $allLosses = (int) $allMetrics->sum('losses');
            $allAmbiguous = (int) $allMetrics->sum('ambiguous');
            $allDecisive = $allWins + $allLosses;
            $periodWinRate = $decisive > 0 ? ($wins / $decisive) * 100 : null;
            $allTimeWinRate = $allDecisive > 0 ? ($allWins / $allDecisive) * 100 : null;
            $modelTrades = (int) $metrics->sum('model_trades');
            $modelNetR = (float) $metrics->sum('model_net_r');
            $modelGrossProfitR = (float) $metrics->sum('model_gross_profit_r');
            $modelGrossLossR = (float) $metrics->sum('model_gross_loss_r');
            $modelReturnPct = (float) $metrics->sum('model_return_pct');
            $allModelTrades = (int) $allMetrics->sum('model_trades');
            $allModelNetR = (float) $allMetrics->sum('model_net_r');
            $allModelReturnPct = (float) $allMetrics->sum('model_return_pct');
            $learningWeight = max(0, (int) $learning->sum('sample_size'));
            $reliability = $learning->isEmpty() ? null : ($learningWeight > 0
                ? $learning->sum(fn ($row) => (float) $row->reliability_score * max(0, (int) $row->sample_size)) / $learningWeight
                : (float) $learning->avg('reliability_score'));
            $evidence = $learning->sortByDesc(fn ($row) => $evidenceRank[$row->evidence_level] ?? 0)->first()?->evidence_level;

            return [
                'name' => $strategy->name,
                'slug' => $strategy->slug,
                'version' => $strategy->version ?: '1.0',
                'enabled' => (bool) $strategy->is_enabled,
                'samples' => $samples,
                'entries' => $entries,
                'entry_rate' => $samples > 0 ? ($entries / $samples) * 100 : null,
                'wins' => $wins,
                'losses' => $losses,
                'win_rate' => $periodWinRate,
                'ambiguous' => $ambiguous,
                'avg_mfe_r' => $weighted($metrics, 'avg_mfe_r', 'entries'),
                'avg_mae_r' => $weighted($metrics, 'avg_mae_r', 'entries'),
                'model_trades' => $modelTrades,
                'model_net_r' => $modelNetR,
                'model_expectancy_r' => $modelTrades > 0 ? $modelNetR / $modelTrades : null,
                'model_profit_factor' => $modelGrossLossR > 0 ? $modelGrossProfitR / $modelGrossLossR : null,
                'model_return_pct' => $modelReturnPct,
                'reliability' => $reliability,
                // Pulse confidence uses 75% technical score + 25% learned reliability.
                'confidence_impact' => $reliability === null ? null : ($reliability - 50.0) * 0.25,
                'all_time_samples' => $allSamples,
                'all_time_entries' => $allEntries,
                'all_time_wins' => $allWins,
                'all_time_losses' => $allLosses,
                'all_time_ambiguous' => $allAmbiguous,
                'all_time_win_rate' => $allTimeWinRate,
                'all_time_model_trades' => $allModelTrades,
                'all_time_model_net_r' => $allModelNetR,
                'all_time_model_expectancy_r' => $allModelTrades > 0 ? $allModelNetR / $allModelTrades : null,
                'all_time_model_return_pct' => $allModelReturnPct,
                // Reporting comparison only: how the selected period's decisive outcome rate
                // differs from all-time evidence after the model's 25% reliability weight.
                'range_confidence_delta' => $periodWinRate === null || $allTimeWinRate === null ? null : ($periodWinRate - $allTimeWinRate) * 0.25,
                'evidence' => $evidence ?: 'insufficient',
                'calculated_at' => $learning->max('calculated_at'),
            ];
        })->values();

        $trendSource = $filters['strategy'] !== '' ? $strategyRows : $daily;
        $intelligenceTrend = $trendSource->groupBy(fn ($row) => $row->metric_date?->format('Y-m-d') ?? (string) $row->metric_date)
            ->map(function ($rows, $date): array {
                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('d M'),
                    'signals' => (int) $rows->sum(fn ($row) => (int) ($row->signals ?? $row->sample_count ?? 0)),
                    'entries' => (int) $rows->sum('entries'),
                    'wins' => (int) $rows->sum('wins'),
                    'losses' => (int) $rows->sum('losses'),
                    'ambiguous' => (int) $rows->sum('ambiguous'),
                ];
            })->sortBy('date')->values();

        $recentValidationQuery = Schema::hasTable('pulse_signal_validations')
            ? PulseSignalValidation::query()->whereBetween('generated_at', [$from, $to])->latest('generated_at')
            : null;
        if ($recentValidationQuery && $filters['timeframe'] !== '') $recentValidationQuery->where('timeframe', $filters['timeframe']);
        if ($recentValidationQuery && $filters['direction'] !== '') $recentValidationQuery->where('direction', $filters['direction']);
        $recentValidations = $recentValidationQuery ? $recentValidationQuery->limit(50)->get() : collect();
        $pendingValidations = 0;
        if (Schema::hasTable('pulse_signal_validations')) {
            $pendingQuery = PulseSignalValidation::query()->whereBetween('generated_at', [$from, $to])->whereNull('resolved_at');
            if ($filters['timeframe'] !== '') $pendingQuery->where('timeframe', $filters['timeframe']);
            if ($filters['direction'] !== '') $pendingQuery->where('direction', $filters['direction']);
            if ($filters['strategy'] !== '') $pendingQuery->whereJsonContains('strategy_snapshot', ['slug' => $filters['strategy']]);
            $pendingValidations = $pendingQuery->count();
        }

        $simulation = $analytics->simulation($from, $to, $filters);
        $simulationTrend = $analytics->dailySimulationTrend($from, $to, $filters);
        $strategyProfitability = $analytics->strategyProfitability($from, $to, $filters);
        $execution = $analytics->actualExecution($from, $to);

        return view('admin.pulse.intelligence', [
            'from'=>$from,'to'=>$to,'filters'=>$filters,'strategyCatalog'=>$catalog,'marketHealth'=>$market->health(),
            'summary'=>[
                'signals'=>$signals,'entries'=>$entries,'entry_rate'=>$signals>0?($entries/$signals)*100:null,
                'wins'=>$wins,'losses'=>$losses,'ambiguous'=>(int)($filters['strategy'] !== '' ? $strategyRows : $daily)->sum('ambiguous'),
                'expired_no_entry'=>(int)($filters['strategy'] !== '' ? $strategyRows : $daily)->sum('expired_no_entry'),
                'expired_after_entry'=>$filters['strategy'] !== '' ? 0 : (int)$daily->sum('expired_after_entry'),
                'decisive_win_rate'=>($wins+$losses)>0?($wins/($wins+$losses))*100:null,
                'avg_mfe_r'=>$weighted($filters['strategy'] !== '' ? $strategyRows : $daily,'avg_mfe_r','entries'),
                'avg_mae_r'=>$weighted($filters['strategy'] !== '' ? $strategyRows : $daily,'avg_mae_r','entries'),
                'catalog_strategies'=>$visibleCatalog->count(),'tracked_strategies'=>$strategyRollups->where('samples','>',0)->count(),
                'pending_validations'=>$pendingValidations,
            ],
            'strategies'=>$strategies,
            'strategyRollups'=>$strategyRollups,
            'intelligenceTrend'=>$intelligenceTrend,
            'learningStates'=>$learningRows->sortByDesc('sample_size')->take(100)->values(),
            'learningUpdatedAt'=>$learningRows->max('calculated_at'),
            'recentValidations'=>$recentValidations,
            'simulation'=>$simulation,
            'simulationTrend'=>$simulationTrend,
            'strategyProfitability'=>$strategyProfitability,
            'execution'=>$execution,
            'marketRuns'=>Schema::hasTable('pulse_market_data_runs') ? PulseMarketDataRun::query()->latest('id')->limit(20)->get() : collect(),
        ]);
    }

    public function plans()
    {
        $now = now();
        return view('admin.pulse.plans', [
            'plans' => PulsePlan::query()->with(['strategies','pairs'])->withCount([
                'accesses as assigned_count' => fn ($q) => $q->where('service','pulse'),
                'accesses as active_count' => fn ($q) => $q->where('service','pulse')->where('status','active')->where(fn($x)=>$x->whereNull('ends_at')->orWhere('ends_at','>',$now)),
                'accesses as expiring_count' => fn ($q) => $q->where('service','pulse')->where('status','active')->whereNotNull('ends_at')->whereBetween('ends_at',[$now,$now->copy()->addDays(30)]),
            ])->orderBy('sort_order')->get(),
            'strategies' => PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get(),
            'pairs' => PulsePair::query()->where('is_enabled', true)->orderBy('quote_asset')->orderBy('symbol')->get(),
            'capabilityLabels' => PulsePlan::CAPABILITIES,
        ]);
    }

    public function storePlan(Request $request, PulseAuditService $audit)
    {
        $data = $this->validatePlan($request);
        $data = $this->planBooleans($request, $data);
        $plan = PulsePlan::create($data);
        $this->syncPlanStrategies($plan, $request);
        $this->syncPlanPairs($plan, $request);
        $audit->record('admin.plan_created', $request->user(), 'PulsePlan', $plan->id, null, ['slug' => $plan->slug], $request);
        return back()->with('success', 'Pulse plan created and strategy permissions saved.');
    }

    public function updatePlan(Request $request, PulsePlan $plan, PulseAuditService $audit)
    {
        $data = $this->validatePlan($request, $plan);
        $plan->update($this->planBooleans($request, $data));
        $this->syncPlanStrategies($plan, $request);
        $this->syncPlanPairs($plan, $request);
        $audit->record('admin.plan_updated', $request->user(), 'PulsePlan', $plan->id, null, ['slug' => $plan->slug], $request);
        return back()->with('success', 'Pulse plan, limits and strategies updated.');
    }

    public function deletePlan(Request $request, PulsePlan $plan, PulseAuditService $audit)
    {
        if ($plan->accesses()->exists()) return back()->withErrors(['plan' => 'This plan is assigned to users. Disable it instead of deleting it.']);
        $id = $plan->id; $plan->strategies()->detach(); $plan->pairs()->detach(); $plan->delete();
        $audit->record('admin.plan_deleted', $request->user(), 'PulsePlan', $id, null, [], $request);
        return back()->with('success', 'Pulse plan deleted.');
    }

    public function strategies()
    {
        $strategies=PulseStrategy::query()->withCount('plans')->orderBy('sort_order')->get();
        return view('admin.pulse.strategies', [
            'strategies'=>$strategies,
            'summary'=>[
                'total'=>$strategies->count(),
                'enabled'=>$strategies->where('is_enabled',true)->count(),
                'disabled'=>$strategies->where('is_enabled',false)->count(),
                'assigned'=>$strategies->where('plans_count','>',0)->count(),
                'timeframes'=>$strategies->pluck('timeframe')->filter()->unique()->count(),
            ],
        ]);
    }

    public function storeStrategy(Request $request, PulseAuditService $audit)
    {
        $data = $this->validateStrategy($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        validator(['slug' => $data['slug']], ['slug' => [Rule::unique('pulse_strategies', 'slug')]])->validate();
        $data['settings'] = $this->decodeJson($data['settings_json'] ?? null);
        unset($data['settings_json']);
        $data['is_enabled'] = $request->boolean('is_enabled');
        $strategy = PulseStrategy::create($data);
        PulsePlan::query()->where('is_active', true)->each(fn (PulsePlan $plan) => $plan->strategies()->syncWithoutDetaching([$strategy->id => ['is_enabled' => true]]));
        $audit->record('admin.strategy_created', $request->user(), 'PulseStrategy', $strategy->id, null, ['slug' => $strategy->slug], $request);
        return back()->with('success', 'Pulse strategy created and attached to active plans.');
    }

    public function updateStrategy(Request $request, PulseStrategy $strategy, PulseAuditService $audit)
    {
        $data = $this->validateStrategy($request, $strategy);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        validator(['slug' => $data['slug']], ['slug' => [Rule::unique('pulse_strategies', 'slug')->ignore($strategy->id)]])->validate();
        $data['settings'] = $this->decodeJson($data['settings_json'] ?? null);
        unset($data['settings_json']);
        $data['is_enabled'] = $request->boolean('is_enabled');
        $strategy->update($data);
        $audit->record('admin.strategy_updated', $request->user(), 'PulseStrategy', $strategy->id, null, ['slug' => $strategy->slug], $request);
        return back()->with('success', 'Pulse strategy updated.');
    }

    public function deleteStrategy(Request $request, PulseStrategy $strategy, PulseAuditService $audit)
    {
        $id = $strategy->id; $strategy->plans()->detach(); $strategy->delete();
        $audit->record('admin.strategy_deleted', $request->user(), 'PulseStrategy', $id, null, [], $request);
        return back()->with('success', 'Pulse strategy deleted.');
    }

    public function pairs(Request $request)
    {
        $query = PulsePair::query()->orderBy('quote_asset')->orderBy('sort_order')->orderBy('symbol');
        if ($request->filled('q')) {
            $term = '%'.strtoupper(trim((string) $request->string('q'))).'%';
            $query->where(fn ($q) => $q->where('symbol', 'like', $term)->orWhere('base_asset', 'like', $term)->orWhere('quote_asset', 'like', $term));
        }
        if ($request->filled('quote')) $query->where('quote_asset', strtoupper((string) $request->string('quote')));
        if ($request->filled('status')) $query->where('is_enabled', $request->string('status')->value() === 'enabled');
        return view('admin.pulse.pairs', [
            'pairs' => $query->paginate(75)->withQueryString(),
            'quotes' => PulsePair::query()->select('quote_asset')->distinct()->orderBy('quote_asset')->pluck('quote_asset'),
            'summary' => [
                'total' => PulsePair::query()->count(),
                'enabled' => PulsePair::query()->where('is_enabled', true)->count(),
                'synced' => PulsePair::query()->whereNotNull('last_synced_at')->count(),
            ],
        ]);
    }

    public function storePair(Request $request, PulseAuditService $audit)
    {
        $data = $this->validatePair($request);
        $data['symbol'] = strtoupper($data['symbol']); $data['base_asset'] = strtoupper($data['base_asset']); $data['quote_asset'] = strtoupper($data['quote_asset']);
        $data['is_enabled'] = $request->boolean('is_enabled');
        $pair = PulsePair::create($data);
        $audit->record('admin.pair_created', $request->user(), 'PulsePair', $pair->id, null, ['symbol' => $pair->symbol], $request);
        return back()->with('success', 'Pulse pair created. Use Exchange Sync to obtain current filters.');
    }

    public function updatePair(Request $request, PulsePair $pair, PulseAuditService $audit)
    {
        $data = $this->validatePair($request, $pair);
        $data['symbol'] = strtoupper($data['symbol']); $data['base_asset'] = strtoupper($data['base_asset']); $data['quote_asset'] = strtoupper($data['quote_asset']);
        $data['is_enabled'] = $request->boolean('is_enabled'); $pair->update($data);
        $audit->record('admin.pair_updated', $request->user(), 'PulsePair', $pair->id, null, ['symbol' => $pair->symbol], $request);
        return back()->with('success', 'Pulse pair updated.');
    }

    public function syncPairs(Request $request, BinanceFuturesService $binance, PulseAuditService $audit)
    {
        try {
            $count = $binance->syncPairsFromExchange('live');
            $audit->record('admin.pairs_synchronized', $request->user(), null, null, 'live', ['count' => $count], $request);
            return back()->with('success', "Synchronized {$count} active Binance USD-M perpetual Futures pairs and current exchange filters.");
        } catch (\Throwable $e) { return back()->withErrors(['pairs' => $e->getMessage()]); }
    }

    public function access(Request $request)
    {
        $now = now();
        $users = User::query()->with(['pulseAccess.plan'])->orderBy('name');
        if ($request->filled('q')) {
            $q = '%'.trim((string) $request->string('q')).'%';
            $users->where(fn ($query) => $query->where('name', 'like', $q)->orWhere('email', 'like', $q));
        }
        if ($request->filled('status')) $users->whereHas('pulseAccess', fn ($q) => $q->where('status', $request->string('status')));
        if ($request->filled('plan')) $users->whereHas('pulseAccess', fn ($q) => $q->where('pulse_plan_id', (int) $request->input('plan')));
        if ($request->string('expiry')->value() === '7') {
            $users->whereHas('pulseAccess', fn ($q) => $q->where('status','active')->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $now->copy()->addDays(7)]));
        } elseif ($request->string('expiry')->value() === '30') {
            $users->whereHas('pulseAccess', fn ($q) => $q->where('status','active')->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $now->copy()->addDays(30)]));
        } elseif ($request->string('expiry')->value() === 'expired') {
            $users->whereHas('pulseAccess', fn ($q) => $q->whereNotNull('ends_at')->where('ends_at','<=',$now));
        } elseif ($request->string('expiry')->value() === 'none') {
            $users->whereHas('pulseAccess', fn ($q) => $q->whereNull('ends_at'));
        }

        $plans = PulsePlan::query()->orderBy('sort_order')->orderBy('name')->get();
        $pulse = UserServiceAccess::query()->where('service','pulse');
        return view('admin.pulse.access', [
            'users' => $users->paginate(40)->withQueryString(),
            'plans' => $plans,
            'capabilityLabels' => PulsePlan::CAPABILITIES,
            'summary' => [
                'active' => (clone $pulse)->where('status','active')->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>',$now))->count(),
                'trial' => (clone $pulse)->where('status','active')->whereIn('pulse_plan_id', $plans->where('is_trial', true)->pluck('id'))->count(),
                'expiring7' => (clone $pulse)->where('status','active')->whereNotNull('ends_at')->whereBetween('ends_at',[$now,$now->copy()->addDays(7)])->count(),
                'expiring30' => (clone $pulse)->where('status','active')->whereNotNull('ends_at')->whereBetween('ends_at',[$now,$now->copy()->addDays(30)])->count(),
                'expired' => (clone $pulse)->whereNotNull('ends_at')->where('ends_at','<=',$now)->count(),
                'unassigned' => User::query()->whereDoesntHave('pulseAccess')->count(),
            ],
        ]);
    }

    public function renewAccess(Request $request, User $user, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate(['days' => ['required','integer','in:7,30,60,90,180,365']]);
        $access = UserServiceAccess::query()->where('user_id',$user->id)->where('service','pulse')->firstOrFail();
        $base = $access->ends_at && $access->ends_at->isFuture() ? $access->ends_at->copy() : now();
        $access->update(['status'=>'active','starts_at'=>$access->starts_at ?: now(),'ends_at'=>$base->addDays((int)$data['days'])]);
        $audit->record('admin.access_renewed', $request->user(), 'UserServiceAccess', $access->id, null, ['target_user_id'=>$user->id,'days'=>(int)$data['days'],'ends_at'=>$access->ends_at?->toIso8601String()], $request);
        $mail->accessUpdated($user, $access->fresh('plan'), 'renewed');
        return back()->with('success', "Pulse access extended for {$user->email}.");
    }

    public function updateAccess(Request $request, User $user, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,active,suspended,expired,revoked'], 'pulse_plan_id' => ['nullable', 'exists:pulse_plans,id'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        // Plans define the maximum feature set. Per-user overrides only restrict
        // individual capabilities and can never grant something the plan excludes.
        $permissions = [];
        foreach (PulsePlan::CAPABILITIES as $key => $label) {
            if ($request->boolean('restriction_'.$key)) {
                $permissions[$key] = false;
            }
        }
        $access = UserServiceAccess::updateOrCreate(['user_id' => $user->id, 'service' => 'pulse'], [
            'status' => $data['status'], 'pulse_plan_id' => $data['pulse_plan_id'] ?? null,
            'approved_by' => $request->user()->id, 'starts_at' => $data['starts_at'] ?? now(), 'ends_at' => $data['ends_at'] ?? null,
            'permissions' => $permissions, 'notes' => $data['notes'] ?? null,
        ]);
        $assignedPlan = $access->pulse_plan_id ? PulsePlan::query()->find($access->pulse_plan_id) : null;
        if ($assignedPlan?->is_trial && ! $access->trial_used_at) {
            $access->update(['trial_used_at' => now()]);
        }
        $audit->record('admin.access_updated', $request->user(), 'UserServiceAccess', $access->id, null, [
            'target_user_id' => $user->id, 'status' => $access->status, 'plan_id' => $access->pulse_plan_id, 'permissions' => $permissions,
        ], $request);
        $mail->accessUpdated($user, $access->fresh('plan'));
        return back()->with('success', "Pulse access updated for {$user->email}.");
    }


    public function memberships(Request $request, PulseMembershipService $membership)
    {
        $openStatuses = ['submitted', 'under_review'];
        $statusFilter = trim((string) $request->input('status', ''));
        $planFilter = $request->filled('plan') ? (int) $request->input('plan') : null;
        $searchTerm = $request->filled('q') ? '%'.trim((string) $request->input('q')).'%' : null;

        $applyCommonFilters = function ($query) use ($planFilter, $searchTerm) {
            if ($planFilter) $query->where('pulse_plan_id', $planFilter);
            if ($searchTerm) {
                $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm));
            }
            return $query;
        };

        $pendingQuery = $applyCommonFilters(PulseMembershipRequest::query()
            ->with(['user', 'plan', 'promotion', 'reviewer'])
            ->whereIn('status', $openStatuses)
            ->latest());

        if ($statusFilter !== '') {
            if (in_array($statusFilter, $openStatuses, true)) $pendingQuery->where('status', $statusFilter);
            else $pendingQuery->whereRaw('1 = 0');
        }

        $historyQuery = $applyCommonFilters(PulseMembershipRequest::query()
            ->with(['user', 'plan', 'promotion', 'reviewer'])
            ->whereNotIn('status', $openStatuses)
            ->latest());

        if ($statusFilter !== '') {
            if (in_array($statusFilter, $openStatuses, true)) $historyQuery->whereRaw('1 = 0');
            else $historyQuery->where('status', $statusFilter);
        }

        $requests = PulseMembershipRequest::query();
        return view('admin.pulse.memberships', [
            'pendingMembershipRequests' => $pendingQuery->limit(50)->get(),
            'membershipRequests' => $historyQuery->paginate(35)->withQueryString(),
            'promotions' => PulsePromotionCode::query()->with(['plan','assignee'])->withCount('redemptions')->latest()->get(),
            'plans' => PulsePlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'commerce' => $membership->settings(),
            'summary' => [
                'open' => (clone $requests)->whereIn('status',['submitted','under_review'])->count(),
                'approved30' => (clone $requests)->where('status','approved')->where('reviewed_at','>=',now()->subDays(30))->count(),
                'rejected30' => (clone $requests)->where('status','rejected')->where('reviewed_at','>=',now()->subDays(30))->count(),
                'confirmedValue30' => (float) (clone $requests)->where('status','approved')->where('reviewed_at','>=',now()->subDays(30))->sum('final_amount'),
                'activePromotions' => PulsePromotionCode::query()->where('is_active',true)->where(fn($q)=>$q->whereNull('valid_until')->orWhere('valid_until','>',now()))->count(),
            ],
        ]);
    }

    public function updateMembershipSettings(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate([
            'membership_requests_enabled' => ['required','in:true,false'],
            'usdt_wallet_address' => ['nullable','string','max:500'],
            'usdt_network' => ['nullable','string','max:80'],
            'usdt_payment_instructions' => ['nullable','string','max:2000'],
            'payment_proof_required' => ['required','in:true,false'],
            'trial_auto_assign_enabled' => ['required','in:true,false'],
            'trial_banner_enabled' => ['required','in:true,false'],
            'trial_duration_days' => ['required','integer','min:1','max:365'],
        ]);
        $definitions = [
            'membership_requests_enabled' => [$data['membership_requests_enabled'] === 'true' ? '1' : '0','boolean','membership','Allow direct USDT Pulse package payment requests.'],
            'usdt_wallet_address' => [trim((string)($data['usdt_wallet_address'] ?? '')),'string','membership','USDT wallet address shown at checkout.'],
            'usdt_network' => [trim((string)($data['usdt_network'] ?? '')),'string','membership','USDT network shown at checkout.'],
            'usdt_payment_instructions' => [trim((string)($data['usdt_payment_instructions'] ?? '')),'string','membership','Payment instructions shown to members.'],
            'payment_proof_required' => [$data['payment_proof_required'] === 'true' ? '1' : '0','boolean','membership','Require payment proof upload with direct USDT package requests.'],
            'trial_auto_assign_enabled' => [$data['trial_auto_assign_enabled'] === 'true' ? '1' : '0','boolean','membership','Automatically apply the active Trial plan to eligible new registrations.'],
            'trial_banner_enabled' => [$data['trial_banner_enabled'] === 'true' ? '1' : '0','boolean','membership','Show Trial availability on the public Pulse page.'],
            'trial_duration_days' => [(string)(int)$data['trial_duration_days'],'integer','membership','Default Trial duration for newly registered users.'],
        ];
        foreach ($definitions as $key => [$value,$type,$group,$description]) {
            PulseSystemSetting::updateOrCreate(['key'=>$key], compact('value','type','group','description'));
        }
        PulseSystemSetting::updateOrCreate(['key'=>'promotion_codes_enabled'], ['value'=>'0','type'=>'boolean','group'=>'membership','description'=>'Promotions are disabled by default in the direct USDT package flow.']);
        $audit->record('admin.membership_settings_updated', $request->user(), null, null, null, ['keys'=>array_keys($definitions)], $request);
        return back()->with('success', 'USDT package payment and Trial settings updated.');
    }

    public function approveMembershipRequest(Request $request, PulseMembershipRequest $membershipRequest, PulseMembershipService $membership, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate([
            'activation_days' => ['nullable','integer','min:1','max:3650'],
            'admin_notes' => ['nullable','string','max:2000'],
        ]);
        if (! $membershipRequest->isOpen()) {
            return back()->withErrors(['membership'=>'This payment request is no longer awaiting review.']);
        }

        $access = $membership->activateMembership(
            $membershipRequest,
            $request->user(),
            isset($data['activation_days']) ? (int)$data['activation_days'] : null,
            trim((string)($data['admin_notes'] ?? '')) ?: null,
        );
        $audit->record('admin.membership_request_approved', $request->user(), 'PulseMembershipRequest', $membershipRequest->id, null, [
            'target_user_id'=>$membershipRequest->user_id,
            'plan_id'=>$membershipRequest->pulse_plan_id,
            'access_id'=>$access->id,
            'amount'=>(float)$membershipRequest->final_amount,
            'currency'=>$membershipRequest->currency,
        ], $request);
        $mail->planActivated($membershipRequest->fresh(['user','plan']));

        return back()->with('success', 'USDT transaction approved and the Pulse package is now active.');
    }

    public function rejectMembershipRequest(Request $request, PulseMembershipRequest $membershipRequest, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate(['admin_notes'=>['required','string','max:2000']]);
        if (! $membershipRequest->isOpen()) return back()->withErrors(['membership'=>'This request is no longer awaiting review.']);
        $membershipRequest->update([
            'status'=>'rejected', 'admin_notes'=>$data['admin_notes'],
            'reviewed_by'=>$request->user()->id, 'reviewed_at'=>now(),
        ]);
        $audit->record('admin.membership_request_rejected', $request->user(), 'PulseMembershipRequest', $membershipRequest->id, null, ['target_user_id'=>$membershipRequest->user_id], $request);
        $mail->planRequestDeclined($membershipRequest->fresh(['user','plan']));
        return back()->with('success', 'Pulse plan request rejected.');
    }

    public function membershipProof(PulseMembershipRequest $membershipRequest)
    {
        abort_unless($membershipRequest->payment_proof_path && Storage::disk('local')->exists($membershipRequest->payment_proof_path), 404);
        return Storage::disk('local')->download($membershipRequest->payment_proof_path);
    }

    public function storePromotion(Request $request)
    {
        return back()->withErrors(['promotion' => 'Promotion codes are disabled in the current direct USDT package flow.']);
    }

    public function updatePromotion(Request $request, PulsePromotionCode $promotion)
    {
        return back()->withErrors(['promotion' => 'Promotion codes are disabled in the current direct USDT package flow.']);
    }

    public function deletePromotion(Request $request, PulsePromotionCode $promotion, PulseAuditService $audit)
    {
        if ($promotion->redemptions()->exists() || $promotion->membershipRequests()->whereIn('status',['submitted','under_review','approved'])->exists()) {
            return back()->withErrors(['promotion'=>'This code has usage history. Disable it instead of deleting it.']);
        }
        $id=$promotion->id; $code=$promotion->code; $promotion->delete();
        $audit->record('admin.promotion_deleted', $request->user(), 'PulsePromotionCode', $id, null, ['code'=>$code], $request);
        return back()->with('success', 'Unused promotion deleted.');
    }

    public function signals(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'symbol' => ['nullable', 'string', 'max:30'],
            'user' => ['nullable', 'string', 'max:160'],
            'direction' => ['nullable', Rule::in(['LONG', 'SHORT', 'NEUTRAL'])],
            'timeframe' => ['nullable', Rule::in(['15m', '4h'])],
            'status' => ['nullable', Rule::in(['active', 'executed', 'expired', 'rejected'])],
            'outcome' => ['nullable', Rule::in(['pending', 'tp', 'sl', 'ambiguous', 'expired_no_entry', 'expired_after_entry'])],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $filters = [
            'symbol' => strtoupper(trim((string) ($data['symbol'] ?? ''))),
            'user' => trim((string) ($data['user'] ?? '')),
            'direction' => strtoupper(trim((string) ($data['direction'] ?? ''))),
            'timeframe' => strtolower(trim((string) ($data['timeframe'] ?? ''))),
            'status' => strtolower(trim((string) ($data['status'] ?? ''))),
            'outcome' => strtolower(trim((string) ($data['outcome'] ?? ''))),
        ];

        $query = PulseSignal::query()
            ->with(['user', 'validation'])
            ->whereBetween('pulse_signals.generated_at', [$from, $to]);
        if ($filters['symbol'] !== '') $query->where('pulse_signals.symbol', 'like', '%'.$filters['symbol'].'%');
        if ($filters['user'] !== '') {
            $term = '%'.$filters['user'].'%';
            $query->whereHas('user', fn ($q) => $q->where('email', 'like', $term)->orWhere('name', 'like', $term));
        }
        if ($filters['status'] !== '') $query->where('pulse_signals.status', $filters['status']);
        if ($filters['direction'] !== '') $query->where('pulse_signals.direction', $filters['direction']);
        if ($filters['timeframe'] !== '') $query->where('pulse_signals.timeframe', $filters['timeframe']);
        if ($filters['outcome'] === 'pending') {
            $query->where(fn ($q) => $q->whereDoesntHave('validation')->orWhereHas('validation', fn ($v) => $v->whereNull('resolved_at')));
        } elseif ($filters['outcome'] !== '') {
            $query->whereHas('validation', fn ($v) => $v->where('outcome', $filters['outcome']));
        }

        $total = (clone $query)->count();
        $wins = (clone $query)->whereHas('validation', fn ($q) => $q->where('outcome', 'tp'))->count();
        $losses = (clone $query)->whereHas('validation', fn ($q) => $q->where('outcome', 'sl'))->count();
        $entries = (clone $query)->whereHas('validation', fn ($q) => $q->whereNotNull('entry_hit_at'))->count();
        $averageConfidence = (clone $query)->whereNotNull('confidence_score')->avg('confidence_score');
        if ($averageConfidence === null) $averageConfidence = (clone $query)->avg('score');
        $ambiguous = (clone $query)->whereHas('validation', fn ($q) => $q->where('outcome', 'ambiguous'))->count();
        $expiredNoEntry = (clone $query)->whereHas('validation', fn ($q) => $q->where('outcome', 'expired_no_entry'))->count();
        $expiredAfterEntry = (clone $query)->whereHas('validation', fn ($q) => $q->where('outcome', 'expired_after_entry'))->count();
        $pending = (clone $query)->where(fn ($q) => $q->whereDoesntHave('validation')->orWhereHas('validation', fn ($v) => $v->whereNull('resolved_at')))->count();
        $signalTrend = (clone $query)->setEagerLoads([])
            ->leftJoin('pulse_signal_validations as report_validation', 'report_validation.signal_id', '=', 'pulse_signals.id')
            ->selectRaw("DATE(pulse_signals.generated_at) as metric_day, COUNT(DISTINCT pulse_signals.id) as signals, SUM(CASE WHEN report_validation.entry_hit_at IS NOT NULL THEN 1 ELSE 0 END) as entries, SUM(CASE WHEN report_validation.outcome = 'tp' THEN 1 ELSE 0 END) as wins, SUM(CASE WHEN report_validation.outcome = 'sl' THEN 1 ELSE 0 END) as losses, SUM(CASE WHEN report_validation.outcome = 'ambiguous' THEN 1 ELSE 0 END) as ambiguous")
            ->groupBy(DB::raw('DATE(pulse_signals.generated_at)'))->orderBy('metric_day')->get()
            ->map(fn ($row) => [
                'label' => Carbon::parse($row->metric_day)->format('d M'),
                'signals' => (int) $row->signals,
                'entries' => (int) $row->entries,
                'wins' => (int) $row->wins,
                'losses' => (int) $row->losses,
                'ambiguous' => (int) $row->ambiguous,
            ])->values();

        return view('admin.pulse.signals', [
            'signals' => $query->latest('pulse_signals.generated_at')->paginate(30)->withQueryString(),
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'signalTrend' => $signalTrend,
            'summary' => [
                'total' => $total,
                'active' => (clone $query)->where('status', 'active')->count(),
                'entries' => $entries,
                'entry_rate' => $total > 0 ? ($entries / $total) * 100 : null,
                'wins' => $wins,
                'losses' => $losses,
                'win_rate' => ($wins + $losses) > 0 ? ($wins / ($wins + $losses)) * 100 : null,
                'pending' => $pending,
                'ambiguous' => $ambiguous,
                'expired_no_entry' => $expiredNoEntry,
                'expired_after_entry' => $expiredAfterEntry,
                'markets' => (clone $query)->distinct()->count('symbol'),
                'average_confidence' => $averageConfidence === null ? null : (float) $averageConfidence,
                'unlocked' => (clone $query)->whereNotNull('unlocked_at')->count(),
                'latest_at' => (clone $query)->max('generated_at'),
            ],
        ]);
    }

    public function updateSignal(Request $request, PulseSignal $signal, PulseAuditService $audit)
    {
        $data = $request->validate(['status' => ['required', 'in:active,executed,expired,rejected'], 'expires_at' => ['nullable', 'date']]);
        $signal->update($data); $audit->record('admin.signal_updated', $request->user(), 'PulseSignal', $signal->id, null, $data, $request);
        return back()->with('success', 'Signal updated.');
    }

    public function trades(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable','date'], 'to' => ['nullable','date','after_or_equal:from'],
            'symbol' => ['nullable','string','max:30'], 'user' => ['nullable','string','max:160'],
            'status' => ['nullable',Rule::in(['submitting','pending','open','protection_failed','closing','closed','failed','cancelled'])],
            'environment' => ['nullable',Rule::in(['testnet','live'])],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $filters = [
            'status' => strtolower(trim((string) ($data['status'] ?? ''))),
            'environment' => strtolower(trim((string) ($data['environment'] ?? ''))),
            'symbol' => strtoupper(trim((string) ($data['symbol'] ?? ''))),
            'user' => trim((string) ($data['user'] ?? '')),
        ];
        $query = PulseTrade::query()->with(['user', 'signal'])->whereBetween('pulse_trades.created_at',[$from,$to]);
        if ($filters['status'] !== '') $query->where('pulse_trades.status', $filters['status']);
        if ($filters['environment'] !== '') $query->where('pulse_trades.environment', $filters['environment']);
        if ($filters['symbol'] !== '') $query->where('pulse_trades.symbol', 'like', '%'.$filters['symbol'].'%');
        if ($filters['user'] !== '') {
            $term='%'.$filters['user'].'%';
            $query->whereHas('user',fn($q)=>$q->where('email','like',$term)->orWhere('name','like',$term));
        }
        $total=(clone $query)->count();
        $closed=(clone $query)->where('pulse_trades.status','closed')->count();
        $profitable=(clone $query)->where('pulse_trades.status','closed')->where('pulse_trades.realized_pnl','>',0)->count();
        $losing=(clone $query)->where('pulse_trades.status','closed')->where('pulse_trades.realized_pnl','<',0)->count();
        $flat=(clone $query)->where('pulse_trades.status','closed')->where('pulse_trades.realized_pnl','=',0)->count();
        $activeStatuses = ['submitting','pending','open','closing','protection_failed'];
        $protectionScope = (clone $query)->whereIn('pulse_trades.status',['open','protection_failed']);
        $protectionEligible = (clone $protectionScope)->count();
        $protected = (clone $protectionScope)->where('pulse_trades.protection_status','confirmed')->count();
        $tradeTrend = (clone $query)->setEagerLoads([])
            ->selectRaw("DATE(pulse_trades.created_at) as metric_day, COUNT(*) as trades, SUM(CASE WHEN pulse_trades.status = 'closed' THEN 1 ELSE 0 END) as closed, SUM(CASE WHEN pulse_trades.status = 'closed' AND pulse_trades.realized_pnl > 0 THEN 1 ELSE 0 END) as profitable, SUM(COALESCE(pulse_trades.realized_pnl,0)) as realized_pnl, SUM(COALESCE(pulse_trades.fees,0)) as fees")
            ->groupBy(DB::raw('DATE(pulse_trades.created_at)'))->orderBy('metric_day')->get()
            ->map(fn ($row) => [
                'label' => Carbon::parse($row->metric_day)->format('d M'),
                'trades' => (int) $row->trades,
                'closed' => (int) $row->closed,
                'profitable' => (int) $row->profitable,
                'realized_pnl' => round((float) $row->realized_pnl, 4),
                'fees' => round((float) $row->fees, 4),
            ])->values();
        $environmentMix = [
            'practice' => (clone $query)->where('pulse_trades.environment','testnet')->count(),
            'live' => (clone $query)->where('pulse_trades.environment','live')->count(),
        ];
        return view('admin.pulse.trades', [
            'trades' => $query->latest('pulse_trades.created_at')->paginate(30)->withQueryString(), 'from'=>$from, 'to'=>$to,
            'filters'=>$filters, 'tradeTrend'=>$tradeTrend, 'environmentMix'=>$environmentMix,
            'summary' => [
                'total'=>$total,
                'open'=>(clone $query)->whereIn('pulse_trades.status',$activeStatuses)->count(),
                'protection_review'=>$protectionEligible-$protected,
                'protection_rate'=>$protectionEligible>0?($protected/$protectionEligible)*100:null,
                'closed'=>$closed,
                'profitable'=>$profitable,
                'losing'=>$losing,
                'flat'=>$flat,
                'profitable_rate'=>$closed>0?($profitable/$closed)*100:null,
                'realized_pnl'=>(float)(clone $query)->sum('pulse_trades.realized_pnl'),
                'unrealized_pnl'=>(float)(clone $query)->whereIn('pulse_trades.status',$activeStatuses)->sum('pulse_trades.unrealized_pnl'),
                'fees'=>(float)(clone $query)->sum('pulse_trades.fees'),
            ],
        ]);
    }

    public function settings()
    {
        return view('admin.pulse.settings', [
            'settings' => PulseSystemSetting::query()->where('group', '!=', 'membership')->orderBy('group')->orderBy('key')->get()->groupBy('group'),
            'plans' => PulsePlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateSettings(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:10000']]);
        foreach ($data['settings'] as $key => $value) {
            $setting = PulseSystemSetting::query()->where('key', $key)->first();
            if ($setting) $setting->update(['value' => $this->normalizeSettingValue($setting->type, $value)]);
        }
        $audit->record('admin.system_settings_updated', $request->user(), null, null, null, ['keys' => array_keys($data['settings'])], $request);
        return back()->with('success', 'Pulse system settings updated. Server-level .env trading gates still take priority.');
    }

    public function broadcastAlert(Request $request, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate([
            'audience' => ['required', 'in:all,active,plan'], 'pulse_plan_id' => ['nullable', 'required_if:audience,plan', 'exists:pulse_plans,id'], 'type' => ['required', 'in:system,market,risk,trade,plan'],
            'title' => ['required', 'string', 'max:160'], 'message' => ['required', 'string', 'max:3000'],
            'severity' => ['required', 'in:info,success,warning,danger'], 'action_url' => ['nullable', 'string', 'max:255'], 'send_email' => ['nullable', 'boolean'],
        ]);
        $users = User::query()->whereHas('pulseAccess', function ($q) use ($data): void {
            $q->where('service', 'pulse');
            if ($data['audience'] === 'active') $q->where('status', 'active');
            if ($data['audience'] === 'plan') $q->where('status', 'active')->where('pulse_plan_id', $data['pulse_plan_id']);
        })->pluck('id');
        foreach ($users->chunk(500) as $chunk) {
            $rows = $chunk->map(fn ($id) => [
                'user_id' => $id, 'type' => $data['type'], 'title' => $data['title'], 'message' => $data['message'],
                'severity' => $data['severity'], 'is_read' => false, 'action_url' => $data['action_url'] ?: null,
                'data' => json_encode(['admin_broadcast' => true]), 'created_at' => now(), 'updated_at' => now(),
            ])->all();
            PulseAlert::query()->insert($rows);

            if ($request->boolean('send_email')) {
                $emailUsers = User::query()->whereIn('id', $chunk->all())->get();
                foreach ($emailUsers as $emailUser) {
                    $alert = new PulseAlert([
                        'user_id' => $emailUser->id, 'type' => $data['type'], 'title' => $data['title'], 'message' => $data['message'],
                        'severity' => $data['severity'], 'action_url' => $data['action_url'] ?: null,
                    ]);
                    $mail->pulseAlert($emailUser, $alert);
                }
            }
        }
        $audit->record('admin.alert_broadcast', $request->user(), null, null, null, ['recipients' => $users->count(), 'title' => $data['title'], 'audience' => $data['audience'], 'plan_id' => $data['pulse_plan_id'] ?? null, 'email_requested' => $request->boolean('send_email')], $request);
        return back()->with('success', "Pulse alert sent to {$users->count()} users.");
    }

    public function logs(Request $request)
    {
        $data=$request->validate([
            'from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from'],'action'=>['nullable','string','max:160'],
            'user'=>['nullable','string','max:160'],'environment'=>['nullable',Rule::in(['testnet','live'])],
        ]);
        $from=isset($data['from'])?Carbon::parse($data['from'])->startOfDay():now()->subDays(7)->startOfDay();
        $to=isset($data['to'])?Carbon::parse($data['to'])->endOfDay():now()->endOfDay();
        $query = PulseAuditLog::query()->with('user')->whereBetween('created_at',[$from,$to]);
        if (!empty($data['action'])) $query->where('action','like','%'.trim($data['action']).'%');
        if (!empty($data['environment'])) $query->where('environment',$data['environment']);
        if (!empty($data['user'])) {
            $term='%'.trim($data['user']).'%';
            $query->whereHas('user',fn($q)=>$q->where('email','like',$term)->orWhere('name','like',$term));
        }
        return view('admin.pulse.logs', [
            'logs'=>$query->latest('created_at')->paginate(75)->withQueryString(),'from'=>$from,'to'=>$to,
            'summary'=>[
                'total'=>(clone $query)->count(),
                'administrators'=>(clone $query)->whereHas('user',fn($q)=>$q->where('role','admin'))->count(),
                'system'=>(clone $query)->whereNull('user_id')->count(),
                'live'=>(clone $query)->where('environment','live')->count(),
                'security'=>(clone $query)->where(fn($q)=>$q->where('action','like','%security%')->orWhere('action','like','%password%')->orWhere('action','like','%login%'))->count(),
            ],
        ]);
    }


    private function validatePromotion(Request $request, ?PulsePromotionCode $promotion = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required','string','max:80','regex:/^[A-Za-z0-9_-]+$/', Rule::unique('pulse_promotion_codes','code')->ignore($promotion?->id)],
            'label' => ['nullable','string','max:120'],
            'type' => ['required','in:coupon,gift_voucher'],
            'discount_type' => ['required','in:percent,fixed,full'],
            'discount_value' => ['required','numeric','min:0','max:1000000'],
            'applicable_plan_id' => ['nullable','exists:pulse_plans,id'],
            'assigned_user_email' => ['nullable','email','max:255'],
            'access_days' => ['nullable','integer','min:1','max:3650'],
            'max_uses' => ['required','integer','min:0','max:1000000'],
            'per_user_limit' => ['required','integer','min:1','max:1000'],
            'valid_from' => ['nullable','date'],
            'valid_until' => ['nullable','date','after:valid_from'],
            'notes' => ['nullable','string','max:2000'],
        ]);
        if ($data['discount_type'] === 'percent' && (float)$data['discount_value'] > 100) {
            validator(['discount_value'=>$data['discount_value']], ['discount_value'=>['max:100']])->validate();
        }
        if ($data['discount_type'] === 'full') $data['discount_value'] = 100;
        return $data;
    }

    private function resolvePromotionAssignee(?string $email): ?int
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') return null;

        $id = User::query()->whereRaw('LOWER(email) = ?', [$email])->value('id');
        if (! $id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'assigned_user_email' => 'No ABS user account matches this email address.',
            ]);
        }

        return (int) $id;
    }

    private function validatePlan(Request $request, ?PulsePlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'], 'monthly_price' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'max:8'],
            'minimum_signal_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'manual_trades_per_day' => ['required', 'integer', 'min:0'], 'auto_trades_per_day' => ['required', 'integer', 'min:0'],
            'max_open_trades' => ['required', 'integer', 'min:0'], 'max_selected_pairs' => ['required', 'integer', 'min:1'],
            'pair_access_mode' => ['required', Rule::in(['all','selected'])], 'pair_ids' => ['nullable','array'], 'pair_ids.*' => ['integer','exists:pulse_pairs,id'],
            'access_days' => ['required', 'integer', 'min:1', 'max:3650'], 'badge' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0'], 'strategy_ids' => ['nullable', 'array'], 'strategy_ids.*' => ['integer', 'exists:pulse_strategies,id'],
            'capabilities' => ['nullable', 'array'], 'capabilities.*' => ['string', Rule::in(array_keys(PulsePlan::CAPABILITIES))],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        validator(['slug' => $data['slug']], ['slug' => [Rule::unique('pulse_plans', 'slug')->ignore($plan?->id)]])->validate();
        unset($data['strategy_ids'], $data['pair_ids']);
        return $data;
    }

    private function planBooleans(Request $request, array $data): array
    {
        $selected = collect($request->input('capabilities', []))
            ->filter(fn ($key) => array_key_exists((string) $key, PulsePlan::CAPABILITIES))
            ->mapWithKeys(fn ($key) => [(string) $key => true]);

        $matrix = [];
        foreach (PulsePlan::CAPABILITIES as $key => $label) {
            $matrix[$key] = $selected->has($key);
        }

        $data['capabilities'] = $matrix;
        $data['allow_testnet_trading'] = $matrix['testnet_trading'] ?? false;
        $data['allow_manual_trading'] = $matrix['manual_trading'] ?? false;
        $data['allow_live_trading'] = $matrix['live_trading'] ?? false;
        $data['allow_auto_trading'] = $matrix['auto_trading'] ?? false;
        $data['allow_mobile_api'] = $matrix['mobile_api'] ?? false;
        $data['is_active'] = $request->boolean('is_active');
        $data['is_trial'] = $request->boolean('is_trial');
        $data['is_public'] = $request->boolean('is_public');
        // V15.1 commerce: direct USDT transfer followed by Admin verification.
        $data['request_enabled'] = $request->boolean('request_enabled') && ! $data['is_trial'];
        $data['requires_payment'] = $request->boolean('requires_payment') && ! $data['is_trial'];
        $data['currency'] = 'USDT';
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }

    private function syncPlanStrategies(PulsePlan $plan, Request $request): void
    {
        $ids = collect($request->input('strategy_ids', []))->mapWithKeys(fn ($id) => [(int) $id => ['is_enabled' => true]])->all();
        $plan->strategies()->sync($ids);
    }

    private function syncPlanPairs(PulsePlan $plan, Request $request): void
    {
        if (($plan->pair_access_mode ?: 'all') === 'all') {
            $plan->pairs()->detach();
            return;
        }
        $ids = collect($request->input('pair_ids', []))->mapWithKeys(fn ($id) => [(int) $id => ['is_enabled' => true]])->all();
        $plan->pairs()->sync($ids);
    }

    private function validateStrategy(Request $request, ?PulseStrategy $strategy = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'], 'timeframe' => ['required', 'in:5m,15m,30m,1h,4h,1d'],
            'weight' => ['required', 'numeric', 'min:0', 'max:10'], 'minimum_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'settings_json' => ['nullable', 'string', 'max:10000'], 'sort_order' => ['required', 'integer', 'min:0'],
        ]);
    }

    private function validatePair(Request $request, ?PulsePair $pair = null): array
    {
        return $request->validate([
            'symbol' => ['required', 'string', 'max:30', Rule::unique('pulse_pairs', 'symbol')->ignore($pair?->id)],
            'base_asset' => ['required', 'string', 'max:20'], 'quote_asset' => ['required', 'string', 'max:20'],
            'sort_order' => ['required', 'integer', 'min:0'], 'price_precision' => ['required', 'integer', 'min:0', 'max:12'],
            'quantity_precision' => ['required', 'integer', 'min:0', 'max:12'], 'tick_size' => ['nullable', 'numeric', 'gte:0'],
            'step_size' => ['nullable', 'numeric', 'gte:0'], 'minimum_quantity' => ['nullable', 'numeric', 'gte:0'],
            'minimum_notional' => ['nullable', 'numeric', 'gte:0'],
        ]);
    }

    private function decodeJson(?string $json): array
    {
        if (blank($json)) return [];
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) abort(422, 'Strategy settings must be valid JSON.');
        return $decoded;
    }

    private function normalizeSettingValue(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false',
            'integer' => (string) (int) $value,
            'float' => (string) (float) $value,
            default => (string) $value,
        };
    }
}
