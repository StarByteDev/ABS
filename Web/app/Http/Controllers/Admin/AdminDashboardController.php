<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Models\EmailDeliveryLog;
use App\Models\ContactMessage;
use App\Models\NewsArticle;
use App\Models\PortfolioAccount;
use App\Models\PulsePlan;
use App\Models\PulsePublicSignalUnlock;
use App\Models\PulseAuditLog;
use App\Models\PulseMembershipRequest;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategy;
use App\Models\PulseStrategyLearningState;
use App\Models\PulseTrade;
use App\Models\ResearchReport;
use App\Models\LearningArticle;
use App\Models\EconomicEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Services\PulseMarketDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, PulseMarketDataService $market)
    {
        $now = now();
        try {
            $from = Carbon::parse((string) $request->query('from', today()->subDays(29)->toDateString()))->startOfDay();
            $to = Carbon::parse((string) $request->query('to', today()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = today()->subDays(29)->startOfDay();
            $to = today()->endOfDay();
        }
        if ($from->greaterThan($to) || $from->diffInDays($to) > 365) {
            $from = today()->subDays(29)->startOfDay();
            $to = today()->endOfDay();
        }
        $timeframe = in_array($request->query('timeframe'), ['15m', '4h'], true) ? $request->query('timeframe') : null;
        $in7 = $now->copy()->addDays(7);
        $in30 = $now->copy()->addDays(30);

        $pulseBase = UserServiceAccess::query()->where('service', 'pulse');
        $activePulse = (clone $pulseBase)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));

        $expiring7 = (clone $activePulse)->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $in7])->count();
        $expiring30 = (clone $activePulse)->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $in30])->count();
        $expired = (clone $pulseBase)->whereNotNull('ends_at')->where('ends_at', '<=', $now)->count();
        $trialPlanIds = PulsePlan::query()->where('is_trial', true)->pluck('id');

        $planRows = PulsePlan::query()
            ->withCount([
                'accesses as assigned_count' => fn ($q) => $q->where('service', 'pulse'),
                'accesses as active_count' => fn ($q) => $q->where('service', 'pulse')->where('status', 'active')
                    ->where(fn ($x) => $x->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
                'accesses as expiring_30_count' => fn ($q) => $q->where('service', 'pulse')->where('status', 'active')
                    ->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $in30]),
            ])
            ->orderBy('sort_order')->orderBy('name')->get();

        $expiryQueue = UserServiceAccess::query()->with(['user', 'plan'])
            ->where('service', 'pulse')->where('status', 'active')->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$now, $in30])->orderBy('ends_at')->limit(12)->get();

        $recentUsers = User::query()->with(['pulseAccess.plan'])->latest()->limit(8)->get();
        $validations30 = PulseSignalValidation::query()->whereBetween('generated_at', [$from, $to]);
        if ($timeframe) $validations30->where('timeframe', $timeframe);
        $wins30 = (clone $validations30)->where('outcome', 'tp')->count();
        $losses30 = (clone $validations30)->where('outcome', 'sl')->count();
        $ambiguous30 = (clone $validations30)->where('outcome', 'ambiguous')->count();
        $pending30 = (clone $validations30)->whereNull('resolved_at')->count();
        $signalRange = PulseSignal::query()->whereBetween('generated_at', [$from, $to]);
        if ($timeframe) $signalRange->where('timeframe', $timeframe);
        $signals30 = (clone $signalRange)->count();

        $trendStart = $from->copy()->startOfDay();
        $trendDays = (int) $trendStart->diffInDays($to->copy()->startOfDay());
        $signalByDay = PulseSignal::query()
            ->selectRaw('DATE(generated_at) as metric_day, COUNT(*) as signals')
            ->whereBetween('generated_at', [$from, $to])
            ->when($timeframe, fn ($query) => $query->where('timeframe', $timeframe))
            ->groupBy(DB::raw('DATE(generated_at)'))->get()->keyBy('metric_day');
        $validationByDay = PulseSignalValidation::query()
            ->selectRaw("DATE(generated_at) as metric_day, SUM(CASE WHEN entry_hit_at IS NOT NULL THEN 1 ELSE 0 END) as entries, SUM(CASE WHEN outcome = 'tp' THEN 1 ELSE 0 END) as wins, SUM(CASE WHEN outcome = 'sl' THEN 1 ELSE 0 END) as losses")
            ->whereBetween('generated_at', [$from, $to])
            ->when($timeframe, fn ($query) => $query->where('timeframe', $timeframe))
            ->groupBy(DB::raw('DATE(generated_at)'))->get()->keyBy('metric_day');
        $dashboardTrend = collect(range(0, $trendDays))->map(function (int $offset) use ($trendStart, $signalByDay, $validationByDay): array {
            $date = $trendStart->copy()->addDays($offset);
            $signal = $signalByDay->get($date->toDateString());
            $validation = $validationByDay->get($date->toDateString());
            return [
                'label' => $date->format('d M'),
                'signals' => (int) ($signal->signals ?? 0),
                'entries' => (int) ($validation->entries ?? 0),
                'wins' => (int) ($validation->wins ?? 0),
                'losses' => (int) ($validation->losses ?? 0),
            ];
        })->values();
        $marketHealth = $market->health();

        $learningByStrategy = PulseStrategyLearningState::query()->get()->groupBy('strategy_slug');
        $strategyConfidence = PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get()->map(function ($strategy) use ($learningByStrategy): array {
            $states = $learningByStrategy->get($strategy->slug, collect());
            return [
                'name' => $strategy->name,
                'confidence' => $states->isNotEmpty() ? round((float) $states->avg('reliability_score'), 1) : 50.0,
                'signals' => (int) $states->sum('sample_size'),
                'win_rate' => $states->isNotEmpty() ? round((float) $states->avg('win_rate') * 100, 1) : null,
            ];
        })->sortByDesc('confidence')->take(5)->values();

        $topMarkets = PulseSignal::query()->from('pulse_signals as s')
            ->leftJoin('pulse_signal_validations as v', 'v.signal_id', '=', 's.id')
            ->whereBetween('s.generated_at', [$from, $to])
            ->when($timeframe, fn ($query) => $query->where('s.timeframe', $timeframe))
            ->selectRaw("s.symbol, COUNT(DISTINCT s.id) as signals, SUM(CASE WHEN v.outcome = 'tp' THEN 1 ELSE 0 END) as wins, SUM(CASE WHEN v.outcome = 'sl' THEN 1 ELSE 0 END) as losses")
            ->groupBy('s.symbol')->orderByDesc('signals')->limit(5)->get();
        $recentActivity = PulseAuditLog::query()->with('user')->latest('created_at')->limit(5)->get();

        $memberGrowth = User::query()
            ->selectRaw('DATE(created_at) as metric_day, COUNT(*) as registrations')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(created_at)'))->get()->keyBy('metric_day');
        $memberGrowthTrend = collect(range(0, $trendDays))->map(function (int $offset) use ($trendStart, $memberGrowth): array {
            $date = $trendStart->copy()->addDays($offset);
            return [
                'label' => $date->format('d M'),
                'registrations' => (int) ($memberGrowth->get($date->toDateString())->registrations ?? 0),
            ];
        })->values();

        $latestTrades = PulseTrade::query()->with(['user','signal'])->latest('created_at')->limit(6)->get();
        $bestSignal = PulseSignal::query()->with('validation')
            ->whereBetween('generated_at', [$from, $to])
            ->when($timeframe, fn ($query) => $query->where('timeframe', $timeframe))
            ->orderByDesc(DB::raw('COALESCE(confidence_score, score)'))
            ->latest('generated_at')->first();
        $approvedPayments = PulseMembershipRequest::query()
            ->where('status', 'approved')->whereBetween('reviewed_at', [$from, $to]);
        $pendingPayments = PulseMembershipRequest::query()->whereIn('status', ['submitted', 'under_review']);
        $paymentStats = [
            'approved_usdt' => (float) (clone $approvedPayments)->sum('final_amount'),
            'approved_count' => (int) (clone $approvedPayments)->count(),
            'pending_count' => (int) (clone $pendingPayments)->count(),
            'pending_usdt' => (float) (clone $pendingPayments)->sum('final_amount'),
        ];
        $publicRewardedStats = [
            'selected_period' => (int) PulsePublicSignalUnlock::query()->where('status', 'granted')->whereBetween('claimed_at', [$from, $to])->count(),
            'today' => (int) PulsePublicSignalUnlock::query()->where('status', 'granted')->whereDate('claimed_at', today())->count(),
        ];

        return view('admin.dashboard', [
            'stats' => [
                'Total users' => User::count(),
                'Active accounts' => User::where('status', 'active')->count(),
                'Active Pulse users' => (clone $activePulse)->count(),
                'Trial users' => (clone $activePulse)->whereIn('pulse_plan_id', $trialPlanIds)->count(),
                'Expiring in 7 days' => $expiring7,
                'Expiring in 30 days' => $expiring30,
                'Expired Pulse access' => $expired,
                'Private members' => User::where('role', 'private_member')->whereNotNull('private_member_approved_at')->count(),
                'Membership requests' => PulseMembershipRequest::query()->whereIn('status',['submitted','under_review'])->count(),
            ],
            'planRows' => $planRows,
            'expiryQueue' => $expiryQueue,
            'recentUsers' => $recentUsers,
            'dashboardTrend' => $dashboardTrend,
            'from' => $from,
            'to' => $to,
            'timeframe' => $timeframe,
            'strategyConfidence' => $strategyConfidence,
            'topMarkets' => $topMarkets,
            'recentActivity' => $recentActivity,
            'memberGrowthTrend' => $memberGrowthTrend,
            'latestTrades' => $latestTrades,
            'bestSignal' => $bestSignal,
            'paymentStats' => $paymentStats,
            'publicRewardedStats' => $publicRewardedStats,
            'dashboardOutcomeMix' => [
                'wins' => $wins30,
                'losses' => $losses30,
                'ambiguous' => $ambiguous30,
                'pending' => $pending30,
            ],
            'planAdoptionChart' => $planRows->map(fn ($plan) => [
                'label' => $plan->name,
                'active' => (int) $plan->active_count,
                'expiring' => (int) $plan->expiring_30_count,
            ])->values(),
            'operations' => [
                'Scanner runs today' => PulseScannerRun::query()->whereDate('created_at', today())->count(),
                'Active signals' => PulseSignal::query()->where('status', 'active')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))->count(),
                'Open trades' => PulseTrade::query()->whereIn('status', ['submitting','pending','open','closing','protection_failed'])->count(),
                'Published headlines' => NewsArticle::where('status', 'published')->count(),
                'Member accounts' => PortfolioAccount::count(),
                'Active plans' => PulsePlan::where('is_active', true)->count(),
                'New support enquiries' => ContactMessage::query()->where('status', 'new')->count(),
                'Email failures 24h' => EmailDeliveryLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(),
                'Active mobile devices' => MobileDevice::query()->where('is_active', true)->count(),
            ],
            'userLevels' => [
                ['label' => 'Standard users', 'value' => User::query()->where('role', 'user')->where('status', 'active')->count(), 'note' => 'Core ABS account access'],
                ['label' => 'Pulse customers', 'value' => (clone $activePulse)->count(), 'note' => 'Active package entitlement'],
                ['label' => 'Private members', 'value' => User::query()->where('role', 'private_member')->where('status', 'active')->count(), 'note' => 'Private reporting access'],
                ['label' => 'Administrators', 'value' => User::query()->where('role', 'admin')->where('status', 'active')->count(), 'note' => 'Enterprise control authority'],
            ],
            'tradingReport' => [
                'Signals today' => PulseSignal::query()->whereDate('generated_at', today())->count(),
                'Signals · 24h' => PulseSignal::query()->where('generated_at', '>=', $now->copy()->subDay())->count(),
                'Signals · 30d' => $signals30,
                'Entries · 30d' => (clone $validations30)->whereNotNull('entry_hit_at')->count(),
                'TP outcomes · 30d' => $wins30,
                'SL outcomes · 30d' => $losses30,
                'Decisive win rate' => ($wins30 + $losses30) > 0 ? number_format(($wins30 / ($wins30 + $losses30)) * 100, 1).'%' : '—',
                'Pending validation' => $pending30,
                'Protection review' => PulseTrade::query()->whereIn('status', ['open','protection_failed'])->where('protection_status', '!=', 'confirmed')->count(),
            ],
            'commerceReport' => [
                'USDT approved · selected range' => $paymentStats['approved_usdt'],
                'Approved package payments' => $paymentStats['approved_count'],
                'Payments awaiting review' => $paymentStats['pending_count'],
                'Pending USDT value' => $paymentStats['pending_usdt'],
                'Public rewarded signal unlocks' => $publicRewardedStats['selected_period'],
            ],
            'contentReport' => [
                ['label' => 'Market news', 'published' => NewsArticle::query()->where('status','published')->count(), 'draft' => NewsArticle::query()->where('status','draft')->count(), 'route' => route('admin.content.index','news')],
                ['label' => 'Research', 'published' => ResearchReport::query()->where('status','published')->count(), 'draft' => ResearchReport::query()->where('status','draft')->count(), 'route' => route('admin.enterprise.content.index','research')],
                ['label' => 'Learning', 'published' => LearningArticle::query()->where('status','published')->count(), 'draft' => LearningArticle::query()->where('status','draft')->count(), 'route' => route('admin.enterprise.content.index','learning')],
                ['label' => 'Economic events', 'published' => EconomicEvent::query()->where('event_at','>=',$now)->count(), 'draft' => 0, 'route' => route('admin.enterprise.content.index','events')],
                ['label' => 'Products & services', 'published' => Product::query()->where('status','live')->count(), 'draft' => Product::query()->where('status','draft')->count(), 'route' => route('admin.enterprise.content.index','products')],
            ],
            'controlStatus' => [
                ['label' => 'Signal Engine', 'value' => 'Operational', 'state' => 'good', 'note' => $signals30.' selected-period signals', 'route' => route('admin.pulse.signals')],
                ['label' => 'Execution Router', 'value' => 'Operational', 'state' => 'good', 'note' => PulseTrade::query()->whereIn('status',['submitting','pending','open','closing'])->count().' active records', 'route' => route('admin.pulse.trades')],
                ['label' => 'Risk Monitor', 'value' => PulseTrade::query()->whereIn('status',['open','protection_failed'])->where('protection_status','!=','confirmed')->exists() ? 'Review' : 'Operational', 'state' => PulseTrade::query()->whereIn('status',['open','protection_failed'])->where('protection_status','!=','confirmed')->exists() ? 'danger' : 'good', 'note' => 'TP/SL protection oversight', 'route' => route('admin.pulse.trades')],
                ['label' => 'User Services', 'value' => 'Operational', 'state' => 'good', 'note' => (clone $activePulse)->count().' active Pulse users', 'route' => route('admin.users')],
                ['label' => 'Market Data Feeds', 'value' => strtoupper((string)($marketHealth['feed_status'] ?? 'offline')), 'state' => ($marketHealth['feed_status'] ?? null) === 'healthy' ? 'good' : (($marketHealth['feed_status'] ?? null) === 'delayed' ? 'warn' : 'danger'), 'note' => isset($marketHealth['price_age_seconds']) ? $marketHealth['price_age_seconds'].' sec price age' : 'No current price', 'route' => route('admin.market-data')],
                ['label' => 'Notifications', 'value' => EmailDeliveryLog::query()->where('status','failed')->where('created_at','>=',$now->copy()->subDay())->exists() ? 'Review' : 'Operational', 'state' => EmailDeliveryLog::query()->where('status','failed')->where('created_at','>=',$now->copy()->subDay())->exists() ? 'warn' : 'good', 'note' => 'Branded email delivery', 'route' => route('admin.enterprise.emails')],
                ['label' => 'Database', 'value' => 'Connected', 'state' => 'good', 'note' => 'Application data available', 'route' => route('admin.enterprise.maintenance')],
            ],
            'marketHealth' => $marketHealth,
        ]);
    }
}
