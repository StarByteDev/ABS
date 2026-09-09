<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulsePair;
use App\Models\BinanceConnection;
use App\Models\PulseUserSetting;
use App\Services\PulseAccessService;
use App\Services\PulseAuditService;
use App\Services\PulsePairAccessService;
use App\Services\PulsePairSelectionLockService;
use App\Services\PulseMarketDataService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request, PulseAccessService $access, PulsePairAccessService $pairAccess, PulsePairSelectionLockService $pairLock, PulseMarketDataService $market)
    {
        $user = $request->user();
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaults($user));
        $activeConnection = BinanceConnection::query()->where('user_id', $user->id)->where('environment', $settings->environment)->where('is_active', true)->first();
        $selectedCount = $pairAccess->allowedPairs($user)->count();
        $setupSteps = [
            ['key' => 'mode', 'label' => 'Trading Mode', 'ready' => in_array($settings->environment, ['testnet','live'], true), 'href' => '#environment'],
            ['key' => 'binance', 'label' => 'Binance', 'ready' => (bool) $activeConnection, 'href' => route('pulse.binance.index')],
            ['key' => 'risk', 'label' => 'Risk', 'ready' => (float) $settings->risk_per_trade_percent > 0 && (float) $settings->stop_loss_percent > 0, 'href' => '#risk-controls'],
            ['key' => 'pairs', 'label' => 'Market Universe', 'ready' => $selectedCount > 0, 'href' => route('pulse.scanner')],
            ['key' => 'ready', 'label' => 'Ready', 'ready' => (bool) $activeConnection && $selectedCount > 0 && ! $settings->emergency_stop, 'href' => route('pulse.scanner')],
        ];

        return view('pulse.settings', [
            'settings' => $settings,
            'activeConnection' => $activeConnection,
            'setupSteps' => $setupSteps,
            'marketHealth' => $market->health(),
            'pairs' => $pairAccess->allowedPairs($user),
            'pairCatalog' => $pairAccess->catalog($user, []),
            'pairSelectionLock' => ['locked' => false, 'lock_hours' => 0],
            'plan' => $user->pulsePlan(),
            'capabilities' => $access->capabilityMatrix($user),
            'practiceAllowed' => $access->systemEnabled('testnet_trading_enabled', true) && $access->allows($user, 'testnet_trading', false),
            'manualAllowed' => $access->allows($user, 'manual_trading', false),
            'liveAllowed' => $access->allows($user, 'live_trading', false),
            'automaticAllowed' => $access->allows($user, 'auto_trading', false),
            'automaticServerEnabled' => (bool) config('pulse.allow_automatic_trading', false) && $access->systemEnabled('automatic_trading_enabled', false),
            'liveServerEnabled' => (bool) config('pulse.allow_live_trading', false) && $access->systemEnabled('live_trading_enabled', false),
        ]);
    }

    public function update(Request $request, PulseAuditService $audit, PulseAccessService $access)
    {
        $plan = $request->user()->pulsePlan();
        $data = $request->validate([
            'environment' => ['required', 'in:testnet,live'],
            'execution_mode' => ['required', 'in:signal_only,manual,automatic'],
            'default_leverage' => ['required', 'integer', 'min:1', 'max:'.config('pulse.risk.max_leverage', 20)],
            'margin_type' => ['required', 'in:ISOLATED,CROSSED'],
            'position_mode' => ['required', 'in:BOTH,LONG,SHORT'],
            'risk_per_trade_percent' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'sizing_mode' => ['required', 'in:fixed_notional,fixed_quantity'],
            'fixed_notional' => ['nullable', 'numeric', 'min:1'],
            'fixed_quantity' => ['nullable', 'numeric', 'gt:0'],
            'default_order_type' => ['required', 'in:MARKET,LIMIT'],
            'take_profit_percent' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'stop_loss_percent' => ['required', 'numeric', 'min:0.1', 'max:25'],
            'daily_loss_limit' => ['nullable', 'numeric', 'min:0'],
            'max_open_positions' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        if ($data['execution_mode'] !== 'signal_only') {
            try { $access->assertEnvironment($request->user(), $data['environment']); }
            catch (\Throwable $e) { return back()->withErrors(['environment' => $e->getMessage()])->withInput(); }
        } elseif ($data['environment'] === 'live' && ! $access->allows($request->user(), 'live_trading', false)) {
            $data['environment'] = 'testnet';
        }

        $automaticRequested = $data['execution_mode'] === 'automatic' || $request->boolean('auto_trade_enabled');
        if ($automaticRequested && (! config('pulse.allow_automatic_trading', false)
            || ! $access->systemEnabled('automatic_trading_enabled', false)
            || ! $access->allows($request->user(), 'auto_trading', false))) {
            return back()->withErrors(['execution_mode' => 'Automatic trading is not enabled for this server, plan or account.'])->withInput();
        }
        if ($data['execution_mode'] === 'manual' && ! $access->allows($request->user(), 'manual_trading', false)) {
            return back()->withErrors(['execution_mode' => 'Manual trading is not enabled for this account.'])->withInput();
        }

        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults($request->user()));
        $settings->update([
            'environment' => $data['environment'],
            'execution_mode' => $data['execution_mode'],
            'auto_trade_enabled' => $automaticRequested && $data['execution_mode'] === 'automatic',
            'emergency_stop' => $request->boolean('emergency_stop'),
            'default_leverage' => min((int) $data['default_leverage'], (int) config('pulse.risk.max_leverage', 20)),
            'margin_type' => $data['margin_type'],
            'position_mode' => $data['position_mode'],
            'risk_per_trade_percent' => min((float) $data['risk_per_trade_percent'], (float) config('pulse.risk.max_risk_per_trade', 5)),
            'sizing_mode' => $data['sizing_mode'],
            'fixed_notional' => $data['fixed_notional'] ?? null,
            'fixed_quantity' => $data['fixed_quantity'] ?? null,
            'default_order_type' => $data['default_order_type'],
            'take_profit_percent' => $data['take_profit_percent'],
            'stop_loss_percent' => $data['stop_loss_percent'],
            'daily_loss_limit' => $data['daily_loss_limit'] ?? 0,
            'max_open_positions' => min((int) $data['max_open_positions'], max(1, (int) ($plan?->max_open_trades ?: 1))),
            'notification_preferences' => [
                'signals' => $request->boolean('notify_signals'), 'trades' => $request->boolean('notify_trades'),
                'risk' => $request->boolean('notify_risk'), 'system' => $request->boolean('notify_system'),
            ],
        ]);

        $audit->record('settings.updated', $request->user(), 'PulseUserSetting', $settings->id, $settings->environment, [
            'execution_mode' => $settings->execution_mode,
            'scanner_controls' => 'admin_managed_v15',
            'emergency_stop' => $settings->emergency_stop,
        ], $request);
        return back()->with('success', 'Trading and risk preferences saved. Signal markets, strategies, timeframes and qualification thresholds are controlled automatically by ABS.');
    }

    private function defaults(?\App\Models\User $user = null): array
    {
        return [
            'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false,
            'emergency_stop' => false, 'default_leverage' => 3, 'margin_type' => 'ISOLATED',
            'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1, 'sizing_mode' => 'fixed_notional',
            'fixed_notional' => 25, 'minimum_signal_score' => app(\App\Services\PulseSignalThresholdService::class)->packageDefault($user), 'default_order_type' => 'MARKET',
            'take_profit_percent' => 2, 'stop_loss_percent' => 1, 'daily_loss_limit' => 0,
            'max_open_positions' => 2, 'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
            'notification_preferences' => ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true],
        ];
    }
}
