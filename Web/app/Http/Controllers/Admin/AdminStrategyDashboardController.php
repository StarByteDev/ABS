<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BinanceConnection;
use App\Models\PulseAuditLog;
use App\Models\PulseMarketDataRun;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategy;
use App\Models\PulseStrategyLearningState;
use App\Models\PulseSystemSetting;
use App\Models\PulseTrade;
use App\Services\PulseAuditService;
use App\Services\PulseMarketDataService;
use App\Services\PulseRuntimeCadenceService;
use App\Services\PulseStrategyAnalyticsService;
use App\Services\PulseStrategyCycleService;
use App\Services\PulseStrategyResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminStrategyDashboardController extends Controller
{
    public function index(
        Request $request,
        PulseMarketDataService $market,
        PulseStrategyAnalyticsService $analytics,
        PulseStrategyResearchService $research,
        PulseRuntimeCadenceService $cadence,
        PulseStrategyCycleService $cycle,
    ) {
        $data = $request->validate([
            'range' => ['nullable', Rule::in(['1d','7d','30d','90d','custom'])],
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
            'timeframe' => ['nullable', Rule::in(['15m','4h'])],
            'direction' => ['nullable', Rule::in(['LONG','SHORT'])],
        ]);

        $range = (string) ($data['range'] ?? '7d');
        if ($range === 'custom' && isset($data['from'], $data['to'])) {
            $from = Carbon::parse($data['from'])->startOfDay();
            $to = Carbon::parse($data['to'])->endOfDay();
        } else {
            $to = now()->endOfDay();
            $from = match ($range) {
                '1d' => now()->startOfDay(),
                '30d' => now()->subDays(29)->startOfDay(),
                '90d' => now()->subDays(89)->startOfDay(),
                default => now()->subDays(6)->startOfDay(),
            };
        }

        $filters = [
            'timeframe' => strtolower((string) ($data['timeframe'] ?? '')),
            'direction' => strtoupper((string) ($data['direction'] ?? '')),
        ];

        $validations = collect();
        if (Schema::hasTable('pulse_signal_validations')) {
            $query = PulseSignalValidation::query()->with('signal')
                ->whereNull('user_id')
                ->whereBetween('generated_at', [$from, $to]);
            if ($filters['timeframe'] !== '') $query->where('timeframe', $filters['timeframe']);
            if ($filters['direction'] !== '') $query->where('direction', $filters['direction']);
            $validations = $query->orderBy('generated_at')->get();
        }

        $outcomes = $this->outcomeSummary($validations);
        $strategyRows = $this->strategyRows($validations);
        $dailyTrend = $this->dailyTrend($validations, $from, $to);
        $simulation = $analytics->simulation($from, $to, $filters);
        $profitabilityByStrategy = $analytics->strategyProfitability($from, $to, $filters)->keyBy('strategy_slug');

        $learningByStrategy = collect();
        $learningLastCalculated = null;
        if (Schema::hasTable('pulse_strategy_learning_states')) {
            $learningStates = PulseStrategyLearningState::query()->where('market_regime','ALL')->get();
            $learningLastCalculated = $learningStates->max('calculated_at');
            $learningByStrategy = $learningStates->groupBy('strategy_slug')->map(function (Collection $rows): array {
                $samples = (int) $rows->sum('sample_size');
                $weighted = 0.0;
                foreach ($rows as $state) $weighted += ((float) $state->reliability_score) * max(1, (int) $state->sample_size);
                $denominator = max(1, $rows->sum(fn ($state) => max(1, (int) $state->sample_size)));
                $levels = $rows->pluck('evidence_level')->filter()->all();
                $level = in_array('established', $levels, true) ? 'established' : (in_array('developing', $levels, true) ? 'developing' : 'insufficient');
                return ['samples'=>$samples,'reliability'=>$weighted/$denominator,'evidence_level'=>$level];
            });
        }

        $strategyRows = $strategyRows->map(function (array $row) use ($learningByStrategy, $profitabilityByStrategy): array {
            $learning = $learningByStrategy->get($row['slug'], []);
            $profitability = $profitabilityByStrategy->get($row['slug'], []);
            $decisive = (int) $row['tp'] + (int) $row['sl'];
            $verdict = 'Collecting';
            $verdictKey = 'collecting';
            if ($decisive >= 5) {
                if ((float) $row['net_r'] > 0 && (float) ($row['win_rate'] ?? 0) >= 50) {
                    $verdict = 'Positive evidence'; $verdictKey = 'positive';
                } elseif ((float) $row['net_r'] > 0) {
                    $verdict = 'Watch'; $verdictKey = 'watch';
                } else {
                    $verdict = 'Needs review'; $verdictKey = 'review';
                }
            }
            $row['decisive_trades'] = $decisive;
            $row['learning_samples'] = (int) ($learning['samples'] ?? 0);
            $row['reliability'] = isset($learning['reliability']) ? (float) $learning['reliability'] : null;
            $row['evidence_level'] = (string) ($learning['evidence_level'] ?? 'insufficient');
            $row['model_return_pct'] = isset($profitability['model_return_pct']) ? (float) $profitability['model_return_pct'] : null;
            $row['model_profit_factor'] = isset($profitability['model_profit_factor']) ? (float) $profitability['model_profit_factor'] : null;
            $row['model_expectancy_r'] = isset($profitability['model_expectancy_r']) ? (float) $profitability['model_expectancy_r'] : null;
            $row['verdict'] = $verdict;
            $row['verdict_key'] = $verdictKey;
            return $row;
        })->sortByDesc(function (array $row): float {
            if ((int) $row['decisive_trades'] < 1) return -999999 + (int) $row['signals'];
            return ((float) $row['net_r'] * 10000) + (float) ($row['win_rate'] ?? 0);
        })->values();

        $scanQuery = PulseScannerRun::query()->whereNull('user_id')->whereBetween('created_at', [$from, $to]);
        $systemScans = (clone $scanQuery)->count();
        $completedScans = (clone $scanQuery)->where('status','completed')->count();
        $failedScans = (clone $scanQuery)->where('status','failed')->count();
        $signalsGenerated = (int) (clone $scanQuery)->sum('signals_created');

        $recentRunModels = PulseScannerRun::query()
            ->with(['bestSignal.validation','bestSignal.trades','user'])
            ->whereNull('user_id')->latest('id')->limit(10)->get();
        $recentRunIds = $recentRunModels->pluck('id');
        $auditByRun = collect();
        if (Schema::hasTable('pulse_audit_logs') && $recentRunIds->isNotEmpty()) {
            $auditByRun = PulseAuditLog::query()
                ->whereIn('action', ['scanner.completed','scanner.completed_with_warning'])
                ->where('entity_type','PulseScannerRun')->whereIn('entity_id',$recentRunIds)
                ->orderByDesc('id')->get()->unique('entity_id')->keyBy('entity_id');
        }
        $recentCycles = $recentRunModels->map(function (PulseScannerRun $run) use ($auditByRun): array {
            $row = $this->scanAuditRow($run, $auditByRun->get($run->id));
            $engineMeta = collect((array) ($run->summary ?? []))->first(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'engine_meta');
            $row['market_run_id'] = is_array($engineMeta) ? ($engineMeta['market_run_id'] ?? null) : null;
            $row['market_prices_updated'] = is_array($engineMeta) ? (int) ($engineMeta['market_prices_updated'] ?? 0) : 0;
            $row['cycle_mode'] = is_array($engineMeta) ? (string) ($engineMeta['cycle_mode'] ?? '') : '';
            return $row;
        });

        $actual = $analytics->actualExecution($from, $to);
        $researchSettings = $research->settings();
        $latestResearchRun = $researchSettings['last_run_id'] > 0 ? PulseScannerRun::query()->find($researchSettings['last_run_id']) : null;
        $marketHealth = $market->health();
        $lastMarketRun = Schema::hasTable('pulse_market_data_runs') ? PulseMarketDataRun::query()->latest('id')->first() : null;
        $recentPaperTrades = $validations->sortByDesc('generated_at')->take(12)->values();

        $waitingEntry = $validations->whereNull('entry_hit_at')->whereNull('resolved_at')->count();
        $entryOpen = $validations->whereNotNull('entry_hit_at')->whereNull('resolved_at')->count();
        $learningStateCount = Schema::hasTable('pulse_strategy_learning_states') ? PulseStrategyLearningState::query()->count() : 0;

        $stages = [
            [
                'number'=>1,'key'=>'market','title'=>'Market Data','subtitle'=>'Prices ready',
                'status'=>strtolower((string)($marketHealth['feed_status'] ?? 'offline')) === 'healthy' ? 'ready' : 'attention',
                'primary'=>number_format((int)($marketHealth['latest_price_symbols'] ?? 0)).' markets',
                'secondary'=>isset($marketHealth['price_age_seconds']) ? number_format((int)$marketHealth['price_age_seconds']).'s old' : 'No price data',
                'href'=>route('admin.market-data'),
            ],
            [
                'number'=>2,'key'=>'scan','title'=>'Strategy Scan','subtitle'=>'15-strategy engine',
                'status'=>in_array(strtolower((string)$researchSettings['last_status']),['completed','running'],true) ? 'ready' : (strtolower((string)$researchSettings['last_status'])==='failed'?'attention':'waiting'),
                'primary'=>number_format($systemScans).' cycles',
                'secondary'=>number_format($signalsGenerated).' signals in period',
                'href'=>route('admin.pulse.scan-audit'),
            ],
            [
                'number'=>3,'key'=>'paper','title'=>'Paper Trades','subtitle'=>'Entry monitoring',
                'status'=>$validations->count() > 0 ? 'ready' : 'waiting',
                'primary'=>number_format($outcomes['entries']).' entered',
                'secondary'=>number_format($waitingEntry).' waiting · '.number_format($entryOpen).' open',
                'href'=>'#paper-trades',
            ],
            [
                'number'=>4,'key'=>'results','title'=>'Results','subtitle'=>'TP / SL / void',
                'status'=>($outcomes['tp'] + $outcomes['sl']) > 0 ? 'ready' : 'waiting',
                'primary'=>($outcomes['win_rate'] === null ? '—' : number_format((float)$outcomes['win_rate'],1).'%').' win rate',
                'secondary'=>number_format($outcomes['tp']).' wins · '.number_format($outcomes['sl']).' losses',
                'href'=>'#strategy-performance',
            ],
            [
                'number'=>5,'key'=>'learning','title'=>'Strategy Evidence','subtitle'=>'Win/loss validation',
                'status'=>$learningStateCount > 0 ? 'ready' : 'waiting',
                'primary'=>number_format($strategyRows->count()).' strategies tracked',
                'secondary'=>$learningLastCalculated ? Carbon::parse($learningLastCalculated)->diffForHumans() : 'Waiting for resolved trades',
                'href'=>'#strategy-performance',
            ],
        ];

        $automation = [
            'server_gate' => (bool) config('pulse.allow_automatic_trading', false),
            'admin_gate' => (bool) PulseSystemSetting::value('automatic_trading_enabled', false),
            'active_binance_connections' => Schema::hasTable('binance_connections') ? BinanceConnection::query()->where('is_active', true)->count() : 0,
            'active_trades' => Schema::hasTable('pulse_trades') ? PulseTrade::query()->whereIn('status',['submitting','pending','open','closing','protection_failed'])->count() : 0,
        ];

        return view('admin.pulse.strategy-dashboard', [
            'range'=>$range,'from'=>$from,'to'=>$to,'filters'=>$filters,
            'outcomes'=>$outcomes,'strategyRows'=>$strategyRows,'dailyTrend'=>$dailyTrend,'simulation'=>$simulation,
            'systemScans'=>$systemScans,'completedScans'=>$completedScans,'failedScans'=>$failedScans,'signalsGenerated'=>$signalsGenerated,
            'actual'=>$actual,'research'=>$researchSettings,'cadence'=>$cadence->profile(),'latestResearchRun'=>$latestResearchRun,
            'marketHealth'=>$marketHealth,'lastMarketRun'=>$lastMarketRun,'automation'=>$automation,
            'recentPaperTrades'=>$recentPaperTrades,'recentCycles'=>$recentCycles,'stages'=>$stages,
            'lastCycle'=>$cycle->lastCycle(),'learningLastCalculated'=>$learningLastCalculated,
        ]);
    }

    public function updateEngine(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate([
            'enabled' => ['required','in:true,false'],
            'mode' => ['required', Rule::in(PulseRuntimeCadenceService::ALLOWED_MODES)],
            'interval_seconds' => ['required','integer', Rule::in(PulseRuntimeCadenceService::ALLOWED_SECONDS)],
        ]);

        $enabled = $data['enabled'] === 'true';
        $mode = (string) $data['mode'];
        $interval = (int) $data['interval_seconds'];
        $this->put('strategy_research_enabled', $enabled ? '1' : '0', 'boolean', 'research_engine', 'Enable the Admin-only Pulse strategy research engine.');
        $this->put('pulse_background_mode', $mode, 'string', 'runtime', 'Market/strategy research cycle source: production cron, ABS internal scheduler, or manual only.');
        $this->put('pulse_background_interval_seconds', (string) $interval, 'integer', 'runtime', 'Configured internal background cycle interval. Shared hosting remains minimum 60 seconds.');

        $audit->record('admin.strategy_lab_settings_updated', $request->user(), null, null, null, [
            'enabled'=>$enabled,'mode'=>$mode,'interval_seconds'=>$interval,
        ], $request);

        $profile = app(PulseRuntimeCadenceService::class)->profile();
        $message = match ($mode) {
            'manual' => 'Strategy Lab saved in Manual Only mode. Nothing runs automatically; use Run Full Cycle when you want a test cycle.',
            'internal' => 'ABS Internal Scheduler saved. Keep schedule:work running locally/VPS; effective cadence is '.$profile['effective_seconds'].' seconds.',
            default => 'Production Cron mode saved. The server cron/schedule:run remains the cycle trigger; effective cadence is at least 60 seconds.',
        };
        return back()->with('success', $message);
    }

    public function runNow(Request $request, PulseStrategyCycleService $cycle, PulseAuditService $audit)
    {
        $result = $cycle->run(true);
        $scanId = data_get($result, 'stages.scan.run_id');
        $audit->record('admin.strategy_lab_full_cycle', $request->user(), 'PulseScannerRun', $scanId, null, $result, $request);

        if (($result['status'] ?? '') === 'failed') {
            return back()->withErrors(['strategy_lab' => 'Pulse cycle failed: '.($result['message'] ?? 'Unknown error')]);
        }
        if (($result['status'] ?? '') === 'locked') {
            return back()->with('warning', $result['message']);
        }
        return back()->with('success', $result['message'] ?? 'Full Pulse research cycle completed.');
    }

    public function scanAudit(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
            'status' => ['nullable', Rule::in(['completed','failed','running'])],
            'source' => ['nullable', Rule::in(['system','member'])],
            'outcome' => ['nullable', Rule::in(['tp','sl','ambiguous','expired_no_entry','expired_after_entry','open','no_signal'])],
        ]);

        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->subDays(6)->startOfDay();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->endOfDay();
        $filters = [
            'status' => (string) ($data['status'] ?? ''),
            'source' => (string) ($data['source'] ?? ''),
            'outcome' => (string) ($data['outcome'] ?? ''),
        ];

        $base = PulseScannerRun::query()->whereBetween('started_at', [$from, $to]);
        if ($filters['status'] !== '') $base->where('status', $filters['status']);
        if ($filters['source'] === 'system') $base->whereNull('user_id');
        elseif ($filters['source'] === 'member') $base->whereNotNull('user_id');

        if ($filters['outcome'] === 'no_signal') {
            $base->whereNull('best_signal_id');
        } elseif ($filters['outcome'] === 'open') {
            $base->whereHas('bestSignal.validation', fn ($q) => $q->whereNull('resolved_at'));
        } elseif ($filters['outcome'] !== '') {
            $outcome = $filters['outcome'];
            $base->whereHas('bestSignal.validation', fn ($q) => $q->where('outcome', $outcome));
        }

        $summaryBase = clone $base;
        $evidenceRuns = (clone $summaryBase)->get(['id','best_signal_id','summary']);
        $runIdsForEvidence = $evidenceRuns->pluck('id');
        $bestSignalIds = $evidenceRuns->pluck('best_signal_id');
        $summarySignalIds = $evidenceRuns->flatMap(function (PulseScannerRun $run) {
            $set = collect((array) $run->summary)->first(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'qualified_signal_set');
            return collect((array) data_get($set,'signals',[]))->pluck('signal_id');
        });
        $createdSignalIds = PulseSignal::query()->whereIn('scanner_run_id', $runIdsForEvidence)->pluck('id');
        $signalIdsForEvidence = $bestSignalIds->merge($createdSignalIds)->merge($summarySignalIds)->filter()->unique()->values();

        $validationQuery = Schema::hasTable('pulse_signal_validations')
            ? PulseSignalValidation::query()->whereIn('signal_id', $signalIdsForEvidence)
            : null;
        $tradeQuery = Schema::hasTable('pulse_trades')
            ? PulseTrade::query()->whereIn('signal_id', $signalIdsForEvidence)
            : null;

        $summary = [
            'runs' => (clone $summaryBase)->count(),
            'completed' => (clone $summaryBase)->where('status','completed')->count(),
            'failed' => (clone $summaryBase)->where('status','failed')->count(),
            'system' => (clone $summaryBase)->whereNull('user_id')->count(),
            'member' => (clone $summaryBase)->whereNotNull('user_id')->count(),
            'pairs_scanned' => (int) (clone $summaryBase)->sum('pairs_scanned'),
            'signals_published' => $signalIdsForEvidence->count(),
            'new_signals_created' => (int) (clone $summaryBase)->sum('signals_created'),
            'entries' => $validationQuery ? (clone $validationQuery)->whereNotNull('entry_hit_at')->count() : 0,
            'tp' => $validationQuery ? (clone $validationQuery)->where('outcome','tp')->count() : 0,
            'sl' => $validationQuery ? (clone $validationQuery)->where('outcome','sl')->count() : 0,
            'ambiguous' => $validationQuery ? (clone $validationQuery)->where('outcome','ambiguous')->count() : 0,
            'expired' => $validationQuery ? (clone $validationQuery)->whereIn('outcome',['expired_no_entry','expired_after_entry'])->count() : 0,
            'open' => $validationQuery ? (clone $validationQuery)->whereNull('resolved_at')->count() : 0,
            'trades' => $tradeQuery ? (clone $tradeQuery)->count() : 0,
            'closed_trades' => $tradeQuery ? (clone $tradeQuery)->where('status','closed')->count() : 0,
            'trade_tp' => $tradeQuery ? (clone $tradeQuery)->where('status','closed')->where('close_reason','take_profit')->count() : 0,
            'trade_sl' => $tradeQuery ? (clone $tradeQuery)->where('status','closed')->where('close_reason','stop_loss')->count() : 0,
            'realized_pnl' => $tradeQuery ? (float) (clone $tradeQuery)->where('status','closed')->sum('realized_pnl') : 0.0,
            'qualified_candidates' => 0,
        ];

        if (Schema::hasTable('pulse_audit_logs') && $runIdsForEvidence->isNotEmpty()) {
            PulseAuditLog::query()
                ->where('entity_type','PulseScannerRun')
                ->whereIn('entity_id', $runIdsForEvidence)
                ->whereIn('action',['scanner.completed','scanner.completed_with_warning'])
                ->orderBy('id')
                ->get(['entity_id','context'])
                ->groupBy('entity_id')
                ->each(function (Collection $events) use (&$summary): void {
                    $event = $events->last(fn ($candidate) => array_key_exists('qualified_candidates', (array) $candidate->context)) ?: $events->last();
                    $summary['qualified_candidates'] += (int) data_get($event?->context, 'qualified_candidates', 0);
                });
        }

        $runs = (clone $base)
            ->with(['user:id,name,email','bestSignal.validation','bestSignal.trades'])
            ->orderByDesc('started_at')
            ->paginate(30)
            ->withQueryString();

        $pageIds = $runs->getCollection()->pluck('id');
        $auditByRun = collect();
        if (Schema::hasTable('pulse_audit_logs') && $pageIds->isNotEmpty()) {
            $auditByRun = PulseAuditLog::query()
                ->where('entity_type','PulseScannerRun')
                ->whereIn('entity_id',$pageIds)
                ->whereIn('action',['scanner.completed','scanner.completed_with_warning','scanner.failed'])
                ->orderBy('id')
                ->get()
                ->groupBy('entity_id')
                ->map(fn (Collection $events) => $events->last(fn ($candidate) => array_key_exists('qualified_candidates', (array) $candidate->context)) ?: $events->last());
        }

        $runs->getCollection()->transform(fn (PulseScannerRun $run) => $this->scanAuditRow($run, $auditByRun->get($run->id)));

        return view('admin.pulse.scan-audit', compact('from','to','filters','summary','runs'));
    }

    public function scanAuditShow(PulseScannerRun $scannerRun)
    {
        $scannerRun->loadMissing(['user:id,name,email','signals.validation','signals.trades.user:id,name,email','bestSignal.validation','bestSignal.trades.user:id,name,email']);
        $audit = Schema::hasTable('pulse_audit_logs')
            ? PulseAuditLog::query()
                ->where('entity_type','PulseScannerRun')
                ->where('entity_id',$scannerRun->id)
                ->whereIn('action',['scanner.completed','scanner.completed_with_warning','scanner.failed'])
                ->latest('id')->first()
            : null;

        $summaryRows = collect((array) ($scannerRun->summary ?? []));
        $signalSet = $summaryRows->first(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'qualified_signal_set');
        $signalRefs = collect((array) data_get($signalSet, 'signals', []));
        $signalIds = $signalRefs->pluck('signal_id')->merge($scannerRun->signals->pluck('id'))->push($scannerRun->best_signal_id)->filter()->unique()->values();
        $signalIndex = $signalIds->isEmpty() ? collect() : PulseSignal::query()->with(['validation','trades.user:id,name,email'])->whereIn('id',$signalIds)->get()->keyBy('id');
        $scanSignals = $signalRefs->map(function ($ref) use ($signalIndex, $scannerRun) {
            $signal = $signalIndex->get((int) ($ref['signal_id'] ?? 0));
            if (! $signal) return null;
            return [
                'model' => $signal,
                'rank' => (int) ($ref['rank'] ?? ($signal->id === $scannerRun->best_signal_id ? 1 : 0)),
                'persistence' => (string) ($ref['persistence'] ?? ($signal->scanner_run_id === $scannerRun->id ? 'created' : 'reused')),
            ];
        })->filter()->sortBy(fn ($row) => $row['rank'] ?: 999)->values();
        if ($scanSignals->isEmpty()) {
            $scanSignals = $scannerRun->signals->map(fn ($signal) => ['model'=>$signal,'rank'=>$signal->id===$scannerRun->best_signal_id?1:0,'persistence'=>'created']);
            if ($scannerRun->bestSignal && ! $scanSignals->contains(fn($row)=>(int)$row['model']->id===(int)$scannerRun->bestSignal->id)) {
                $scanSignals->prepend(['model'=>$scannerRun->bestSignal,'rank'=>1,'persistence'=>'reused']);
            }
            $scanSignals = $scanSignals->sortBy(fn($row)=>$row['rank'] ?: 999)->values();
        }

        $row = $this->scanAuditRow($scannerRun, $audit);
        $context = (array) ($audit?->context ?? []);
        $threshold = isset($context['effective_minimum_score']) && is_numeric($context['effective_minimum_score'])
            ? (float) $context['effective_minimum_score']
            : null;
        $marketRows = collect((array) ($scannerRun->summary ?? []))
            ->filter(fn ($item) => is_array($item) && isset($item['symbol']))
            ->map(function (array $item) use ($threshold, $scannerRun): array {
                $direction = strtoupper((string) ($item['direction'] ?? ($item['status'] ?? 'EVALUATED')));
                $score = is_numeric($item['score'] ?? null) ? (float) $item['score'] : null;
                $isBest = $scannerRun->bestSignal
                    && strtoupper((string) $scannerRun->bestSignal->symbol) === strtoupper((string) ($item['symbol'] ?? ''))
                    && strtolower((string) $scannerRun->bestSignal->timeframe) === strtolower((string) ($item['timeframe'] ?? ''))
                    && strtoupper((string) $scannerRun->bestSignal->direction) === $direction;
                $qualified = $isBest || ($threshold !== null && in_array($direction,['LONG','SHORT'],true) && $score !== null && $score >= $threshold);
                $strategies = collect((array) ($item['strategies'] ?? []))
                    ->filter(fn ($strategy) => is_array($strategy) && (float) ($strategy['points'] ?? 0) > 0)
                    ->sortByDesc(fn ($strategy) => (float) ($strategy['points'] ?? 0))
                    ->take(3)
                    ->pluck('name')
                    ->filter()
                    ->values()->all();
                return [
                    'symbol' => strtoupper((string) ($item['symbol'] ?? '—')),
                    'timeframe' => strtoupper((string) ($item['timeframe'] ?? '—')),
                    'direction' => $direction,
                    'score' => $score,
                    'last_price' => is_numeric($item['last_price'] ?? null) ? (float) $item['last_price'] : null,
                    'entry_price' => is_numeric($item['entry_price'] ?? null) ? (float) $item['entry_price'] : null,
                    'stop_loss' => is_numeric($item['stop_loss'] ?? null) ? (float) $item['stop_loss'] : null,
                    'take_profit' => is_numeric($item['take_profit'] ?? null) ? (float) $item['take_profit'] : null,
                    'error' => trim((string) ($item['error'] ?? '')),
                    'qualified' => $qualified,
                    'is_best' => $isBest,
                    'strategies' => $strategies,
                ];
            })->values();

        return view('admin.pulse.scan-audit-show', [
            'scannerRun'=>$scannerRun,
            'row'=>$row,
            'audit'=>$audit,
            'context'=>$context,
            'threshold'=>$threshold,
            'marketRows'=>$marketRows,
            'scanSignals'=>$scanSignals,
        ]);
    }

    private function scanAuditRow(PulseScannerRun $run, ?PulseAuditLog $audit): array
    {
        $summaryRows = collect((array) ($run->summary ?? []));
        $evaluations = $summaryRows->filter(fn ($item) => is_array($item) && isset($item['symbol']));
        $engineMeta = $summaryRows->first(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'engine_meta' && ($item['source'] ?? '') === 'strategy_research_engine');
        $signalSet = $summaryRows->first(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'qualified_signal_set');
        $signalCount = (int) data_get($signalSet,'qualified_count',$run->best_signal_id ? 1 : 0);
        $context = (array) ($audit?->context ?? []);
        $signal = $run->bestSignal;
        $validation = $signal?->validation;
        $trades = $signal?->trades ?? collect();
        $latestTrade = $trades->sortByDesc('created_at')->first();
        $unavailable = $evaluations->filter(fn ($item) => strtoupper((string) ($item['direction'] ?? '')) === 'UNAVAILABLE' || trim((string) ($item['error'] ?? '')) !== '')->count();
        $duration = $run->started_at && $run->completed_at ? max(0, $run->started_at->diffInSeconds($run->completed_at)) : null;

        $outcome = null;
        if ($validation) {
            $outcome = $validation->outcome ?: ($validation->entry_hit_at ? 'entry_open' : 'waiting_entry');
        } elseif ($signal) {
            $outcome = 'validation_pending';
        } else {
            $outcome = 'no_signal';
        }

        return [
            'model' => $run,
            'source' => $run->user_id ? 'Member scan' : ($engineMeta ? 'Background research' : 'System scan'),
            'source_key' => $run->user_id ? 'member' : ($engineMeta ? 'research' : 'system'),
            'duration_seconds' => $duration,
            'markets' => max((int) $run->pairs_scanned, $evaluations->pluck('symbol')->filter()->unique()->count()),
            'evaluations' => (int) ($context['market_timeframes_evaluated'] ?? max(0, $evaluations->count() - $unavailable)),
            'unavailable' => (int) ($context['market_timeframes_unavailable'] ?? $unavailable),
            'qualified_candidates' => (int) ($context['qualified_candidates'] ?? $signalCount),
            'signal_count' => $signalCount,
            'threshold' => isset($context['effective_minimum_score']) ? (float) $context['effective_minimum_score'] : null,
            'signal' => $signal,
            'validation' => $validation,
            'outcome' => $outcome,
            'trade_count' => $trades->count(),
            'closed_trade_count' => $trades->where('status','closed')->count(),
            'latest_trade' => $latestTrade,
            'realized_pnl' => (float) $trades->where('status','closed')->sum('realized_pnl'),
            'audit' => $audit,
        ];
    }

    private function outcomeSummary(Collection $rows): array
    {
        $tp = $rows->where('outcome','tp')->count();
        $sl = $rows->where('outcome','sl')->count();
        $ambiguous = $rows->where('outcome','ambiguous')->count();
        $expiredNoEntry = $rows->where('outcome','expired_no_entry')->count();
        $expiredAfterEntry = $rows->where('outcome','expired_after_entry')->count();
        $open = $rows->whereNull('resolved_at')->count();
        $entries = $rows->whereNotNull('entry_hit_at')->count();
        $decisive = $tp + $sl;
        $void = $ambiguous + $expiredNoEntry + $expiredAfterEntry;
        $grossProfitR = 0.0;
        $grossLossR = (float) $sl;
        foreach ($rows->where('outcome','tp') as $row) $grossProfitR += $this->tpR($row);
        $netR = $grossProfitR - $grossLossR;

        return [
            'signals'=>$rows->count(),
            'entries'=>$entries,
            'tp'=>$tp,
            'sl'=>$sl,
            'ambiguous'=>$ambiguous,
            'expired_no_entry'=>$expiredNoEntry,
            'expired_after_entry'=>$expiredAfterEntry,
            'void'=>$void,
            'open'=>$open,
            'win_rate'=>$decisive > 0 ? ($tp / $decisive) * 100 : null,
            'entry_rate'=>$rows->count() > 0 ? ($entries / $rows->count()) * 100 : null,
            'gross_profit_r'=>$grossProfitR,
            'gross_loss_r'=>$grossLossR,
            'net_r'=>$netR,
            'profit_factor'=>$grossLossR > 0 ? $grossProfitR / $grossLossR : ($grossProfitR > 0 ? INF : null),
            'expectancy_r'=>$decisive > 0 ? $netR / $decisive : null,
        ];
    }

    private function strategyRows(Collection $validations): Collection
    {
        $catalog = Schema::hasTable('pulse_strategies')
            ? PulseStrategy::query()->orderBy('sort_order')->orderBy('name')->get()
            : collect();
        $rows = [];
        foreach ($catalog as $strategy) {
            $rows[$strategy->slug] = [
                'slug'=>$strategy->slug,
                'name'=>$strategy->name,
                'enabled'=>(bool) $strategy->is_enabled,
                'signals'=>0,'entries'=>0,'tp'=>0,'sl'=>0,'void'=>0,'open'=>0,
                'gross_profit_r'=>0.0,'gross_loss_r'=>0.0,
            ];
        }

        foreach ($validations as $validation) {
            $contributors = collect((array) $validation->strategy_snapshot)
                ->filter(function ($item) use ($validation): bool {
                    if (! is_array($item) || isset($item['_meta'])) return false;
                    if ((float) ($item['points'] ?? 0) <= 0) return false;
                    $bias = strtoupper((string) ($item['bias'] ?? 'NEUTRAL'));
                    return in_array($bias, [strtoupper((string) $validation->direction), 'NEUTRAL'], true);
                })
                ->pluck('slug')->filter()->unique()->values();

            foreach ($contributors as $slug) {
                if (! isset($rows[$slug])) {
                    $rows[$slug] = ['slug'=>$slug,'name'=>str($slug)->replace('-',' ')->title()->toString(),'enabled'=>true,'signals'=>0,'entries'=>0,'tp'=>0,'sl'=>0,'void'=>0,'open'=>0,'gross_profit_r'=>0.0,'gross_loss_r'=>0.0];
                }
                $rows[$slug]['signals']++;
                if ($validation->entry_hit_at) $rows[$slug]['entries']++;
                if ($validation->outcome === 'tp') {
                    $rows[$slug]['tp']++;
                    $rows[$slug]['gross_profit_r'] += $this->tpR($validation);
                } elseif ($validation->outcome === 'sl') {
                    $rows[$slug]['sl']++;
                    $rows[$slug]['gross_loss_r'] += 1.0;
                } elseif (in_array($validation->outcome, ['ambiguous','expired_no_entry','expired_after_entry'], true)) {
                    $rows[$slug]['void']++;
                } elseif (! $validation->resolved_at) {
                    $rows[$slug]['open']++;
                }
            }
        }

        return collect(array_values($rows))->map(function (array $row): array {
            $decisive = $row['tp'] + $row['sl'];
            $netR = $row['gross_profit_r'] - $row['gross_loss_r'];
            return $row + [
                'win_rate'=>$decisive > 0 ? ($row['tp'] / $decisive) * 100 : null,
                'net_r'=>$netR,
                'profit_factor'=>$row['gross_loss_r'] > 0 ? $row['gross_profit_r'] / $row['gross_loss_r'] : ($row['gross_profit_r'] > 0 ? INF : null),
                'expectancy_r'=>$decisive > 0 ? $netR / $decisive : null,
            ];
        })->sortByDesc(function (array $row): float {
            return ((float) $row['net_r'] * 1000000) + (int) $row['signals'];
        })->values();
    }

    private function dailyTrend(Collection $validations, Carbon $from, Carbon $to): Collection
    {
        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end) && count($days) < 120) {
            $days[$cursor->toDateString()] = ['date'=>$cursor->toDateString(),'label'=>$cursor->format('d M'),'signals'=>0,'tp'=>0,'sl'=>0,'void'=>0,'net_r'=>0.0];
            $cursor->addDay();
        }

        foreach ($validations as $row) {
            $date = ($row->generated_at ?: $row->created_at)?->toDateString();
            if (! $date || ! isset($days[$date])) continue;
            $days[$date]['signals']++;
            if ($row->outcome === 'tp') { $days[$date]['tp']++; $days[$date]['net_r'] += $this->tpR($row); }
            elseif ($row->outcome === 'sl') { $days[$date]['sl']++; $days[$date]['net_r'] -= 1.0; }
            elseif (in_array($row->outcome, ['ambiguous','expired_no_entry','expired_after_entry'], true)) $days[$date]['void']++;
        }

        $cumulative = 0.0;
        return collect(array_values($days))->map(function (array $row) use (&$cumulative): array {
            $cumulative += $row['net_r'];
            $row['net_r'] = round($row['net_r'], 4);
            $row['cumulative_r'] = round($cumulative, 4);
            return $row;
        });
    }

    private function tpR(PulseSignalValidation $row): float
    {
        $entry = (float) $row->entry_price;
        $stop = (float) $row->stop_loss;
        $risk = abs($entry - $stop);
        if ($risk <= 0) return 0.0;
        $levels = array_values(array_filter(array_map('floatval', (array) $row->take_profit_levels), fn ($v) => $v > 0));
        $target = $levels !== [] ? (float) end($levels) : 0.0;
        if ($target <= 0) return 0.0;
        return abs($target - $entry) / $risk;
    }

    private function put(string $key, string $value, string $type, string $group, string $description): void
    {
        PulseSystemSetting::updateOrCreate(['key'=>$key], compact('value','type','group','description'));
    }
}
