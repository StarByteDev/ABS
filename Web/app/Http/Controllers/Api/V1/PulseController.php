<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BinanceConnection;
use App\Models\PulseAlert;
use App\Models\PulseMembershipRequest;
use App\Models\PulsePair;
use App\Models\PulsePlan;
use App\Models\PulsePromotionCode;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseSignalDailyMetric;
use App\Models\PulseSignalValidation;
use App\Models\PulseStrategyDailyMetric;
use App\Models\PulseStrategyLearningState;
use App\Models\PulseStrategy;
use App\Models\PulseTrade;
use App\Models\PulseUserSetting;
use App\Models\UserServiceAccess;
use App\Services\BinanceFuturesService;
use App\Services\BrandedMailService;
use App\Services\PulseAccessService;
use App\Services\PulseAuditService;
use App\Services\PulseAiExplanationService;
use App\Services\PulseShareService;
use App\Services\PulseScannerService;
use App\Services\PulseSignalThresholdService;
use App\Services\PulseTradeService;
use App\Services\PulseMembershipService;
use App\Services\PulseMarketDataService;
use App\Services\PulsePageDataService;
use App\Services\PulsePairAccessService;
use App\Services\PulsePairSelectionLockService;
use App\Services\PulseUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PulseController extends Controller
{
    public function access(Request $request, PulseAccessService $accessService, PulseMembershipService $membership)
    {
        $access = $request->user()->pulseAccess()->with('plan')->first();
        return response()->json([
            'has_access' => $request->user()->hasPulseAccess(),
            'access' => $this->publicAccessPayload($access, $membership),
            'plan' => $access?->plan ? $membership->mobilePlanPayload($access->plan, $access) : null,
            'next_upgrade_plan' => $this->publicPlanPayload($membership->nextUpgradePlan($access), $access, $membership),
            'effective_capabilities' => $accessService->capabilityMatrix($request->user()),
            'commerce_model' => 'direct_usdt_admin_verification',
            'commerce_label' => 'Direct USDT · Admin verified',
        ]);
    }

    public function dashboard(Request $request, PulseAccessService $accessService, PulsePageDataService $pages, PulseUsageService $usage, PulseMembershipService $membership)
    {
        $user = $request->user();
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaults($user));
        $access = $user->pulseAccess()->with('plan')->first();
        $trades = PulseTrade::query()->where('user_id', $user->id);
        $summary = $pages->dashboard($user, (string) $request->query('period', '30d'));
        $signals = PulseSignal::query()->where('user_id', $user->id)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('generated_at')->limit(10)->get()->map(fn (PulseSignal $signal) => $this->publicSignalPayload($signal));

        return response()->json([
            'settings' => $this->publicTradingSettings($settings),
            'access' => $this->publicAccessPayload($access, $membership),
            'active_signals' => $signals,
            'recent_trades' => (clone $trades)->latest()->limit(10)->get(),
            'open_trades' => (clone $trades)->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed'])->count(),
            'realized_pnl' => (float) (clone $trades)->sum('realized_pnl'),
            'unrealized_pnl' => (float) (clone $trades)->whereIn('status', ['open', 'closing', 'protection_failed'])->sum('unrealized_pnl'),
            'unread_alerts' => PulseAlert::query()->where('user_id', $user->id)->where('is_read', false)->count(),
            'effective_capabilities' => $accessService->capabilityMatrix($user),
            'summary' => $summary,
            'usage' => $usage->today($user),
        ]);
    }

    public function plans(Request $request, PulseMembershipService $membership)
    {
        $access = $request->user()->pulseAccess()->with('plan')->first();
        $plans = $membership->upgradePathPlans($access)->map(fn (PulsePlan $plan) => $membership->mobilePlanPayload($plan, $access));
        return response()->json([
            'data' => $plans,
            'next_upgrade_plan' => $this->publicPlanPayload($membership->nextUpgradePlan($access), $access, $membership),
            'commerce_model' => 'direct_usdt_admin_verification',
            'commerce_label' => 'Direct USDT · Admin verified',
        ]);
    }
    public function membership(Request $request, PulseMembershipService $membership)
    {
        $user = $request->user();
        $commerce = $membership->settings();
        $access = $user->pulseAccess()->with('plan')->first();
        return response()->json(['data' => [
            'commerce_model' => 'direct_usdt_admin_verification',
            'commerce_label' => 'Direct USDT · Admin verified',
            'access' => $this->publicAccessPayload($access, $membership),
            'next_upgrade_plan' => $this->publicPlanPayload($membership->nextUpgradePlan($access), $access, $membership),
            'plans' => $membership->upgradePathPlans($access)->map(fn (PulsePlan $plan) => $membership->mobilePlanPayload($plan, $access)),
            'payment_requests_enabled' => (bool) $commerce['requests_enabled'],
            'wallet_address' => $commerce['wallet_address'],
            'network' => $commerce['network'],
            'payment_instructions' => $commerce['payment_instructions'],
            'proof_required' => (bool) $commerce['proof_required'],
            'requests' => PulseMembershipRequest::query()->where('user_id', $user->id)->with('plan')->latest()->limit(20)->get()->map(fn (PulseMembershipRequest $item) => [
                'id' => $item->id, 'plan_name' => $item->plan?->name, 'amount' => (float)$item->final_amount, 'currency' => $item->currency,
                'payment_reference' => $item->payment_reference, 'status' => $item->status, 'submitted_at' => $item->created_at, 'reviewed_at' => $item->reviewed_at,
            ]),
        ]]);
    }

    public function membershipQuote(Request $request, PulseMembershipService $membership)
    {
        $data = $request->validate(['pulse_plan_id' => ['required','integer','exists:pulse_plans,id']]);
        $plan = PulsePlan::query()->findOrFail((int)$data['pulse_plan_id']);
        abort_unless($plan->is_active && $plan->is_public && ! $plan->is_trial && $plan->request_enabled, 404);
        $commerce = $membership->settings();
        $quote = $membership->quote($plan);
        $access = $request->user()->pulseAccess()->with('plan')->first();
        return response()->json(['data' => [
            'commerce_model' => 'direct_usdt_admin_verification',
            'plan' => $membership->mobilePlanPayload($plan, $access),
            'quote' => $quote,
            'wallet_address' => $commerce['wallet_address'],
            'network' => $commerce['network'],
            'payment_instructions' => $commerce['payment_instructions'],
            'proof_required' => (bool)$commerce['proof_required'],
            'message' => 'Transfer the exact USDT amount and submit the transaction reference. Admin verification activates the package.',
        ]]);
    }

    public function submitMembershipRequest(Request $request, PulseMembershipService $membership, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate([
            'pulse_plan_id' => ['required','integer','exists:pulse_plans,id'],
            'payment_reference' => ['required','string','min:6','max:190', Rule::unique('pulse_membership_requests','payment_reference')],
            'payment_proof' => ['nullable','file','mimes:jpg,jpeg,png,pdf,webp','max:8192'],
            'user_notes' => ['nullable','string','max:2000'],
        ]);
        $plan = PulsePlan::query()->findOrFail((int)$data['pulse_plan_id']);
        abort_unless($plan->is_active && $plan->is_public && ! $plan->is_trial && $plan->request_enabled, 404);
        $commerce = $membership->settings();
        if (! $commerce['requests_enabled']) return response()->json(['message'=>'New package payment requests are temporarily paused.'],422);
        if ((float)$plan->effectiveMonthlyPrice() > 0 && (($commerce['wallet_address'] ?? '') === '' || ($commerce['network'] ?? '') === '')) return response()->json(['message'=>'USDT payment details are not configured.'],422);
        if (($commerce['proof_required'] ?? false) && ! $request->hasFile('payment_proof')) return response()->json(['message'=>'Payment proof is required.'],422);
        $quote = $membership->quote($plan);
        $item = $membership->createRequest($request,$request->user(),$plan,null,$quote,$commerce);
        $audit->record('api.membership_request_submitted',$request->user(),'PulseMembershipRequest',$item->id,null,['plan_id'=>$plan->id,'amount'=>$quote['final_amount'],'currency'=>$quote['currency']],$request);
        try { $mail->adminNewSubscription($item,'mobile_api'); $mail->planRequestReceived($item); } catch (\Throwable) {}
        return response()->json(['message'=>'Payment submitted for Admin verification.','data'=>$item->fresh('plan')],201);
    }

    public function cancelMembershipRequest(Request $request, PulseMembershipRequest $membershipRequest, PulseAuditService $audit)
    {
        abort_unless((int)$membershipRequest->user_id===(int)$request->user()->id,403);
        if(!$membershipRequest->isOpen()) throw ValidationException::withMessages(['membership'=>'Only an open Pulse plan request can be cancelled.']);
        $membershipRequest->update(['status'=>'cancelled']);
        $audit->record('api.membership_request_cancelled',$request->user(),'PulseMembershipRequest',$membershipRequest->id,null,[],$request);
        return response()->json(['message'=>'Pulse plan request cancelled.','data'=>$membershipRequest]);
    }

    public function pairs(Request $request, PulsePairAccessService $pairAccess)
    {
        $pairs = $pairAccess->allowedPairs($request->user())->values()->map(fn (PulsePair $pair) => [
            'id' => $pair->id,
            'symbol' => $pair->symbol,
            'base_asset' => $pair->base_asset,
            'quote_asset' => $pair->quote_asset,
            'is_active' => (bool) $pair->is_active,
        ]);
        return response()->json(['data' => ['admin_controlled' => true, 'count' => $pairs->count(), 'markets' => $pairs]]);
    }

    public function strategies(Request $request)
    {
        $plan = $request->user()->pulsePlan();
        $includedIds = $plan
            ? $plan->strategies()->where('pulse_strategies.is_enabled', true)->wherePivot('is_enabled', true)->pluck('pulse_strategies.id')->map(fn ($id) => (int) $id)->all()
            : [];
        $catalog = PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get()->map(function (PulseStrategy $strategy) use ($plan, $includedIds) {
            $included = ! $plan || in_array((int) $strategy->id, $includedIds, true);
            return ['id' => $strategy->id, 'name' => $strategy->name, 'slug' => $strategy->slug, 'description' => $strategy->description, 'included' => $included];
        })->values();
        return response()->json(['data' => ['admin_controlled' => true, 'plan' => $plan?->only(['id','name','slug']), 'strategies' => $catalog]]);
    }

    public function executionReadiness(Request $request, PulseAccessService $access)
    {
        $user = $request->user();
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaults($request->user()));
        $plan = $user->pulsePlan();
        $connection = BinanceConnection::query()->where('user_id', $user->id)->where('environment', $settings->environment)->first();
        $connectionReady = (bool) ($connection?->is_active && $connection?->last_tested_at && ! $connection?->last_error && data_get($connection?->permissions, 'can_trade', false));
        $manualUsedToday = PulseTrade::query()->where('user_id', $user->id)->whereDate('created_at', today())
            ->where(fn ($q) => $q->whereNull('meta->automatic')->orWhere('meta->automatic', false))->count();
        $manualAllowed = $access->allows($user, 'manual_trading', false);
        $executionSystemEnabled = $access->systemEnabled('execution_enabled', true) && ! $access->systemEnabled('emergency_stop_all', false);
        $environmentAllowed = $settings->environment === 'live'
            ? config('pulse.allow_live_trading', false) && $access->systemEnabled('live_trading_enabled', false) && $access->allows($user, 'live_trading', false)
            : $access->systemEnabled('testnet_trading_enabled', true) && $access->allows($user, 'testnet_trading', false);
        return response()->json(['data' => [
            'environment' => $settings->environment,
            'execution_mode' => $settings->execution_mode,
            'connection_ready' => $connectionReady,
            'manual_allowed' => $manualAllowed,
            'environment_allowed' => $environmentAllowed,
            'system_enabled' => $executionSystemEnabled,
            'emergency_stop' => (bool) $settings->emergency_stop,
            'manual_trades_used_today' => $manualUsedToday,
            'manual_trades_limit' => (int) ($plan?->manual_trades_per_day ?: 0),
            'ready' => $manualAllowed && $executionSystemEnabled && $environmentAllowed && $connectionReady && ! $settings->emergency_stop,
            'managed_setup' => $manualAllowed && $executionSystemEnabled && $environmentAllowed && $connectionReady && ! $settings->emergency_stop && $settings->execution_mode === 'signal_only',
            'next_step' => ! $manualAllowed ? 'plans' : (! $connectionReady ? 'binance_connection' : ((bool) $settings->emergency_stop ? 'risk_controls' : (! $environmentAllowed ? 'environment' : 'open_trade'))),
        ]]);
    }

    public function positions(Request $request, PulseTradeService $trades)
    {
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        $environment = $request->string('environment')->toString() ?: $settings->environment;
        $local = PulseTrade::query()->where('user_id', $request->user()->id)->where('environment', $environment)
            ->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed'])->latest()->get();
        try {
            $snapshot = $trades->exchangeSnapshot($request->user(), $environment);
            return response()->json(['data' => ['environment' => $environment, 'exchange' => $snapshot, 'local' => $local]]);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['environment' => $environment, 'exchange' => null, 'local' => $local], 'warning' => $e->getMessage()]);
        }
    }

    public function riskControls(Request $request)
    {
        $user = $request->user();
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaults($request->user()));
        $plan = $user->pulsePlan();
        $openQuery = PulseTrade::query()->where('user_id', $user->id)->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed']);
        $openCount = (clone $openQuery)->count();
        $planMaxOpen = max(0, (int) ($plan?->max_open_trades ?? 0));
        $maxOpen = $planMaxOpen > 0 ? max(1, min((int) $settings->max_open_positions, $planMaxOpen)) : 0;
        $todayPnl = (float) PulseTrade::query()->where('user_id', $user->id)->where('status', 'closed')->whereDate('closed_at', today())->sum('realized_pnl');
        return response()->json(['data' => [
            'settings' => $settings,
            'open_count' => $openCount,
            'max_open' => $maxOpen,
            'plan_max_open' => $planMaxOpen,
            'risk_utilization' => $maxOpen > 0 ? min(100, (int) round(($openCount / $maxOpen) * 100)) : 0,
            'today_realized_pnl' => $todayPnl,
            'protection_issues' => (clone $openQuery)->whereIn('protection_status', ['failed', 'review_required'])->count(),
        ]]);
    }

    public function updateRiskControls(Request $request, PulseAuditService $audit)
    {
        $plan = $request->user()->pulsePlan();
        $data = $request->validate([
            'risk_per_trade_percent' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'daily_loss_limit' => ['nullable', 'numeric', 'min:0'],
            'take_profit_percent' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'stop_loss_percent' => ['required', 'numeric', 'min:0.1', 'max:25'],
            'max_open_positions' => ['required', 'integer', 'min:1', 'max:20'],
        ]);
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        $settings->update([
            'risk_per_trade_percent' => min((float) $data['risk_per_trade_percent'], (float) config('pulse.risk.max_risk_per_trade', 5)),
            'daily_loss_limit' => (float) ($data['daily_loss_limit'] ?? 0),
            'take_profit_percent' => (float) $data['take_profit_percent'],
            'stop_loss_percent' => (float) $data['stop_loss_percent'],
            'max_open_positions' => min((int) $data['max_open_positions'], max(1, (int) ($plan?->max_open_trades ?: 1))),
        ]);
        $audit->record('api.risk_settings_updated', $request->user(), 'PulseUserSetting', $settings->id, $settings->environment, [], $request);
        return response()->json(['message' => 'Risk controls updated.', 'data' => $settings->fresh()]);
    }

    public function settings(Request $request, PulseAccessService $accessService)
    {
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        return response()->json([
            'data' => $this->publicTradingSettings($settings),
            'scanner' => [
                'admin_controlled' => true,
                'timeframes' => ['15m','4h'],
                'qualification' => 'admin_managed',
                'market_universe' => 'admin_package_managed',
                'strategy_selection' => 'admin_package_managed',
            ],
            'effective_capabilities' => $accessService->capabilityMatrix($request->user()),
        ]);
    }

    public function usage(Request $request, PulseUsageService $usage)
    {
        return response()->json(['data' => $usage->today($request->user())]);
    }

    public function updateSettings(Request $request, PulseAuditService $audit, PulseAccessService $access)
    {
        $plan = $request->user()->pulsePlan();
        $data = $request->validate([
            'environment' => ['required','in:testnet,live'], 'execution_mode' => ['required','in:signal_only,manual,automatic'],
            'auto_trade_enabled' => ['sometimes','boolean'], 'emergency_stop' => ['sometimes','boolean'],
            'default_leverage' => ['required','integer','min:1','max:'.config('pulse.risk.max_leverage',20)],
            'margin_type' => ['required','in:ISOLATED,CROSSED'], 'position_mode' => ['required','in:BOTH,LONG,SHORT'],
            'risk_per_trade_percent' => ['required','numeric','min:0.1','max:10'], 'sizing_mode' => ['required','in:fixed_notional,fixed_quantity'],
            'fixed_notional' => ['nullable','numeric','min:1'], 'fixed_quantity' => ['nullable','numeric','gt:0'],
            'default_order_type' => ['required','in:MARKET,LIMIT'], 'take_profit_percent' => ['required','numeric','min:0.1','max:50'],
            'stop_loss_percent' => ['required','numeric','min:0.1','max:25'], 'daily_loss_limit' => ['nullable','numeric','min:0'],
            'max_open_positions' => ['required','integer','min:1','max:20'], 'notification_preferences' => ['nullable','array'],
        ]);
        if ($data['execution_mode'] !== 'signal_only') {
            try { $access->assertEnvironment($request->user(), $data['environment']); }
            catch (\Throwable $e) { return response()->json(['message'=>$e->getMessage()],422); }
        } elseif ($data['environment']==='live' && ! $access->allows($request->user(),'live_trading',false)) {
            $data['environment']='testnet';
        }
        $automatic = $data['execution_mode']==='automatic' || (bool)($data['auto_trade_enabled']??false);
        if ($automatic && (!config('pulse.allow_automatic_trading',false) || !$access->systemEnabled('automatic_trading_enabled',false) || !$access->allows($request->user(),'auto_trading',false))) {
            return response()->json(['message'=>'Automatic trading is not enabled for this installation or account.'],422);
        }
        if ($data['execution_mode']==='manual' && !$access->allows($request->user(),'manual_trading',false)) return response()->json(['message'=>'Manual trading is not included in the current Pulse plan.'],422);

        $settings=PulseUserSetting::firstOrCreate(['user_id'=>$request->user()->id],$this->defaults($request->user()));
        $settings->update([
            'environment'=>$data['environment'],'execution_mode'=>$data['execution_mode'],'auto_trade_enabled'=>$automatic && $data['execution_mode']==='automatic',
            'emergency_stop'=>(bool)($data['emergency_stop']??false),'default_leverage'=>min((int)$data['default_leverage'],(int)config('pulse.risk.max_leverage',20)),
            'margin_type'=>$data['margin_type'],'position_mode'=>$data['position_mode'],'risk_per_trade_percent'=>min((float)$data['risk_per_trade_percent'],(float)config('pulse.risk.max_risk_per_trade',5)),
            'sizing_mode'=>$data['sizing_mode'],'fixed_notional'=>$data['fixed_notional']??null,'fixed_quantity'=>$data['fixed_quantity']??null,
            'default_order_type'=>$data['default_order_type'],'take_profit_percent'=>$data['take_profit_percent'],'stop_loss_percent'=>$data['stop_loss_percent'],
            'daily_loss_limit'=>$data['daily_loss_limit']??0,'max_open_positions'=>min((int)$data['max_open_positions'],max(1,(int)($plan?->max_open_trades?:1))),
            'notification_preferences'=>$data['notification_preferences']??[],
        ]);
        $audit->record('api.settings_updated',$request->user(),'PulseUserSetting',$settings->id,$settings->environment,['scanner_controls'=>'admin_managed_v15'],$request);
        return response()->json(['message'=>'Pulse trading preferences updated. Scanner markets, strategies, timeframes and qualification threshold remain Admin controlled.','data'=>$settings->fresh()]);
    }

    public function scannerRuns(Request $request)
    {
        $paginator = PulseScannerRun::query()->where('user_id', $request->user()->id)->with('bestSignal')->latest()->paginate($this->perPage($request));
        $paginator->getCollection()->transform(fn (PulseScannerRun $run) => $this->publicScannerRunPayload($run));
        return response()->json($paginator);
    }

    public function scannerOverview(Request $request, PulseUsageService $usage, PulsePairAccessService $pairAccess)
    {
        $user = $request->user();
        $lastRun = PulseScannerRun::query()->where('user_id', $user->id)->with('bestSignal')->latest()->first();
        return response()->json(['data' => [
            'admin_controlled' => true,
            'timeframes' => ['15m','4h'],
            'market_count' => $pairAccess->allowedPairs($user)->count(),
            'best_signal_included' => true,
            'per_signal_charge' => 0,
            'last_run' => $lastRun ? $this->publicScannerRunPayload($lastRun) : null,
        ], 'usage' => $usage->today($user)]);
    }

    public function runScanner(Request $request, PulseScannerService $scanner, PulseUsageService $usage)
    {
        try { $run = $scanner->run($request->user(), null, 'all'); }
        catch (\Throwable $e) { return response()->json(['message' => $e->getMessage(), 'usage' => $usage->today($request->user())], 422); }
        $run->loadMissing('bestSignal');
        $message = $run->bestSignal
            ? 'Best Signal unlocked. Included with your active Pulse package.'
            : 'No qualifying Best Signal found.';
        return response()->json(['message' => $message, 'data' => $this->publicScannerRunPayload($run), 'usage' => $usage->today($request->user())], 201);
    }

    public function shareSignal(Request $request, PulseSignal $signal, PulseShareService $share)
    {
        abort_unless((int)$signal->user_id===(int)$request->user()->id,403);
        $request->validate(['channel'=>['nullable','string','max:40']]);
        $signal->increment('share_count');
        return response()->json(['message'=>'Share card ready.','data'=>$share->payload($signal)]);
    }

    public function explainSignal(Request $request, PulseSignal $signal, PulseAiExplanationService $ai)
    {
        abort_unless((int)$signal->user_id===(int)$request->user()->id,403);
        try{$text=$ai->explain($request->user(),$signal);}catch(RuntimeException $e){return response()->json(['message'=>$e->getMessage()],422);}
        return response()->json(['message'=>'AI explanation ready.','data'=>['explanation'=>$text]]);
    }

    public function signals(Request $request)
    {
        $query = PulseSignal::query()->where('user_id', $request->user()->id)->latest('generated_at');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('direction')) $query->where('direction', $request->string('direction'));
        if ($request->filled('symbol')) $query->where('symbol', strtoupper((string) $request->string('symbol')));
        $paginator = $query->paginate($this->perPage($request));
        $paginator->getCollection()->transform(fn (PulseSignal $signal) => $this->publicSignalPayload($signal));
        return response()->json($paginator);
    }

    public function signalsOverview(Request $request, PulsePageDataService $pages, PulseUsageService $usage)
    {
        return response()->json(['data' => $pages->signals($request->user(), $request->only(['status','selected'])), 'usage' => $usage->today($request->user())]);
    }

    public function signal(Request $request, PulseSignal $signal)
    {
        abort_unless($signal->user_id === $request->user()->id, 403);
        return response()->json(['data' => $this->publicSignalPayload($signal)]);
    }

    public function dismissSignal(Request $request, PulseSignal $signal, PulsePageDataService $pages)
    {
        return response()->json([
            'message' => 'Signal dismissed. It remains available in signal history.',
            'data' => $pages->dismissSignal($request->user(), $signal),
        ]);
    }

    public function strategiesOverview(Request $request, PulsePageDataService $pages)
    {
        return response()->json(['data' => $pages->strategies($request->user(), (string) $request->query('period', '30d'))]);
    }

    public function executionTicket(Request $request, PulsePageDataService $pages)
    {
        return response()->json(['data' => $pages->execution($request->user(), $request->integer('signal_id') ?: null)]);
    }

    public function executeSignal(Request $request, PulseSignal $signal, PulseTradeService $trades)
    {
        abort_unless($signal->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'environment' => ['required', 'in:testnet,live'], 'order_type' => ['required', 'in:MARKET,LIMIT'],
            'leverage' => ['required', 'integer', 'min:1', 'max:'.config('pulse.risk.max_leverage', 20)], 'quantity' => ['nullable', 'numeric', 'gt:0'],
            'notional' => ['nullable', 'numeric', 'gt:0'], 'price' => ['nullable', 'numeric', 'gt:0'],
            'stop_loss' => ['required', 'numeric', 'gt:0'], 'take_profit' => ['required', 'numeric', 'gt:0'],
            'position_side' => ['required', 'in:BOTH,LONG,SHORT'],
            'time_in_force' => ['nullable', 'in:GTC,IOC,FOK'],
            'client_reference' => ['nullable', 'string', 'max:36', 'regex:/^[.A-Za-z0-9_:\/-]+$/'],
            'confirmed_review' => ['accepted'],
        ]);
        try {
            $trade = $trades->execute($request->user(), $signal, $data);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Order request was not submitted.',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'Order request accepted.', 'data' => $trade], 201);
    }

    public function trades(Request $request)
    {
        $query = PulseTrade::query()->where('user_id', $request->user()->id)->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('environment')) $query->where('environment', $request->string('environment'));
        if ($request->filled('symbol')) $query->where('symbol', strtoupper((string) $request->string('symbol')));
        return response()->json($query->paginate($this->perPage($request)));
    }

    public function trade(Request $request, PulseTrade $trade)
    {
        abort_unless($trade->user_id === $request->user()->id, 403);
        return response()->json(['data' => $trade->load('signal')]);
    }

    public function closeTrade(Request $request, PulseTrade $trade, PulseTradeService $trades)
    {
        abort_unless($trade->user_id === $request->user()->id, 403);
        return response()->json(['message' => 'Close request processed.', 'data' => $trades->close($request->user(), $trade)]);
    }

    public function syncTrades(Request $request, PulseTradeService $trades)
    {
        return response()->json(['message' => 'Synchronization completed.', 'data' => $trades->sync($request->user())]);
    }

    public function emergencyStop(Request $request, PulseTradeService $trades)
    {
        return response()->json(['message' => 'Emergency stop enabled.', 'data' => $trades->emergencyStop($request->user())]);
    }

    public function orders(Request $request, PulseTradeService $trades)
    {
        try { return response()->json(['data' => $trades->exchangeSnapshot($request->user(), $request->string('environment')->toString() ?: null)]); }
        catch (\Throwable $e) { return response()->json(['message' => $e->getMessage()], 422); }
    }

    public function reports(Request $request)
    {
        $from = $request->date('from') ?: now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?: now()->endOfDay();
        $base = PulseTrade::query()->where('user_id', $request->user()->id)->whereBetween('created_at', [$from, $to]);
        $signalMetrics = Schema::hasTable('pulse_signal_daily_metrics')
            ? PulseSignalDailyMetric::query()->where('user_id',$request->user()->id)->whereBetween('metric_date',[$from->toDateString(),$to->toDateString()])->get()
            : collect();
        $wins=(int)$signalMetrics->sum('wins'); $losses=(int)$signalMetrics->sum('losses'); $ambiguous=(int)$signalMetrics->sum('ambiguous');
        $tradingSummary = ['trades' => (clone $base)->count(), 'closed' => (clone $base)->where('status', 'closed')->count(),
            'realized_pnl' => (float) (clone $base)->sum('realized_pnl'), 'fees' => (float) (clone $base)->sum('fees')];
        return response()->json(['data' => [
            'period' => ['from' => $from, 'to' => $to],
            // Backward compatibility: existing mobile clients used `summary` before
            // V14.8.17. Keep it while exposing the clearer `trading` alias.
            'summary' => $tradingSummary,
            'trading' => $tradingSummary,
            'signal_intelligence' => [
                'signals'=>(int)$signalMetrics->sum('signals'),'entries'=>(int)$signalMetrics->sum('entries'),'wins'=>$wins,'losses'=>$losses,'ambiguous'=>$ambiguous,
                'expired_no_entry'=>(int)$signalMetrics->sum('expired_no_entry'),'expired_after_entry'=>(int)$signalMetrics->sum('expired_after_entry'),
                'decisive_win_rate'=>($wins+$losses)>0?round(($wins/($wins+$losses))*100,2):null,
                'avg_mfe_r'=>$this->weightedMetric($signalMetrics,'avg_mfe_r','entries'),
                'avg_mae_r'=>$this->weightedMetric($signalMetrics,'avg_mae_r','entries'),
            ],
            'by_symbol' => (clone $base)->select('symbol', DB::raw('COUNT(*) as trades'), DB::raw('SUM(realized_pnl) as realized_pnl'), DB::raw('SUM(fees) as fees'))->groupBy('symbol')->get(),
        ]]);
    }

    public function marketDataHealth(Request $request, PulseMarketDataService $market)
    {
        return response()->json(['data'=>$market->health()]);
    }

    public function marketPrices(Request $request, PulseMarketDataService $market, PulsePairAccessService $pairs)
    {
        $rawSymbols=$request->query('symbols',[]); if (is_string($rawSymbols)) $rawSymbols=array_filter(array_map('trim',explode(',',$rawSymbols)));
        $requested=collect((array)$rawSymbols)->map(fn($s)=>strtoupper(trim((string)$s)))->filter()->unique();
        $allowed=$pairs->allowedPairs($request->user())->pluck('symbol')->map(fn($s)=>strtoupper((string)$s))->values();
        $symbols=$requested->isEmpty()?$allowed:$requested->intersect($allowed)->values();
        $rows=$market->latestPrices($symbols, (int) config('pulse.market_data.read_max_age_seconds', 300))->values()->map(fn($row)=>[
            'symbol'=>$row->symbol,'price'=>(float)$row->price,'change_percent_24h'=>is_numeric($row->change_percent_24h)?(float)$row->change_percent_24h:null,
            'high_24h'=>is_numeric($row->high_24h)?(float)$row->high_24h:null,'low_24h'=>is_numeric($row->low_24h)?(float)$row->low_24h:null,
            'volume_24h'=>is_numeric($row->volume_24h)?(float)$row->volume_24h:null,'observed_at'=>$row->observed_at,'source'=>$row->source,
        ]);
        return response()->json(['data'=>$rows,'meta'=>['requested'=>$symbols->count(),'returned'=>$rows->count(),'central_snapshot'=>true]]);
    }

    public function signalValidation(Request $request, PulseSignal $signal)
    {
        abort_unless($signal->user_id === $request->user()->id, 403);
        if (! Schema::hasTable('pulse_signal_validations')) {
            return response()->json(['data'=>null,'meta'=>['schema_ready'=>false,'recovery'=>'Run php artisan abs:repair --seed.']]);
        }
        return response()->json(['data'=>$signal->validation()->first(),'meta'=>['schema_ready'=>true]]);
    }

    public function signalPerformance(Request $request)
    {
        $from=$request->date('from')?:now()->subDays(30)->startOfDay(); $to=$request->date('to')?:now()->endOfDay();
        $rows=Schema::hasTable('pulse_signal_daily_metrics')
            ? PulseSignalDailyMetric::query()->where('user_id',$request->user()->id)->whereBetween('metric_date',[$from->toDateString(),$to->toDateString()])->orderBy('metric_date')->get()
            : collect();
        $recent=Schema::hasTable('pulse_signal_validations')
            ? PulseSignalValidation::query()->where('user_id',$request->user()->id)->whereBetween('generated_at',[$from,$to])->latest('generated_at')->limit(100)->get()
            : collect();
        $wins=(int)$rows->sum('wins'); $losses=(int)$rows->sum('losses');
        return response()->json(['data'=>[
            'period'=>['from'=>$from,'to'=>$to],
            'summary'=>['signals'=>(int)$rows->sum('signals'),'entries'=>(int)$rows->sum('entries'),'wins'=>$wins,'losses'=>$losses,'ambiguous'=>(int)$rows->sum('ambiguous'),
                'expired_no_entry'=>(int)$rows->sum('expired_no_entry'),'expired_after_entry'=>(int)$rows->sum('expired_after_entry'),'decisive_win_rate'=>($wins+$losses)>0?round($wins/($wins+$losses)*100,2):null,
                'avg_mfe_r'=>$this->weightedMetric($rows,'avg_mfe_r','entries'),'avg_mae_r'=>$this->weightedMetric($rows,'avg_mae_r','entries')],
            'daily'=>$rows,'recent_detailed_validations'=>$recent,
        ],'meta'=>['schema_ready'=>Schema::hasTable('pulse_signal_daily_metrics') && Schema::hasTable('pulse_signal_validations'),'detailed_validation_retention_days'=>(int)config('pulse.validation.detailed_retention_days',7)]]);
    }

    public function strategyPerformance(Request $request)
    {
        $from=$request->date('from')?:now()->subDays(30)->startOfDay(); $to=$request->date('to')?:now()->endOfDay();
        $plan=$request->user()->pulsePlan();
        $slugs=$plan && $plan->strategies()->exists()?$plan->strategies()->pluck('pulse_strategies.slug'):PulseStrategy::query()->where('is_enabled',true)->pluck('slug');
        $rows=Schema::hasTable('pulse_strategy_daily_metrics')
            ? PulseStrategyDailyMetric::query()->whereIn('strategy_slug',$slugs)->where('market_regime','ALL')->whereBetween('metric_date',[$from->toDateString(),$to->toDateString()])->get()
            : collect();
        $groups=$rows->groupBy(fn($r)=>$r->strategy_slug.'|'.$r->strategy_version.'|'.$r->timeframe.'|'.$r->direction)->map(function($g,$key){
            [$slug,$version,$tf,$dir]=explode('|',$key,4); $wins=(int)$g->sum('wins');$losses=(int)$g->sum('losses');$samples=(int)$g->sum('sample_count');
            return ['strategy_slug'=>$slug,'strategy_version'=>$version,'timeframe'=>$tf,'direction'=>$dir,'samples'=>$samples,'wins'=>$wins,'losses'=>$losses,'ambiguous'=>(int)$g->sum('ambiguous'),
                'win_rate'=>($wins+$losses)>0?round($wins/($wins+$losses)*100,2):null,'avg_mfe_r'=>$this->weightedMetric($g,'avg_mfe_r','entries'),'avg_mae_r'=>$this->weightedMetric($g,'avg_mae_r','entries')];
        })->values();
        return response()->json(['data'=>$groups,'meta'=>['schema_ready'=>Schema::hasTable('pulse_strategy_daily_metrics'),'period'=>['from'=>$from,'to'=>$to],'learning_scope'=>'strategy+version+timeframe+direction']]);
    }

    public function learningInsights(Request $request)
    {
        $plan=$request->user()->pulsePlan();
        $slugs=$plan && $plan->strategies()->exists()?$plan->strategies()->pluck('pulse_strategies.slug'):PulseStrategy::query()->where('is_enabled',true)->pluck('slug');
        $states=Schema::hasTable('pulse_strategy_learning_states')
            ? PulseStrategyLearningState::query()->whereIn('strategy_slug',$slugs)->where('market_regime','ALL')->orderBy('strategy_slug')->orderBy('timeframe')->orderBy('direction')->get()
            : collect();
        return response()->json(['data'=>$states,'meta'=>['schema_ready'=>Schema::hasTable('pulse_strategy_learning_states'),'sample_protection'=>true,'hierarchical_fallback'=>true,'recency_weighting'=>true,'context_used_only_when_evidence_is_sufficient'=>true]]);
    }

    public function connections(Request $request)
    {
        return response()->json(['data' => BinanceConnection::query()->where('user_id', $request->user()->id)->get()->map(fn (BinanceConnection $c) => [
            'id' => $c->id, 'environment' => $c->environment, 'label' => $c->label, 'is_active' => $c->is_active,
            'masked_key' => $c->maskedKey(), 'permissions' => $c->permissions, 'last_tested_at' => $c->last_tested_at, 'last_error' => $c->last_error,
        ])]);
    }

    public function saveConnection(Request $request, PulseAuditService $audit, PulseAccessService $access)
    {
        $data = $request->validate(['environment' => ['required', 'in:testnet,live'], 'label' => ['required', 'string', 'max:100'], 'api_key' => ['required', 'string', 'max:255'], 'api_secret' => ['required', 'string', 'max:255']]);
        try { $access->assertEnvironment($request->user(), $data['environment']); }
        catch (\Throwable $e) { return response()->json(['message' => $e->getMessage()], 422); }
        $hasActive = BinanceConnection::query()->where('user_id', $request->user()->id)->where('is_active', true)->exists();
        $connection = BinanceConnection::updateOrCreate(['user_id' => $request->user()->id, 'environment' => $data['environment']], [
            'label' => $data['label'], 'api_key' => trim($data['api_key']), 'api_secret' => trim($data['api_secret']),
            'is_active' => ! $hasActive, 'permissions' => [], 'last_tested_at' => null, 'last_error' => null,
        ]);
        $audit->record('api.binance_connection_saved', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, [], $request);
        return response()->json(['message' => 'Credentials encrypted and saved.', 'data' => ['id' => $connection->id, 'environment' => $connection->environment, 'masked_key' => $connection->maskedKey()]], 201);
    }

    public function testConnection(Request $request, BinanceConnection $connection, BinanceFuturesService $binance)
    {
        abort_unless($connection->user_id === $request->user()->id, 403);
        try {
            $result = $binance->testConnection($connection);
            $connection->update(['last_tested_at' => now(), 'last_error' => null, 'permissions' => [
                'can_trade' => $result['can_trade'],
                'account_equity' => $result['total_wallet_balance'],
                'available_balance' => $result['available_balance'],
            ]]);
            return response()->json(['message' => 'Connection verified.', 'data' => $result]);
        } catch (\Throwable $e) {
            $connection->update(['last_tested_at' => now(), 'last_error' => $e->getMessage(), 'permissions' => []]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function activateConnection(Request $request, BinanceConnection $connection, PulseAccessService $access, PulseAuditService $audit)
    {
        abort_unless($connection->user_id === $request->user()->id, 403);
        try { $access->assertEnvironment($request->user(), $connection->environment); }
        catch (\Throwable $e) { return response()->json(['message' => $e->getMessage()], 422); }
        if (! $connection->last_tested_at || $connection->last_error || ! data_get($connection->permissions, 'can_trade', false)) {
            return response()->json(['message' => 'Test this Binance Futures connection successfully before activating it.'], 422);
        }
        BinanceConnection::query()->where('user_id', $request->user()->id)->update(['is_active' => false]);
        $connection->update(['is_active' => true]);
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        $settings->update(['environment' => $connection->environment]);
        $audit->record('api.binance_connection_activated', $request->user(), 'BinanceConnection', $connection->id, $connection->environment, [], $request);
        return response()->json(['message' => ucfirst($connection->environment).' Binance Futures is now active.', 'data' => ['connection_id' => $connection->id, 'environment' => $connection->environment, 'settings' => $settings->fresh()]]);
    }

    public function deleteConnection(Request $request, BinanceConnection $connection)
    {
        abort_unless($connection->user_id === $request->user()->id, 403); $connection->delete(); return response()->json(status: 204);
    }

    public function alerts(Request $request)
    {
        return response()->json(PulseAlert::query()->where('user_id', $request->user()->id)->latest()->paginate($this->perPage($request)));
    }

    public function readAlert(Request $request, PulseAlert $alert)
    {
        abort_unless($alert->user_id === $request->user()->id, 403); $alert->update(['is_read' => true]); return response()->json(['message' => 'Alert marked as read.', 'data' => $alert]);
    }

    public function readAllAlerts(Request $request)
    {
        $count = PulseAlert::query()->where('user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);
        return response()->json(['message' => 'All alerts marked as read.', 'updated' => $count]);
    }

    private function publicTradingSettings(PulseUserSetting $settings): array
    {
        return $settings->only([
            'id','environment','execution_mode','auto_trade_enabled','emergency_stop','default_leverage','margin_type','position_mode',
            'risk_per_trade_percent','sizing_mode','fixed_notional','fixed_quantity','default_order_type','take_profit_percent','stop_loss_percent',
            'daily_loss_limit','max_open_positions','notification_preferences','created_at','updated_at',
        ]);
    }

    private function publicPlanPayload(?PulsePlan $plan, ?UserServiceAccess $access, PulseMembershipService $membership): ?array
    {
        return $plan ? $membership->mobilePlanPayload($plan, $access) : null;
    }

    private function publicAccessPayload(?UserServiceAccess $access, PulseMembershipService $membership): ?array
    {
        if (! $access) return null;
        return [
            'service' => $access->service,
            'status' => $access->status,
            'starts_at' => $access->starts_at,
            'ends_at' => $access->ends_at,
            'is_active' => $access->isActive(),
            'plan' => $access->plan ? $membership->mobilePlanPayload($access->plan, $access) : null,
        ];
    }

    private function publicScannerRunPayload(PulseScannerRun $run): array
    {
        $run->loadMissing('bestSignal');
        return [
            'id' => $run->id,
            'status' => $run->status,
            'timeframe' => $run->timeframe,
            'timeframes' => ['15m','4h'],
            'pairs_scanned' => (int) $run->pairs_scanned,
            'signals_created' => (int) $run->signals_created,
            'per_signal_charge' => 0,
            'started_at' => $run->started_at,
            'completed_at' => $run->completed_at,
            'best_signal' => $run->bestSignal ? $this->publicSignalPayload($run->bestSignal) : null,
            'message' => $run->bestSignal ? 'Best Signal unlocked. Included with active package.' : ($run->status === 'completed' ? 'No qualifying Best Signal found.' : null),
        ];
    }

    private function publicSignalPayload(PulseSignal $signal): array
    {
        $evidence = collect((array) $signal->strategy_breakdown)
            ->filter(fn ($item) => is_array($item) && ! array_key_exists('_meta', $item))
            ->map(fn (array $item) => [
                'name' => $item['name'] ?? null,
                'slug' => $item['slug'] ?? null,
                'version' => $item['version'] ?? null,
                'bias' => $item['bias'] ?? null,
                'reason' => $item['reason'] ?? null,
            ])->values()->all();

        return [
            'id' => $signal->id,
            'symbol' => $signal->symbol,
            'timeframe' => $signal->timeframe,
            'direction' => $signal->direction,
            'entry_price' => $signal->entry_price,
            'stop_loss' => $signal->stop_loss,
            'take_profit' => $signal->take_profit,
            'take_profit_levels' => $signal->take_profit_levels,
            'score' => $signal->score,
            'technical_score' => $signal->technical_score,
            'reliability_score' => $signal->reliability_score,
            'confidence_score' => $signal->confidence_score,
            'confidence_label' => $signal->confidence_label,
            'status' => $signal->status,
            'unlocked_at' => $signal->unlocked_at,
            'generated_at' => $signal->generated_at,
            'expires_at' => $signal->expires_at,
            'strategy_evidence' => $evidence,
            'ai_explanation' => $signal->ai_explanation,
            'ai_explained_at' => $signal->ai_explained_at,
            'share_count' => (int) ($signal->share_count ?? 0),
        ];
    }

    private function weightedMetric($rows, string $valueField, string $weightField): ?float
    {
        $numerator = 0.0; $denominator = 0;
        foreach ($rows as $row) {
            if ($row->{$valueField} === null) continue;
            $weight = max(0, (int) ($row->{$weightField} ?? 0));
            if ($weight <= 0) continue;
            $numerator += ((float) $row->{$valueField}) * $weight;
            $denominator += $weight;
        }
        return $denominator > 0 ? $numerator / $denominator : null;
    }

    private function perPage(Request $request): int { return min(100, max(1, (int) $request->integer('per_page', 20))); }
    private function defaults(?\App\Models\User $user = null): array
    {
        return ['environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false, 'emergency_stop' => false,
            'default_leverage' => 3, 'margin_type' => 'ISOLATED', 'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1,
            'sizing_mode' => 'fixed_notional', 'fixed_notional' => 25, 'minimum_signal_score' => app(PulseSignalThresholdService::class)->packageDefault($user), 'default_order_type' => 'MARKET',
            'take_profit_percent' => 2, 'stop_loss_percent' => 1, 'daily_loss_limit' => 0, 'max_open_positions' => 2,
            'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'], 'notification_preferences' => []];
    }
}
