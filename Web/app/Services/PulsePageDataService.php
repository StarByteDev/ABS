<?php

namespace App\Services;

use App\Models\BinanceConnection;
use App\Models\PulsePair;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseStrategy;
use App\Models\PulseTrade;
use App\Models\PulseUserSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PulsePageDataService
{
    private const OPEN_STATUSES = ['submitting', 'pending', 'open', 'closing', 'protection_failed'];
    private const POSITION_STATUSES = ['open', 'closing', 'protection_failed'];

    public function dashboard(User $user, string $period = '30d'): array
    {
        $period = $this->normalizePeriod($period);
        $from = $this->periodStart($period);
        $settings = $this->settings($user);
        $access = $user->pulseAccess()->with('plan')->first();
        $connection = $this->connection($user, $settings);
        $scannerCapabilities = $this->capabilities($user);
        $scannerConnectionReady = $this->connectionReady($connection);

        $allTrades = PulseTrade::query()->where('user_id', $user->id)->with('signal')->latest()->get();
        $periodTrades = $allTrades->filter(fn (PulseTrade $trade): bool => $from === null || ($trade->created_at?->gte($from) ?? false));
        $closedTrades = $periodTrades->where('status', 'closed')->values();
        $activeCommitments = $allTrades->whereIn('status', self::OPEN_STATUSES)->values();
        $openTrades = $allTrades->whereIn('status', self::POSITION_STATUSES)->values();
        $periodSignals = PulseSignal::query()->where('user_id', $user->id)
            ->when($from, fn ($query) => $query->where('generated_at', '>=', $from))
            ->latest('generated_at')->get();

        $realizedPnl = (float) $closedTrades->sum(fn (PulseTrade $trade) => (float) $trade->realized_pnl);
        $unrealizedPnl = (float) $openTrades->sum(fn (PulseTrade $trade) => (float) $trade->unrealized_pnl);
        $netPnl = $realizedPnl + $unrealizedPnl;
        $winningTrades = $closedTrades->filter(fn (PulseTrade $trade) => (float) $trade->realized_pnl > 0);
        $losingTrades = $closedTrades->filter(fn (PulseTrade $trade) => (float) $trade->realized_pnl < 0);
        $grossProfit = (float) $winningTrades->sum(fn (PulseTrade $trade) => (float) $trade->realized_pnl);
        $grossLoss = abs((float) $losingTrades->sum(fn (PulseTrade $trade) => (float) $trade->realized_pnl));
        $periodSignalIds = $periodSignals->pluck('id');
        $actionedSignalIds = $periodTrades->pluck('signal_id')->filter()->unique()->intersect($periodSignalIds)->values();
        $accountEquity = $this->accountEquity($allTrades, $connection);
        $openRiskAmount = (float) $openTrades->sum(fn (PulseTrade $trade) => $this->tradeRiskAmount($trade));
        $maxOpen = $this->maximumOpenPositions($settings, $access?->plan?->max_open_trades);
        $riskUtilization = $this->metaFloat($allTrades, 'portfolio_exposure_percent')
            ?? ($maxOpen > 0 ? min(100, round(($activeCommitments->count() / $maxOpen) * 100, 1)) : 0);
        $openRiskPercent = $accountEquity > 0 ? ($openRiskAmount / $accountEquity) * 100 : 0;
        $dailyRiskPercent = $this->metaFloat($allTrades, 'daily_risk_percent')
            ?? min((float) $settings->risk_per_trade_percent * max(1, $openTrades->count()), (float) $settings->risk_per_trade_percent * max(1, $maxOpen));
        $dailyRiskLimit = $this->metaFloat($allTrades, 'daily_risk_limit_percent') ?? max(3.0, (float) $settings->risk_per_trade_percent * max(1, $maxOpen));

        $riskRewards = $periodSignals->map(fn (PulseSignal $signal) => $this->riskReward($signal))->filter();
        $engagement = $periodSignals->count() > 0 ? ($actionedSignalIds->count() / $periodSignals->count()) * 100 : 0;
        $maximumDrawdown = $this->metaFloat($allTrades, 'max_drawdown_percent') ?? $this->maximumDrawdown($closedTrades, $accountEquity);

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'settings' => $settings,
            'access' => $access,
            'capabilities' => $scannerCapabilities,
            'connection' => $connection,
            'connection_ready' => $scannerConnectionReady,
            'plan_name' => $access?->plan?->name ?? ($user->isAdmin() ? 'Administrator' : 'Pulse Access'),
            'member_since' => $access?->starts_at ?? $user->created_at,
            'net_pnl' => $netPnl,
            'net_pnl_percent' => $this->metaFloat($allTrades, 'net_pnl_percent') ?? ($accountEquity > 0 ? ($netPnl / $accountEquity) * 100 : 0),
            'realized_pnl' => $realizedPnl,
            'unrealized_pnl' => $unrealizedPnl,
            'executed_trades' => $closedTrades->count(),
            'winning_trades' => $winningTrades->count(),
            'losing_trades' => $losingTrades->count(),
            'win_rate' => $closedTrades->count() > 0 ? ($winningTrades->count() / $closedTrades->count()) * 100 : 0,
            'profit_factor' => $grossLoss > 0 ? $grossProfit / $grossLoss : ($grossProfit > 0 ? $grossProfit : 0),
            'signals_received' => $periodSignals->count(),
            'signals_actioned' => $actionedSignalIds->count(),
            'signal_engagement' => $engagement,
            'high_conviction_signals' => $periodSignals->where('score', '>=', 80)->count(),
            'expired_signals' => $periodSignals->where('status', 'expired')->count(),
            'dismissed_signals' => $periodSignals->where('status', 'dismissed')->count(),
            'average_setup_score' => $periodSignals->count() > 0 ? (float) $periodSignals->avg('score') : 0,
            'open_positions' => $openTrades->map(fn (PulseTrade $trade): array => $this->tradeRow($trade))->values(),
            'open_position_count' => $openTrades->count(),
            'maximum_open_positions' => $maxOpen,
            'average_closed_trade' => $closedTrades->count() > 0 ? $realizedPnl / $closedTrades->count() : 0,
            'average_risk_reward' => $riskRewards->count() > 0 ? (float) $riskRewards->avg() : 0,
            'maximum_drawdown' => $maximumDrawdown,
            'best_trade' => $closedTrades->count() > 0 ? (float) $closedTrades->max('realized_pnl') : 0,
            'average_win' => $winningTrades->count() > 0 ? (float) $winningTrades->avg('realized_pnl') : 0,
            'average_loss' => $losingTrades->count() > 0 ? (float) $losingTrades->avg('realized_pnl') : 0,
            'risk_utilization' => $riskUtilization,
            'open_risk_percent' => $openRiskPercent,
            'daily_risk_percent' => $dailyRiskPercent,
            'daily_risk_limit' => $dailyRiskLimit,
            'available_capacity' => max(0, 100 - $riskUtilization),
            'portfolio_exposure' => $riskUtilization >= 70 ? 'High' : ($riskUtilization >= 30 ? 'Moderate' : 'Low'),
            'account_equity' => $accountEquity,
            'performance_points' => $this->performancePoints($closedTrades),
        ];
    }

    public function scanner(User $user, array $filters = []): array
    {
        $settings = $this->settings($user);
        $connection = $this->connection($user, $settings);
        $latestRun = PulseScannerRun::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        $allowedPairs = app(PulsePairAccessService::class)->allowedPairs($user)->values();
        $scanSymbols = $allowedPairs->pluck('symbol')->map(fn ($symbol) => strtoupper((string) $symbol))->values()->all();
        $liveTickers = $this->liveTickerMap($scanSymbols);
        $effectiveThreshold = app(PulseSignalThresholdService::class)->resolve($user, $settings);
        $rows = collect();
        $winner = null;

        if ($latestRun?->best_signal_id) {
            $winner = PulseSignal::query()->where('user_id', $user->id)->whereKey((int) $latestRun->best_signal_id)->first();
        }

        if ($winner) {
            $ticker = $liveTickers->get(strtoupper((string) $winner->symbol), []);
            $currentPrice = is_numeric($ticker['price'] ?? null) ? (float) $ticker['price'] : null;
            $row = $this->signalRow($winner, $currentPrice);
            if (is_numeric($ticker['change_percent'] ?? null)) $row['change_percent'] = (float) $ticker['change_percent'];
            $capabilities = $this->capabilities($user);
            $connectionReady = $this->connectionReady($connection);
            $activeTrade = PulseTrade::query()->where('user_id', $user->id)->where('signal_id', $winner->id)
                ->whereIn('status', self::OPEN_STATUSES)->latest('created_at')->first();
            $row['trade_action'] = $this->signalTradeAction($user, $winner, $settings, $connectionReady, $capabilities, $activeTrade, $currentPrice);
            $rows = collect([$row]);
        }

        $plan = $user->pulsePlan();

        $assessed = (int) ($latestRun?->pairs_scanned ?? 0);
        $packageAvailable = $allowedPairs->count();
        $longCount = $rows->where('direction', 'LONG')->count();
        $shortCount = $rows->where('direction', 'SHORT')->count();

        return [
            'settings' => $settings,
            'capabilities' => $this->capabilities($user),
            'connection' => $connection,
            'connection_ready' => $this->connectionReady($connection),
            'latest_run' => $latestRun,
            'best_signal' => $winner,
            'best_signal_included' => true,
            'per_signal_charge' => 0,
            'pairs_monitored' => $packageAvailable,
            'selected_pairs' => $packageAvailable,
            'package_pairs_available' => $packageAvailable,
            'package_pair_limit' => $packageAvailable,
            'effective_minimum_score' => (float) $effectiveThreshold['score'],
            'minimum_score_source' => (string) $effectiveThreshold['source'],
            'setups_identified' => $winner ? 1 : 0,
            'setup_pairs' => $winner ? 1 : 0,
            'high_conviction' => $winner && (float) $winner->score >= 80 ? 1 : 0,
            'scan_coverage' => $packageAvailable > 0 ? min(100, ($assessed / max(1, $packageAvailable)) * 100) : 0,
            'pairs_assessed' => $assessed,
            'results' => $rows,
            'filters' => ['quote' => 'all', 'direction' => 'all', 'strategy' => 'all', 'min_score' => (float) $effectiveThreshold['score'], 'timeframe' => 'all', 'liquidity' => 'all'],
            'market_bias' => $longCount === $shortCount ? 'Balanced' : ($longCount > $shortCount ? 'Bullish' : 'Defensive'),
            'volatility' => $rows->contains(fn (array $row): bool => ($row['volatility'] ?? '') === 'Elevated') ? 'Elevated' : 'Moderate',
            'liquidity' => 'Admin universe',
            'btc_dominance' => null,
            'data_freshness' => $latestRun?->completed_at ? 'Current' : 'Awaiting scan',
            'insufficient_data' => 0,
            'quote_assets' => $allowedPairs->pluck('quote_asset')->filter()->unique()->sort()->values(),
            'strategy_options' => collect(),
        ];
    }

    public function signals(User $user, array $filters = []): array
    {
        $settings = $this->settings($user);
        $connection = $this->connection($user, $settings);
        $base = PulseSignal::query()->where('user_id', $user->id);
        $query = (clone $base)->latest('generated_at');
        $status = strtolower((string) ($filters['status'] ?? 'active'));
        if ($status === 'history') $query->where('status', '!=', 'active');
        elseif ($status !== '' && $status !== 'all') $query->where('status', $status);
        if (! empty($filters['direction']) && strtolower((string) $filters['direction']) !== 'all') $query->where('direction', strtoupper((string) $filters['direction']));
        if (! empty($filters['symbol']) && strtolower((string) $filters['symbol']) !== 'all') $query->where('symbol', strtoupper((string) $filters['symbol']));
        if (! empty($filters['timeframe']) && strtolower((string) $filters['timeframe']) !== 'all') $query->where('timeframe', (string) $filters['timeframe']);
        $effectiveSignalThreshold = app(PulseSignalThresholdService::class)->resolve($user, $settings);
        $signalMinimumScore = array_key_exists('min_score', $filters) && is_numeric($filters['min_score'])
            ? max(0, min(100, (float) $filters['min_score']))
            : (float) $effectiveSignalThreshold['score'];
        $query->where('score', '>=', $signalMinimumScore);

        $signals = $query->limit(100)->get();
        $strategyFilter = (string) ($filters['strategy'] ?? 'all');
        if ($strategyFilter !== '' && strtolower($strategyFilter) !== 'all') {
            $signals = $signals->filter(fn (PulseSignal $signal): bool => $this->signalUsesStrategy($signal, $strategyFilter))->values();
        }
        $selected = null;
        if (! empty($filters['selected'])) {
            $selected = (clone $base)->find((int) $filters['selected']);
        }
        $selected ??= $signals->first();
        $signalSymbols = $signals->pluck('symbol')->when($selected, fn (Collection $symbols) => $symbols->push($selected->symbol))->filter()->unique()->values()->all();
        $liveSignalTickers = $this->liveTickerMap($signalSymbols);
        $active = (clone $base)->where('status', 'active')
            ->where(fn ($activeQuery) => $activeQuery->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();
        $from = now()->subDays(30)->startOfDay();
        $activitySignals = (clone $base)->where('generated_at', '>=', $from)->get();
        $actioned = PulseTrade::query()->where('user_id', $user->id)->where('created_at', '>=', $from)
            ->pluck('signal_id')->filter()->unique()->intersect($activitySignals->pluck('id'))->values();

        $capabilities = $this->capabilities($user);
        $connectionReady = $this->connectionReady($connection);
        $lastScanAt = PulseScannerRun::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->value('completed_at');
        $activeTradesBySignal = PulseTrade::query()
            ->where('user_id', $user->id)
            ->whereNotNull('signal_id')
            ->whereIn('status', self::OPEN_STATUSES)
            ->latest('created_at')
            ->get()
            ->unique('signal_id')
            ->keyBy('signal_id');

        $signalRows = $signals->map(function (PulseSignal $signal) use ($user, $settings, $connectionReady, $capabilities, $activeTradesBySignal, $liveSignalTickers): array {
            $activeTrade = $activeTradesBySignal->get($signal->id);
            $ticker = $liveSignalTickers->get(strtoupper((string) $signal->symbol), []);
            $currentPrice = is_numeric($ticker['price'] ?? null) ? (float) $ticker['price'] : null;
            $row = $this->signalRow($signal, $currentPrice);
            if (is_numeric($ticker['change_percent'] ?? null)) $row['change_percent'] = (float) $ticker['change_percent'];
            return array_merge(
                $row,
                ['trade_action' => $this->signalTradeAction($user, $signal, $settings, $connectionReady, $capabilities, $activeTrade, $currentPrice)],
            );
        })->values();
        $selectedActiveTrade = $selected ? $activeTradesBySignal->get($selected->id) : null;
        $selectedTicker = $selected ? $liveSignalTickers->get(strtoupper((string) $selected->symbol), []) : [];
        $selectedCurrentPrice = is_numeric($selectedTicker['price'] ?? null) ? (float) $selectedTicker['price'] : null;
        $selectedDetail = $selected ? array_merge(
            $this->signalDetail($selected),
            ['last_price' => $selectedCurrentPrice ?: (float) data_get($this->signalMeta($selected), 'last_price', $selected->entry_price)],
            ['trade_action' => $this->signalTradeAction($user, $selected, $settings, $connectionReady, $capabilities, $selectedActiveTrade, $selectedCurrentPrice)],
        ) : null;

        return [
            'settings' => $settings,
            'capabilities' => $capabilities,
            'connection' => $connection,
            'connection_ready' => $connectionReady,
            'signals' => $signalRows,
            'selected_signal' => $selectedDetail,
            'last_scan_at' => $lastScanAt ? Carbon::parse($lastScanAt) : null,
            'active_count' => $active->count(),
            'active_pairs' => $active->pluck('symbol')->unique()->count(),
            'high_conviction_count' => $active->where('score', '>=', 80)->count(),
            'long_count' => $active->where('direction', 'LONG')->count(),
            'short_count' => $active->where('direction', 'SHORT')->count(),
            'engagement' => $activitySignals->count() > 0 ? ($actioned->count() / $activitySignals->count()) * 100 : 0,
            'activity' => [
                'received' => $activitySignals->count(),
                'actioned' => $actioned->count(),
                'expired' => $activitySignals->where('status', 'expired')->count(),
                'dismissed' => $activitySignals->where('status', 'dismissed')->count(),
                'average_score' => $activitySignals->count() > 0 ? (float) $activitySignals->avg('score') : 0,
            ],
            'symbols' => (clone $base)->select('symbol')->distinct()->orderBy('symbol')->pluck('symbol'),
            'timeframes' => (clone $base)->select('timeframe')->distinct()->orderBy('timeframe')->pluck('timeframe'),
            'strategy_options' => $this->strategyOptions($user),
            'effective_minimum_score' => (float) $effectiveSignalThreshold['score'],
            'minimum_score_source' => (string) $effectiveSignalThreshold['source'],
            'filters' => ['min_score' => $signalMinimumScore],
        ];
    }

    public function strategies(User $user, string $period = '30d'): array
    {
        $period = $this->normalizePeriod($period);
        $from = $this->periodStart($period);
        $settings = $this->settings($user);
        $connection = $this->connection($user, $settings);
        $signals = PulseSignal::query()->where('user_id', $user->id)
            ->when($from, fn ($query) => $query->where('generated_at', '>=', $from))
            ->get();
        $trades = PulseTrade::query()->where('user_id', $user->id)->with('signal')
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->get();
        $actionedSignalIds = $trades->pluck('signal_id')->filter()->unique();

        $families = collect($this->strategyFamilies())->map(function (array $family) use ($signals, $trades, $actionedSignalIds): array {
            $familySignals = $signals->filter(fn (PulseSignal $signal): bool => $this->strategyFamily($signal) === $family['name']);
            $signalIds = $familySignals->pluck('id');
            $familyTrades = $trades->filter(fn (PulseTrade $trade): bool => $trade->signal_id && $signalIds->contains($trade->signal_id));
            $closed = $familyTrades->where('status', 'closed');
            $open = $familyTrades->whereIn('status', self::POSITION_STATUSES);
            $actioned = $actionedSignalIds->intersect($signalIds)->count();
            return array_merge($family, [
                'pairs' => $familySignals->pluck('symbol')->unique()->count(),
                'signals' => $familySignals->count(),
                'actioned' => $actioned,
                'average_score' => $familySignals->count() > 0 ? (float) $familySignals->avg('score') : 0,
                'open_positions' => $open->count(),
                'realized_pnl' => (float) $closed->sum(fn (PulseTrade $trade) => (float) $trade->realized_pnl),
            ]);
        })->values();

        $plan = $user->pulsePlan();
        $includedIds = $plan ? $plan->strategies()->wherePivot('is_enabled', true)->pluck('pulse_strategies.id')->all() : [];
        $strategyCatalog = PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get()->map(function (PulseStrategy $strategy) use ($signals, $trades, $actionedSignalIds, $includedIds, $plan): array {
            $strategySignals = $signals->filter(function (PulseSignal $signal) use ($strategy): bool {
                return collect($signal->strategy_breakdown ?? [])->contains(function ($item) use ($strategy) {
                    return (strtolower((string) data_get($item, 'slug', '')) === strtolower($strategy->slug)
                        || strtolower((string) data_get($item, 'name', '')) === strtolower($strategy->name))
                        && (float) data_get($item, 'points', 0) > 0;
                });
            });
            $signalIds = $strategySignals->pluck('id');
            $strategyTrades = $trades->filter(fn (PulseTrade $trade): bool => $trade->signal_id && $signalIds->contains($trade->signal_id));
            return [
                'id' => $strategy->id,
                'name' => $strategy->name,
                'slug' => $strategy->slug,
                'description' => $strategy->description,
                'timeframe' => $strategy->timeframe,
                'minimum_score' => (float) $strategy->minimum_score,
                'weight' => (float) $strategy->weight,
                'sort_order' => (int) $strategy->sort_order,
                'included' => $plan ? in_array($strategy->id, $includedIds, true) : true,
                'signals' => $strategySignals->count(),
                'actioned' => $actionedSignalIds->intersect($signalIds)->count(),
                'average_score' => $strategySignals->count() ? (float) $strategySignals->avg('score') : 0,
                'realized_pnl' => (float) $strategyTrades->where('status', 'closed')->sum(fn (PulseTrade $trade) => (float) $trade->realized_pnl),
            ];
        })->values();

        $realized = (float) $families->sum('realized_pnl');
        return [
            'period' => $period,
            'settings' => $settings,
            'capabilities' => $this->capabilities($user),
            'connection' => $connection,
            'connection_ready' => $this->connectionReady($connection),
            'families' => $families,
            'strategy_catalog' => $strategyCatalog,
            'total_strategies' => $strategyCatalog->count(),
            'active_strategies' => $strategyCatalog->where('included', true)->count(),
            'signals_generated' => $signals->count(),
            'signals_actioned' => $actionedSignalIds->intersect($signals->pluck('id'))->count(),
            'engagement' => $signals->count() > 0 ? ($actionedSignalIds->intersect($signals->pluck('id'))->count() / $signals->count()) * 100 : 0,
            'average_setup_score' => $signals->count() > 0 ? (float) $signals->avg('score') : 0,
            'realized_pnl' => $realized,
            'closed_trades' => $trades->where('status', 'closed')->count(),
            'maximum_open_positions' => $this->maximumOpenPositions($settings, $user->pulsePlan()?->max_open_trades),
            'last_evaluation' => PulseScannerRun::query()->where('user_id', $user->id)->latest()->first()?->completed_at,
        ];
    }

    public function execution(User $user, ?int $signalId = null): array
    {
        $settings = $this->settings($user);
        $access = $user->pulseAccess()->with('plan')->first();
        $connection = $this->connection($user, $settings);
        $signalQuery = PulseSignal::query()->where('user_id', $user->id)->where('status', 'active')
            ->whereIn('direction', ['LONG', 'SHORT'])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $signal = $signalId ? (clone $signalQuery)->find($signalId) : null;
        $signal ??= $signalQuery->latest('generated_at')->first();

        $allTrades = PulseTrade::query()->where('user_id', $user->id)->with('signal')->latest()->get();
        $activeCommitments = $allTrades->whereIn('status', self::OPEN_STATUSES)->values();
        $openTrades = $allTrades->whereIn('status', self::POSITION_STATUSES)->values();
        $todayTrades = $allTrades->filter(fn (PulseTrade $trade): bool => $trade->created_at?->isToday() ?? false);
        $filledToday = $todayTrades->whereIn('status', ['open', 'closed', 'closing', 'protection_failed'])->count();
        $maxOpen = $this->maximumOpenPositions($settings, $access?->plan?->max_open_trades);
        $positionLimitPassed = $maxOpen > 0 && $activeCommitments->count() < $maxOpen;
        $connectionPassed = $this->connectionReady($connection);
        $accessService = app(PulseAccessService::class);
        $environmentAllowed = $settings->environment === 'testnet'
            ? $accessService->systemEnabled('testnet_trading_enabled', true) && $accessService->allows($user, 'testnet_trading', false)
            : config('pulse.allow_live_trading', false)
                && $accessService->systemEnabled('live_trading_enabled', false)
                && $accessService->allows($user, 'live_trading', false);
        $environmentPassed = $accessService->allows($user, 'manual_trading', false)
            && $accessService->systemEnabled('execution_enabled', true)
            && ! $accessService->systemEnabled('emergency_stop_all', false)
            && ! $settings->emergency_stop
            && $environmentAllowed;

        $meta = $signal ? $this->signalMeta($signal) : [];
        $quantity = (float) ($meta['quantity'] ?? $settings->fixed_quantity ?? 0);
        $limitPrice = (float) ($meta['limit_price'] ?? $signal?->entry_price ?? 0);
        if ($quantity <= 0 && $limitPrice > 0 && (float) $settings->fixed_notional > 0) {
            $quantity = (float) $settings->fixed_notional / $limitPrice;
        }
        $leverage = max(1, (int) ($meta['leverage'] ?? $settings->default_leverage ?? 1));
        $stopLoss = (float) ($signal?->stop_loss ?? 0);
        $takeProfit = (float) ($signal?->take_profit ?? 0);
        $notional = $limitPrice * $quantity;
        $requiredMargin = $leverage > 0 ? $notional / $leverage : $notional;
        $estimatedRisk = abs($limitPrice - $stopLoss) * $quantity;
        $potentialReward = abs($takeProfit - $limitPrice) * $quantity;
        $accountEquity = $this->accountEquity($allTrades, $connection);
        $availableBalance = $this->metaFloat($allTrades, 'available_balance')
            ?? (float) data_get($connection?->permissions, 'available_balance', $accountEquity);
        $riskPercent = $accountEquity > 0 ? ($estimatedRisk / $accountEquity) * 100 : 0;
        $riskReward = $estimatedRisk > 0 ? $potentialReward / $estimatedRisk : 0;
        $ticketParametersPassed = $signal !== null
            && $quantity > 0
            && $limitPrice > 0
            && $stopLoss > 0
            && $takeProfit > 0
            && ($signal->direction === 'LONG'
                ? $stopLoss < $limitPrice && $takeProfit > $limitPrice
                : $stopLoss > $limitPrice && $takeProfit < $limitPrice);
        $riskPassed = $ticketParametersPassed && $accountEquity > 0 && $riskPercent <= (float) $settings->risk_per_trade_percent;
        $marginPassed = $availableBalance > 0 && $availableBalance >= $requiredMargin;
        $manualDailyLimit = (int) ($access?->plan?->manual_trades_per_day ?: 0);
        $manualUsedToday = $todayTrades->filter(fn (PulseTrade $trade): bool => ! (bool) data_get($trade->meta, 'automatic', false))->count();
        $manualQuotaPassed = $manualDailyLimit <= 0 || $manualUsedToday < $manualDailyLimit;
        $dailyLossLimit = abs((float) $settings->daily_loss_limit);
        $todayRealizedPnl = (float) $allTrades
            ->filter(fn (PulseTrade $trade): bool => $trade->status === 'closed' && ($trade->closed_at?->isToday() ?? false))
            ->sum('realized_pnl');
        $dailyLossPassed = $dailyLossLimit <= 0 || $todayRealizedPnl > -$dailyLossLimit;
        $checks = [
            ['label' => 'Exchange Connection', 'passed' => $connectionPassed, 'value' => $connectionPassed ? 'Passed' : 'Blocked'],
            ['label' => ucfirst($settings->environment).' Mode', 'passed' => $environmentPassed, 'value' => $environmentPassed ? 'Passed' : 'Blocked'],
            ['label' => 'Risk per Trade', 'passed' => $riskPassed, 'value' => $riskPassed ? 'Passed' : 'Blocked'],
            ['label' => 'Margin Availability', 'passed' => $marginPassed, 'value' => $marginPassed ? 'Passed' : 'Blocked'],
            ['label' => 'Open Position Limit', 'passed' => $positionLimitPassed, 'value' => $positionLimitPassed ? 'Passed' : 'Blocked · '.$activeCommitments->count().' / '.$maxOpen],
        ];
        if (! $ticketParametersPassed) $checks[] = ['label' => 'Ticket Parameters', 'passed' => false, 'value' => $signal ? 'Blocked · Review values' : 'Blocked · Select signal'];
        if (! $manualQuotaPassed) $checks[] = ['label' => 'Daily Trade Limit', 'passed' => false, 'value' => 'Blocked · '.$manualUsedToday.' / '.$manualDailyLimit];
        if (! $dailyLossPassed) $checks[] = ['label' => 'Daily Loss Limit', 'passed' => false, 'value' => 'Blocked'];

        return [
            'settings' => $settings,
            'access' => $access,
            'capabilities' => $this->capabilities($user),
            'connection' => $connection,
            'connection_ready' => $connectionPassed,
            'selected_signal' => $signal ? $this->signalDetail($signal) : null,
            'pending_tickets' => $signal ? 1 : 0,
            'orders_today' => $todayTrades->count(),
            'filled_today' => $filledToday,
            'blocked_today' => $todayTrades->whereIn('status', ['failed', 'rejected'])->count(),
            'fill_rate' => $todayTrades->count() > 0 ? ($filledToday / $todayTrades->count()) * 100 : 0,
            'open_positions' => $openTrades->count(),
            'position_capacity_used' => $activeCommitments->count(),
            'maximum_open_positions' => $maxOpen,
            'position_limit_reached' => ! $positionLimitPassed,
            'checks' => $checks,
            'passed_checks' => collect($checks)->where('passed', true)->count(),
            'blocked_checks' => collect($checks)->where('passed', false)->count(),
            'execution_ready' => collect($checks)->every(fn (array $check): bool => $check['passed']),
            'ticket' => [
                'order_type' => strtoupper((string) ($meta['order_type'] ?? 'LIMIT')),
                'direction' => $signal?->direction ?? 'LONG',
                'limit_price' => $limitPrice,
                'quantity' => $quantity,
                'leverage' => $leverage,
                'time_in_force' => (string) ($meta['time_in_force'] ?? 'GTC'),
                'stop_loss' => $stopLoss,
                'take_profit' => $takeProfit,
                'reduce_only' => false,
                'reduce_only_editable' => false,
                'client_reference' => (string) ($meta['client_reference'] ?? substr('PULSE-'.str_replace(['USDT','USDC'], '', $signal?->symbol ?? 'ORDER').'-'.($signal?->id ?? 'X').'-'.now()->format('ymdHis'), 0, 36)),
            ],
            'calculation' => [
                'notional_value' => $notional,
                'required_margin' => $requiredMargin,
                'estimated_risk' => $estimatedRisk,
                'potential_reward' => $potentialReward,
                'risk_reward' => $riskReward,
                'configured_risk' => $riskPercent,
                'available_balance' => $availableBalance,
            ],
            'account' => [
                'equity' => $accountEquity,
                'current_exposure' => $this->metaFloat($allTrades, 'portfolio_exposure_percent') ?? ($maxOpen > 0 ? ($openTrades->count() / $maxOpen) * 100 : 0),
                'open_risk' => $accountEquity > 0 ? ($openTrades->sum(fn (PulseTrade $trade) => $this->tradeRiskAmount($trade)) / $accountEquity) * 100 : 0,
                'daily_risk' => $this->metaFloat($allTrades, 'daily_risk_percent') ?? (float) $settings->risk_per_trade_percent,
                'daily_risk_limit' => $this->metaFloat($allTrades, 'daily_risk_limit_percent') ?? max(3.0, (float) $settings->risk_per_trade_percent * max(1, $maxOpen)),
                'available_capacity' => max(0, 100 - ($this->metaFloat($allTrades, 'portfolio_exposure_percent') ?? ($maxOpen > 0 ? ($activeCommitments->count() / $maxOpen) * 100 : 0))),
            ],
            'recent_orders' => $allTrades->where('environment', $settings->environment)->take(3)->map(fn (PulseTrade $trade): array => $this->tradeRow($trade))->values(),
        ];
    }

    public function dismissSignal(User $user, PulseSignal $signal): PulseSignal
    {
        abort_unless((int) $signal->user_id === (int) $user->id, 403);
        if ($signal->status === 'active') $signal->update(['status' => 'dismissed']);
        return $signal->fresh();
    }

    private function settings(User $user): PulseUserSetting
    {
        return PulseUserSetting::firstOrCreate(['user_id' => $user->id], [
            'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false,
            'emergency_stop' => false, 'default_leverage' => 3, 'margin_type' => 'ISOLATED',
            'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1, 'sizing_mode' => 'fixed_notional',
            'fixed_notional' => 25, 'minimum_signal_score' => app(PulseSignalThresholdService::class)->packageDefault($user), 'default_order_type' => 'MARKET',
            'take_profit_percent' => 2, 'stop_loss_percent' => 1, 'daily_loss_limit' => 0,
            'max_open_positions' => 2, 'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
            'notification_preferences' => [],
        ]);
    }

    private function capabilities(User $user): array
    {
        return app(PulseAccessService::class)->capabilityMatrix($user);
    }

    private function connection(User $user, PulseUserSetting $settings): ?BinanceConnection
    {
        return BinanceConnection::query()->where('user_id', $user->id)->where('environment', $settings->environment)->first();
    }

    private function connectionReady(?BinanceConnection $connection): bool
    {
        return (bool) ($connection?->is_active && $connection?->last_tested_at && ! $connection?->last_error && data_get($connection?->permissions, 'can_trade', false));
    }

    private function maximumOpenPositions(PulseUserSetting $settings, mixed $planMaximum): int
    {
        $configured = max(1, (int) ($settings->max_open_positions ?: 1));
        $plan = (int) $planMaximum;
        return $plan > 0 ? min($configured, $plan) : $configured;
    }

    private function accountEquity(Collection $trades, ?BinanceConnection $connection): float
    {
        return $this->metaFloat($trades, 'account_equity')
            ?? (float) data_get($connection?->permissions, 'account_equity', 0);
    }

    private function metaFloat(Collection $trades, string $key): ?float
    {
        foreach ($trades as $trade) {
            $value = data_get($trade->meta, $key);
            if (is_numeric($value)) return (float) $value;
        }
        return null;
    }

    private function tradeRiskAmount(PulseTrade $trade): float
    {
        $entry = (float) $trade->entry_price;
        $stop = (float) $trade->stop_loss;
        $quantity = (float) $trade->quantity;
        return $entry > 0 && $stop > 0 && $quantity > 0 ? abs($entry - $stop) * $quantity : 0;
    }

    private function signalMetaFloat(Collection $signals, string $key): ?float
    {
        foreach ($signals as $signal) {
            $value = data_get($this->signalMeta($signal), $key);
            if (is_numeric($value)) return (float) $value;
        }
        return null;
    }

    private function tradeRow(PulseTrade $trade): array
    {
        return [
            'id' => $trade->id,
            'signal_id' => $trade->signal_id,
            'symbol' => $trade->symbol,
            'pair' => $this->pair($trade->symbol),
            'side' => match (strtoupper((string) $trade->side)) {
                'BUY' => 'LONG',
                'SELL' => 'SHORT',
                default => strtoupper((string) $trade->side),
            },
            'environment' => $trade->environment,
            'order_type' => ucfirst(strtolower((string) $trade->order_type)),
            'leverage' => (int) $trade->leverage,
            'quantity' => (float) $trade->quantity,
            'entry_price' => (float) $trade->entry_price,
            'current_price' => (float) $trade->current_price,
            'stop_loss' => (float) $trade->stop_loss,
            'take_profit' => (float) $trade->take_profit,
            'realized_pnl' => (float) $trade->realized_pnl,
            'unrealized_pnl' => (float) $trade->unrealized_pnl,
            'status' => $trade->status,
            'protection_status' => $trade->protection_status,
            'reference' => (string) (data_get($trade->meta, 'client_reference') ?: $trade->exchange_order_id ?: '—'),
            'opened_at' => $trade->opened_at,
            'created_at' => $trade->created_at,
        ];
    }

    private function signalRow(PulseSignal $signal, ?float $currentPrice = null): array
    {
        $meta = $this->signalMeta($signal);
        $entry = (float) $signal->entry_price;
        $entryLow = (float) ($meta['entry_low'] ?? ($entry > 0 ? $entry * .997 : 0));
        $entryHigh = (float) ($meta['entry_high'] ?? ($entry > 0 ? $entry * 1.003 : 0));
        $score = (float) $signal->score;
        $status = $score >= 80 ? 'High-Conviction' : (in_array($signal->direction, ['LONG', 'SHORT'], true) ? 'Qualified' : 'Watching');
        return [
            'id' => $signal->id,
            'actionable' => $signal->isActionable(),
            'symbol' => $signal->symbol,
            'pair' => $this->pair($signal->symbol),
            'strategy' => $this->primaryStrategyName($signal),
            'strategies' => $this->qualifiedStrategyNames($signal),
            'direction' => strtoupper((string) $signal->direction),
            'score' => $score,
            'entry_price' => $entry,
            'entry_low' => $entryLow,
            'entry_high' => $entryHigh,
            'stop_loss' => (float) $signal->stop_loss,
            'take_profit' => (float) $signal->take_profit,
            'risk_reward' => $this->riskReward($signal),
            'timeframe' => strtoupper((string) $signal->timeframe),
            'expires_at' => $signal->expires_at,
            'status' => $status,
            'record_status' => $signal->status,
            'last_price' => $currentPrice !== null && $currentPrice > 0 ? $currentPrice : (float) ($meta['last_price'] ?? $entry),
            'quantity' => isset($meta['quantity']) && is_numeric($meta['quantity']) ? (float) $meta['quantity'] : null,
            'change_percent' => isset($meta['change_percent']) && is_numeric($meta['change_percent']) ? (float) $meta['change_percent'] : null,
            'volatility' => (string) ($meta['volatility'] ?? $this->volatilityFromMeta($meta)),
            'generated_at' => $signal->generated_at,
        ];
    }

    private function signalDetail(PulseSignal $signal): array
    {
        $row = $this->signalRow($signal);
        $direction = strtoupper((string) $signal->direction);
        $qualified = collect($signal->strategy_breakdown ?? [])
            ->filter(function ($item) use ($direction): bool {
                if (! is_array($item) || isset($item['_meta']) || (float) ($item['points'] ?? 0) <= 0) return false;
                $bias = strtoupper((string) ($item['bias'] ?? 'NEUTRAL'));
                return $bias === $direction || $bias === 'NEUTRAL';
            })
            ->sortByDesc(fn (array $item): float => (float) ($item['points'] ?? 0))
            ->values();

        $evidence = $qualified->take(6)->map(fn (array $item): array => [
            'label' => (string) ($item['name'] ?? 'Strategy confirmation'),
            'value' => $direction === 'NEUTRAL' ? 'Confirmed' : $direction,
            'matched' => true,
            'detail' => (string) ($item['reason'] ?? ''),
        ])->values();

        if ($evidence->isEmpty()) {
            $meta = $this->signalMeta($signal);
            $evidence = collect($meta['evidence'] ?? [])->filter(fn ($item) => is_array($item))->take(6)->values();
        }

        return array_merge($row, [
            'evidence' => $evidence,
            'evidence_total' => $qualified->count(),
            'unlocked_at' => $signal->unlocked_at,
            'ai_explanation' => $signal->ai_explanation,
            'ai_explained_at' => $signal->ai_explained_at,
            'share_count' => (int) $signal->share_count,
        ]);
    }

    private function signalMeta(PulseSignal $signal): array
    {
        foreach ($signal->strategy_breakdown ?? [] as $item) {
            if (is_array($item) && isset($item['_meta']) && is_array($item['_meta'])) return $item['_meta'];
        }
        return [];
    }

    private function riskReward(PulseSignal $signal): float
    {
        $entry = (float) $signal->entry_price;
        $risk = abs($entry - (float) $signal->stop_loss);
        return $risk > 0 ? abs((float) $signal->take_profit - $entry) / $risk : 0;
    }

    private function strategyOptions(User $user): Collection
    {
        $plan = $user->pulsePlan();
        if ($plan && $plan->strategies()->wherePivot('is_enabled', true)->exists()) {
            return $plan->strategies()->where('pulse_strategies.is_enabled', true)->wherePivot('is_enabled', true)
                ->orderBy('sort_order')->get(['pulse_strategies.id','pulse_strategies.name','pulse_strategies.slug']);
        }
        return PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get(['id','name','slug']);
    }

    private function analysisUsesStrategy(array $breakdown, string $strategy, string $direction): bool
    {
        $needle = strtolower(trim($strategy));
        if ($needle === '' || $needle === 'all') return true;
        $direction = strtoupper($direction);
        return collect($breakdown)->contains(function ($item) use ($needle, $direction): bool {
            if (! is_array($item)) return false;
            $matches = strtolower((string) ($item['slug'] ?? '')) === $needle || strtolower((string) ($item['name'] ?? '')) === $needle;
            if (! $matches || (float) ($item['points'] ?? 0) <= 0) return false;
            $bias = strtoupper((string) ($item['bias'] ?? ''));
            return $direction === 'NEUTRAL' || $bias === $direction || $bias === 'NEUTRAL';
        });
    }

    private function signalUsesStrategy(PulseSignal $signal, string $strategy): bool
    {
        $needle = strtolower(trim($strategy));
        if ($needle === '' || $needle === 'all') return true;
        return collect($signal->strategy_breakdown ?? [])->contains(function ($item) use ($needle, $signal): bool {
            if (! is_array($item) || isset($item['_meta'])) return false;
            $matches = strtolower((string) ($item['slug'] ?? '')) === $needle || strtolower((string) ($item['name'] ?? '')) === $needle;
            if (! $matches || (float) ($item['points'] ?? 0) <= 0) return false;
            $bias = strtoupper((string) ($item['bias'] ?? ''));
            return $bias === strtoupper((string) $signal->direction) || $bias === 'NEUTRAL';
        });
    }

    private function qualifiedStrategyNames(PulseSignal $signal): array
    {
        $direction = strtoupper((string) $signal->direction);
        $names = collect($signal->strategy_breakdown ?? [])
            ->filter(function ($item) use ($direction): bool {
                if (! is_array($item) || isset($item['_meta']) || (float) ($item['points'] ?? 0) <= 0) return false;
                $bias = strtoupper((string) ($item['bias'] ?? 'NEUTRAL'));
                return $bias === $direction || $bias === 'NEUTRAL';
            })
            ->sortByDesc(fn (array $item): float => (float) ($item['points'] ?? 0))
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();

        return $names !== [] ? $names : [$this->primaryStrategyName($signal)];
    }

    private function signalTradeAction(User $user, PulseSignal $signal, PulseUserSetting $settings, bool $connectionReady, array $capabilities, ?PulseTrade $activeTrade = null, ?float $currentPrice = null): array
    {
        $row = $this->signalRow($signal, $currentPrice);
        $direction = strtoupper((string) $row['direction']);
        $last = (float) ($row['last_price'] ?? $row['entry_price'] ?? 0);
        $entry = (float) ($row['entry_price'] ?? 0);
        $low = (float) ($row['entry_low'] ?? 0);
        $high = (float) ($row['entry_high'] ?? 0);
        $stop = (float) ($row['stop_loss'] ?? 0);
        $target = (float) ($row['take_profit'] ?? 0);
        $insideEntry = $last > 0 && $low > 0 && $high > 0 && $last >= min($low, $high) && $last <= max($low, $high);
        $targetOrStopReached = $last > 0 && match ($direction) {
            'LONG' => ($target > 0 && $last >= $target) || ($stop > 0 && $last <= $stop),
            'SHORT' => ($target > 0 && $last <= $target) || ($stop > 0 && $last >= $stop),
            default => false,
        };

        if ($activeTrade) {
            return [
                'phase' => 'Trade Open',
                'kind' => 'active',
                'label' => 'Manage Trade',
                'detail' => 'Your Binance order or position for this signal is already active. Monitor fill, TP/SL protection and trade progress.',
                'direct' => false,
                'target' => 'trade',
                'trade_id' => $activeTrade->id,
                'current_price' => $last,
                'execution_profile' => 'existing_trade',
            ];
        }

        if (! $signal->isActionable() || $targetOrStopReached) {
            return [
                'phase' => 'Signal Closed',
                'kind' => 'expired',
                'label' => 'View Signal',
                'detail' => $targetOrStopReached
                    ? 'Live price has reached the signal target or stop boundary. A new entry is no longer recommended.'
                    : 'This signal has expired or is no longer active. Review it before considering a fresh setup.',
                'direct' => false,
                'target' => 'signal_detail',
                'current_price' => $last,
                'execution_profile' => 'closed',
            ];
        }


        $access = app(PulseAccessService::class);
        $environment = strtolower((string) ($settings->environment ?? 'testnet'));
        $manualAllowed = (bool) data_get($capabilities, 'manual_trading.enabled', false);
        $environmentAllowed = $environment === 'live'
            ? ((bool) data_get($capabilities, 'live_trading.enabled', false) && (bool) config('pulse.allow_live_trading', false) && $access->systemEnabled('live_trading_enabled', false))
            : ((bool) data_get($capabilities, 'testnet_trading.enabled', false) && $access->systemEnabled('testnet_trading_enabled', true));
        $executionReady = $manualAllowed
            && $environmentAllowed
            && $connectionReady
            && ! (bool) $settings->emergency_stop
            && $access->systemEnabled('execution_enabled', true)
            && ! $access->systemEnabled('emergency_stop_all', false);
        $managedSetup = $executionReady && strtolower((string) ($settings->execution_mode ?? 'signal_only')) === 'signal_only';

        $favorableMove = $last > 0 && $entry > 0 && match ($direction) {
            'LONG' => $last > max($high, $entry),
            'SHORT' => $last < min($low, $entry),
            default => false,
        };
        $againstMove = $last > 0 && $entry > 0 && ! $insideEntry && ! $favorableMove;

        if ($insideEntry) {
            $phase = 'Entry Ready';
            $kind = 'ready';
            $detail = 'Live Binance price is inside the planned entry zone. The signal is ready for a protected entry.';
            $profile = 'entry_ready';
        } elseif ($favorableMove) {
            $phase = 'Move in Progress';
            $kind = 'active';
            $detail = 'Live price has moved in the signal direction beyond the original entry. You can still open the trade panel; the preferred approach is a LIMIT entry at the planned signal price rather than chasing the market.';
            $profile = 'pullback_limit';
        } else {
            $phase = 'Entry Watch';
            $kind = 'opportunity';
            $detail = 'Live price has moved away from the entry against the signal but has not invalidated the setup. Review the levels before placing an entry order.';
            $profile = 'entry_watch';
        }

        if ($managedSetup) {
            $detail .= ' Pulse will apply your managed trading defaults automatically when you confirm; no Settings step is required.';
        } elseif (! $executionReady) {
            $detail .= ' Pulse will show the one required setup step before an order can be submitted.';
        }

        $setupTarget = 'guided';
        if (! $executionReady) {
            if (! $manualAllowed) $setupTarget = 'plans';
            elseif (! $connectionReady) $setupTarget = 'binance';
            elseif ((bool) $settings->emergency_stop) $setupTarget = 'risk';
            elseif (! $environmentAllowed) $setupTarget = 'environment';
            else $setupTarget = 'unavailable';
        }

        return [
            'phase' => $phase,
            'kind' => $kind,
            'label' => 'Open Trade',
            'detail' => $detail,
            'direct' => $executionReady,
            'target' => $setupTarget,
            'current_price' => $last,
            'execution_profile' => $profile,
            'managed_setup' => $managedSetup,
        ];
    }

    private function liveTickerMap(array $symbols): Collection
    {
        $wanted = collect($symbols)->map(fn ($symbol) => strtoupper(trim((string) $symbol)))->filter()->unique()->values();
        if ($wanted->isEmpty()) return collect();

        return app(PulseMarketDataService::class)->latestPrices($wanted, (int) config('pulse.market_data.read_max_age_seconds', 300))
            ->mapWithKeys(fn ($row, $symbol) => [$symbol => [
                'price' => (float) $row->price,
                'change_percent' => is_numeric($row->change_percent_24h ?? null) ? (float) $row->change_percent_24h : null,
                'observed_at' => $row->observed_at,
            ]]);
    }

    private function primaryStrategyName(PulseSignal $signal): string
    {
        $top = collect($signal->strategy_breakdown ?? [])
            ->filter(fn ($item) => is_array($item) && ! isset($item['_meta']) && (float) ($item['points'] ?? 0) > 0)
            ->sortByDesc(fn (array $item) => (float) ($item['points'] ?? 0))->first();
        return (string) ($top['name'] ?? $this->strategyFamily($signal));
    }

    private function strategyFamily(PulseSignal $signal): string
    {
        $meta = $this->signalMeta($signal);
        if (! empty($meta['strategy']) && in_array($meta['strategy'], ['Momentum', 'Breakout', 'Reversal'], true)) return $meta['strategy'];
        $top = collect($signal->strategy_breakdown ?? [])
            ->filter(fn ($item) => is_array($item) && ! isset($item['_meta']))
            ->sortByDesc(fn (array $item) => (float) ($item['points'] ?? 0))->first();
        $slug = strtolower((string) ($top['slug'] ?? ''));
        if (str_contains($slug, 'breakout') || str_contains($slug, 'range') || str_contains($slug, 'volume')) return 'Breakout';
        if (str_contains($slug, 'reversion') || str_contains($slug, 'swing') || str_contains($slug, 'structure')) return 'Reversal';
        return 'Momentum';
    }

    private function strategyFamilies(): array
    {
        return [
            ['name' => 'Momentum', 'version' => '2.4', 'description' => 'Trend-continuation setups confirmed by price structure and volume.', 'timeframes' => '15M · 4H', 'minimum_score' => 75, 'icon' => 'trend'],
            ['name' => 'Breakout', 'version' => '1.8', 'description' => 'Expansion setups validated by range, liquidity and breakout confirmation.', 'timeframes' => '15M · 4H', 'minimum_score' => 78, 'icon' => 'breakout'],
            ['name' => 'Reversal', 'version' => '1.3', 'description' => 'Mean-reversion setups filtered by exhaustion and structural confirmation.', 'timeframes' => '4H', 'minimum_score' => 72, 'icon' => 'reversal'],
        ];
    }

    private function volatilityFromMeta(array $meta): string
    {
        $atr = data_get($meta, 'atr_percent');
        if (! is_numeric($atr)) return 'Moderate';
        return (float) $atr >= 2 ? 'Elevated' : ((float) $atr < .8 ? 'Low' : 'Moderate');
    }

    private function pair(string $symbol): string
    {
        $symbol = strtoupper($symbol);
        foreach (['USDT', 'USDC', 'FDUSD', 'BUSD'] as $quote) {
            if (str_ends_with($symbol, $quote) && strlen($symbol) > strlen($quote)) {
                return substr($symbol, 0, -strlen($quote)).'/'.$quote;
            }
        }
        return $symbol;
    }

    private function performancePoints(Collection $closedTrades): array
    {
        $ordered = $closedTrades->sortBy(fn (PulseTrade $trade) => $trade->closed_at ?? $trade->created_at)->values();
        if ($ordered->isEmpty()) return [['x' => 0, 'value' => 0], ['x' => 100, 'value' => 0]];
        $running = 0.0;
        $points = [];
        foreach ($ordered as $index => $trade) {
            $running += (float) $trade->realized_pnl;
            $points[] = ['x' => $ordered->count() === 1 ? 100 : ($index / ($ordered->count() - 1)) * 100, 'value' => $running];
        }
        return $points;
    }

    private function maximumDrawdown(Collection $closedTrades, float $accountEquity): float
    {
        $running = 0.0;
        $peak = 0.0;
        $drawdown = 0.0;
        foreach ($closedTrades->sortBy(fn (PulseTrade $trade) => $trade->closed_at ?? $trade->created_at) as $trade) {
            $running += (float) $trade->realized_pnl;
            $peak = max($peak, $running);
            $drawdown = min($drawdown, $running - $peak);
        }
        return $accountEquity > 0 ? abs($drawdown / $accountEquity) * 100 : 0;
    }

    private function normalizePeriod(string $period): string
    {
        return in_array(strtolower($period), ['24h', '7d', '30d', '90d', 'all'], true) ? strtolower($period) : '30d';
    }

    private function periodStart(string $period): ?Carbon
    {
        return match ($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7)->startOfDay(),
            '30d' => now()->subDays(30)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            default => null,
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            '24h' => '24-Hour',
            '7d' => '7-Day',
            '90d' => '90-Day',
            'all' => 'All-Time',
            default => '30-Day',
        };
    }
}
