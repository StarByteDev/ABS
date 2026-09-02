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
use App\Services\BinanceFuturesService;
use App\Services\BrandedMailService;
use App\Services\PulseAccessService;
use App\Services\PulseAuditService;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PulseController extends Controller
{
    public function access(Request $request, PulseAccessService $accessService, PulseMembershipService $membership)
    {
        $access = $request->user()->pulseAccess()->with('plan.strategies')->first();
        return response()->json([
            'has_access' => $request->user()->hasPulseAccess(),
            'access' => $access,
            'plan' => $access?->plan,
            'next_upgrade_plan' => $membership->nextUpgradePlan($access),
            'effective_capabilities' => $accessService->capabilityMatrix($request->user()),
        ]);
    }

    public function dashboard(Request $request, PulseAccessService $accessService, PulsePageDataService $pages, PulseUsageService $usage)
    {
        $user = $request->user();
        $trades = PulseTrade::query()->where('user_id', $user->id);
        $summary = $pages->dashboard($user, (string) $request->query('period', '30d'));
        return response()->json([
            'settings' => PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaults($user)),
            'access' => $user->pulseAccess()->with('plan')->first(),
            'active_signals' => PulseSignal::query()->where('user_id', $user->id)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest('generated_at')->limit(10)->get(),
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
        return response()->json(['data' => $plans, 'next_upgrade_plan' => $membership->nextUpgradePlan($access)]);
    }
    public function membership(Request $request, PulseMembershipService $membership)
    {
        $commerce=$membership->settings();
        $access=$request->user()->pulseAccess()->with('plan')->first();
        return response()->json(['data'=>[
            'access'=>$access,
            'next_upgrade_plan'=>$membership->nextUpgradePlan($access),
            'plans'=>$membership->upgradePathPlans($access)->map(fn(PulsePlan $plan)=>$membership->mobilePlanPayload($plan,$access)),
            'requests'=>PulseMembershipRequest::query()->where('user_id',$request->user()->id)->with(['plan','promotion'])->latest()->limit(20)->get(),
            'assigned_promotions'=>PulsePromotionCode::query()->where('assigned_user_id',$request->user()->id)->where('is_active',true)
                ->where(fn($q)=>$q->whereNull('valid_from')->orWhere('valid_from','<=',now()))
                ->where(fn($q)=>$q->whereNull('valid_until')->orWhere('valid_until','>',now()))
                ->with('plan')->latest()->get()->map(fn(PulsePromotionCode $promo)=>[
                    'code'=>$promo->code,'label'=>$promo->label,'type'=>$promo->type,'benefit'=>$promo->displayBenefit(),
                    'applicable_plan_id'=>$promo->applicable_plan_id,'plan'=>$promo->plan?->only(['id','name','slug']),'access_days'=>$promo->access_days,'valid_until'=>$promo->valid_until,
                ]),
            'commerce'=>[
                'requests_enabled'=>$commerce['requests_enabled'],
                'promotions_enabled'=>$commerce['promotions_enabled'],
                'wallet_address'=>$commerce['wallet_address'],
                'network'=>$commerce['network'],
                'payment_instructions'=>$commerce['payment_instructions'],
                'proof_required'=>$commerce['proof_required'],
            ],
        ]]);
    }

    public function membershipQuote(Request $request, PulseMembershipService $membership)
    {
        $data=$request->validate(['pulse_plan_id'=>['required','integer','exists:pulse_plans,id'],'promotion_code'=>['nullable','string','max:80']]);
        $plan=PulsePlan::query()->findOrFail((int)$data['pulse_plan_id']);
        abort_unless($plan->is_active && $plan->is_public && ! $plan->is_trial && $plan->request_enabled,404);
        $commerce=$membership->settings();
        if(!$commerce['requests_enabled']) throw ValidationException::withMessages(['membership'=>'New Pulse plan requests are temporarily unavailable.']);
        $promotion=$membership->resolvePromotion($data['promotion_code']??null,$request->user(),$plan);
        $quote=$membership->quote($plan,$promotion);
        return response()->json(['data'=>['plan'=>$plan,'promotion'=>$promotion?->only(['id','code','label','type','discount_type','discount_value','access_days']),'quote'=>$quote,'payment'=>[
            'wallet_address'=>$commerce['wallet_address'],'network'=>$commerce['network'],'instructions'=>$commerce['payment_instructions'],'proof_required'=>$commerce['proof_required'],
        ]]]);
    }

    public function submitMembershipRequest(Request $request, PulseMembershipService $membership, PulseAuditService $audit, BrandedMailService $mail)
    {
        if($request->filled('payment_reference')) $request->merge(['payment_reference'=>trim((string)$request->input('payment_reference'))]);
        $data=$request->validate([
            'pulse_plan_id'=>['required','integer','exists:pulse_plans,id'],
            'promotion_code'=>['nullable','string','max:80'],
            'payment_reference'=>['nullable','string','max:190',Rule::unique('pulse_membership_requests','payment_reference')],
            'payment_proof'=>['nullable','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'user_notes'=>['nullable','string','max:2000'],
        ]);
        $plan=PulsePlan::query()->findOrFail((int)$data['pulse_plan_id']);
        abort_unless($plan->is_active && $plan->is_public && ! $plan->is_trial && $plan->request_enabled,404);
        $commerce=$membership->settings();
        if(!$commerce['requests_enabled']) throw ValidationException::withMessages(['membership'=>'New Pulse plan requests are temporarily unavailable.']);
        $promotion=$membership->resolvePromotion($data['promotion_code']??null,$request->user(),$plan);
        $quote=$membership->quote($plan,$promotion);
        $freeVoucher=$promotion && $promotion->type==='gift_voucher' && $promotion->discount_type==='full';
        if($plan->requires_payment && (float)$quote['base_amount']<=0 && !$freeVoucher) throw ValidationException::withMessages(['membership'=>'The plan rate is not currently published.']);
        $requiresTransfer=$plan->requires_payment && (float)$quote['final_amount']>0;
        if($requiresTransfer && ($commerce['wallet_address']==='' || $commerce['network']==='')) throw ValidationException::withMessages(['membership'=>'Payment details are not currently published.']);
        if($requiresTransfer && blank($data['payment_reference']??null)) throw ValidationException::withMessages(['payment_reference'=>'Enter the USDT transaction reference or transaction hash.']);
        if($requiresTransfer && $commerce['proof_required'] && !$request->hasFile('payment_proof')) throw ValidationException::withMessages(['payment_proof'=>'Upload payment proof to submit this Pulse plan request.']);

        $item=$membership->createRequest($request,$request->user(),$plan,$promotion,$quote,$commerce);
        $audit->record('api.membership_request_submitted',$request->user(),'PulseMembershipRequest',$item->id,null,[
            'plan_id'=>$plan->id,'final_amount'=>(float)$item->final_amount,'currency'=>$item->currency,'promotion'=>$promotion?->code,'status'=>$item->status,
        ],$request);
        $mail->adminNewSubscription($item, 'mobile_api');
        if($item->status==='approved') $mail->planActivated($item);
        else $mail->planRequestReceived($item);
        return response()->json(['message'=>$item->status==='approved'?'Pulse access activated.':'Pulse plan request submitted for verification.','data'=>$item->fresh(['plan','promotion'])],201);
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
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        return response()->json(['data' => $pairAccess->catalog($request->user(), $settings->selected_pairs ?? [])]);
    }

    public function strategies(Request $request)
    {
        $plan = $request->user()->pulsePlan();
        $includedIds = $plan
            ? $plan->strategies()->where('pulse_strategies.is_enabled', true)->wherePivot('is_enabled', true)->pluck('pulse_strategies.id')->map(fn ($id) => (int) $id)->all()
            : [];
        $catalog = PulseStrategy::query()->where('is_enabled', true)->orderBy('sort_order')->get()->map(function (PulseStrategy $strategy) use ($plan, $includedIds) {
            $included = ! $plan || in_array((int) $strategy->id, $includedIds, true);
            return [
                'id' => $strategy->id,
                'name' => $strategy->name,
                'slug' => $strategy->slug,
                'description' => $strategy->description,
                'timeframe' => $strategy->timeframe,
                'weight' => $strategy->weight,
                'minimum_score' => $strategy->minimum_score,
                'settings' => $strategy->settings,
                'sort_order' => $strategy->sort_order,
                'included' => $included,
                'status' => $included ? 'included' : 'locked_by_plan',
            ];
        })->values();

        return response()->json(['data' => [
            'plan' => $plan?->only(['id','name','slug']),
            'total_strategies' => $catalog->count(),
            'included_strategies' => $catalog->where('included', true)->count(),
            'strategies' => $catalog,
        ]]);
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

    public function settings(Request $request, PulseAccessService $accessService, PulsePairSelectionLockService $pairLock, PulseSignalThresholdService $thresholds)
    {
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        $effectiveThreshold = $thresholds->resolve($request->user(), $settings);
        return response()->json([
            'data' => $settings,
            'effective_minimum_score' => $effectiveThreshold['score'],
            'minimum_score_source' => $effectiveThreshold['source'],
            'pair_selection_lock' => $pairLock->status($settings),
            'effective_capabilities' => $accessService->capabilityMatrix($request->user()),
        ]);
    }

    public function usage(Request $request, PulseUsageService $usage)
    {
        return response()->json(['data' => $usage->today($request->user())]);
    }

    public function updateSettings(Request $request, PulseAuditService $audit, PulseAccessService $access, PulsePairAccessService $pairAccess, PulsePairSelectionLockService $pairLock)
    {
        $plan = $request->user()->pulsePlan();
        $data = $request->validate([
            'environment' => ['required', 'in:testnet,live'], 'execution_mode' => ['required', 'in:signal_only,manual,automatic'],
            'auto_trade_enabled' => ['sometimes', 'boolean'], 'emergency_stop' => ['sometimes', 'boolean'],
            'default_leverage' => ['required', 'integer', 'min:1', 'max:'.config('pulse.risk.max_leverage', 20)], 'margin_type' => ['required', 'in:ISOLATED,CROSSED'],
            'position_mode' => ['required', 'in:BOTH,LONG,SHORT'], 'risk_per_trade_percent' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'sizing_mode' => ['required', 'in:fixed_notional,fixed_quantity'], 'fixed_notional' => ['nullable', 'numeric', 'min:1'],
            'fixed_quantity' => ['nullable', 'numeric', 'gt:0'], 'minimum_signal_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_order_type' => ['required', 'in:MARKET,LIMIT'], 'take_profit_percent' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'stop_loss_percent' => ['required', 'numeric', 'min:0.1', 'max:25'], 'daily_loss_limit' => ['nullable', 'numeric', 'min:0'],
            'max_open_positions' => ['required', 'integer', 'min:1', 'max:20'], 'selected_pairs' => ['nullable', 'array'],
            'selected_pairs.*' => ['string', 'max:30', 'exists:pulse_pairs,symbol'], 'notification_preferences' => ['nullable', 'array'],
        ]);
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        $selectedInput = array_key_exists('selected_pairs', $data) ? (array) $data['selected_pairs'] : (array) ($settings->selected_pairs ?? []);
        $selected = array_values(array_unique(array_map('strtoupper', $selectedInput)));
        $maxPairs = max(1, (int) ($plan?->max_selected_pairs ?: 5));
        if (count($selected) > $maxPairs) return response()->json(['message' => "The current plan allows {$maxPairs} selected pairs."], 422);
        $allowedSymbols = $pairAccess->allowedSymbols($request->user());
        $notAllowed = array_values(array_diff($selected, $allowedSymbols));
        if ($notAllowed !== []) return response()->json(['message' => 'One or more selected markets are not included in the current Pulse plan.', 'invalid_symbols' => $notAllowed], 422);
        $eligibleCurrent = array_values(array_intersect((array) ($settings->selected_pairs ?? []), $allowedSymbols));
        try {
            $pairLockAttributes = $pairLock->guardAndAttributes($settings, $selected, $eligibleCurrent);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'pair_selection_lock' => $pairLock->status($settings)], 423);
        }
        if ($data['execution_mode'] !== 'signal_only') {
            try { $access->assertEnvironment($request->user(), $data['environment']); }
            catch (\Throwable $e) { return response()->json(['message' => $e->getMessage()], 422); }
        } elseif ($data['environment'] === 'live' && ! $access->allows($request->user(), 'live_trading', false)) {
            $data['environment'] = 'testnet';
        }

        $automatic = $data['execution_mode'] === 'automatic' || (bool) ($data['auto_trade_enabled'] ?? false);
        if ($automatic && (! config('pulse.allow_automatic_trading', false) || ! $access->allows($request->user(), 'auto_trading', false))) {
            return response()->json(['message' => 'Automatic trading is not enabled for this installation or account.'], 422);
        }
        if ($data['execution_mode'] === 'manual' && ! $access->allows($request->user(), 'manual_trading', false)) {
            return response()->json(['message' => 'Manual trading is not included in the current Pulse plan.'], 422);
        }

        $settings->update(array_merge([
            'environment' => $data['environment'], 'execution_mode' => $data['execution_mode'],
            'auto_trade_enabled' => $automatic && $data['execution_mode'] === 'automatic', 'emergency_stop' => (bool) ($data['emergency_stop'] ?? false),
            'default_leverage' => min((int) $data['default_leverage'], (int) config('pulse.risk.max_leverage', 20)),
            'margin_type' => $data['margin_type'], 'position_mode' => $data['position_mode'],
            'risk_per_trade_percent' => min((float) $data['risk_per_trade_percent'], (float) config('pulse.risk.max_risk_per_trade', 5)),
            'sizing_mode' => $data['sizing_mode'], 'fixed_notional' => $data['fixed_notional'] ?? null,
            'fixed_quantity' => $data['fixed_quantity'] ?? null, 'minimum_signal_score' => $data['minimum_signal_score'],
            'default_order_type' => $data['default_order_type'], 'take_profit_percent' => $data['take_profit_percent'],
            'stop_loss_percent' => $data['stop_loss_percent'], 'daily_loss_limit' => $data['daily_loss_limit'] ?? 0,
            'max_open_positions' => min((int) $data['max_open_positions'], max(1, (int) ($plan?->max_open_trades ?: 1))),
            'selected_pairs' => $selected, 'notification_preferences' => $data['notification_preferences'] ?? [],
        ], $pairLockAttributes));
        $audit->record('api.settings_updated', $request->user(), 'PulseUserSetting', $settings->id, $settings->environment, ['selected_pairs' => $selected, 'pair_selection_locked_until' => $settings->pair_selection_locked_until?->toIso8601String()], $request);
        return response()->json(['message' => 'Pulse settings updated.', 'data' => $settings, 'pair_selection_lock' => $pairLock->status($settings)]);
    }

    public function scannerRuns(Request $request)
    {
        return response()->json(PulseScannerRun::query()->where('user_id', $request->user()->id)->latest()->paginate($this->perPage($request)));
    }

    public function scannerOverview(Request $request, PulsePageDataService $pages, PulseUsageService $usage)
    {
        return response()->json(['data' => $pages->scanner($request->user(), $request->only([
            'quote', 'direction', 'strategy', 'min_score', 'timeframe', 'liquidity', 'symbol',
        ])), 'usage' => $usage->today($request->user())]);
    }

    public function runScanner(Request $request, PulseScannerService $scanner, PulseUsageService $usage)
    {
        $data = $request->validate(['symbols' => ['nullable', 'array', 'max:'.max(1, (int) config('pulse.scanner.max_pairs_per_run', 1000))], 'symbols.*' => ['string', 'max:30'], 'timeframe' => ['required', 'in:15m,4h,all']]);
        try {
            $run = $scanner->run($request->user(), array_key_exists('symbols', $data) ? $data['symbols'] : null, $data['timeframe']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage(), 'usage' => $usage->today($request->user())], 422);
        }
        return response()->json(['message' => 'Scanner completed.', 'data' => $run, 'usage' => $usage->today($request->user())], 201);
    }

    public function signals(Request $request)
    {
        $query = PulseSignal::query()->where('user_id', $request->user()->id)->latest('generated_at');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('direction')) $query->where('direction', $request->string('direction'));
        if ($request->filled('symbol')) $query->where('symbol', strtoupper((string) $request->string('symbol')));
        return response()->json($query->paginate($this->perPage($request)));
    }

    public function signalsOverview(Request $request, PulsePageDataService $pages, PulseUsageService $usage)
    {
        return response()->json(['data' => $pages->signals($request->user(), $request->only([
            'status', 'direction', 'symbol', 'timeframe', 'strategy', 'min_score', 'selected',
        ])), 'usage' => $usage->today($request->user())]);
    }

    public function signal(Request $request, PulseSignal $signal)
    {
        abort_unless($signal->user_id === $request->user()->id, 403);
        $signal->load('trades');
        if (Schema::hasTable('pulse_signal_validations')) $signal->load('validation');
        return response()->json(['data' => $signal]);
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
