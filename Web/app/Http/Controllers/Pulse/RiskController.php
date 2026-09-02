<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseTrade;
use App\Models\PulseUserSetting;
use App\Services\PulseAuditService;
use Illuminate\Http\Request;

class RiskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], $this->defaults());
        $plan = $user->pulsePlan();
        $openQuery = PulseTrade::query()->where('user_id', $user->id)
            ->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed']);
        $openCount = (clone $openQuery)->count();
        $planMaxOpen = max(0, (int) ($plan?->max_open_trades ?? 0));
        $maxOpen = $planMaxOpen > 0 ? max(1, min((int) $settings->max_open_positions, $planMaxOpen)) : 0;
        $todayPnl = (float) PulseTrade::query()
            ->where('user_id', $user->id)
            ->where('status', 'closed')
            ->whereDate('closed_at', today())
            ->sum('realized_pnl');

        return view('pulse.risk', [
            'settings' => $settings,
            'plan' => $plan,
            'openTrades' => (clone $openQuery)->latest()->get(),
            'openCount' => $openCount,
            'maxOpen' => $maxOpen,
            'planMaxOpen' => $planMaxOpen,
            'riskUtilization' => $maxOpen > 0 ? min(100, (int) round(($openCount / $maxOpen) * 100)) : 0,
            'todayPnl' => $todayPnl,
            'protectionIssues' => (clone $openQuery)->whereIn('protection_status', ['failed', 'review_required'])->count(),
        ]);
    }

    public function update(Request $request, PulseAuditService $audit)
    {
        $plan = $request->user()->pulsePlan();
        $data = $request->validate([
            'risk_per_trade_percent' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'daily_loss_limit' => ['nullable', 'numeric', 'min:0'],
            'take_profit_percent' => ['required', 'numeric', 'min:0.1', 'max:50'],
            'stop_loss_percent' => ['required', 'numeric', 'min:0.1', 'max:25'],
            'max_open_positions' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], $this->defaults());
        $settings->update([
            'risk_per_trade_percent' => min((float) $data['risk_per_trade_percent'], (float) config('pulse.risk.max_risk_per_trade', 5)),
            'daily_loss_limit' => (float) ($data['daily_loss_limit'] ?? 0),
            'take_profit_percent' => (float) $data['take_profit_percent'],
            'stop_loss_percent' => (float) $data['stop_loss_percent'],
            'max_open_positions' => min((int) $data['max_open_positions'], max(1, (int) ($plan?->max_open_trades ?: 1))),
        ]);

        $audit->record('risk.settings_updated', $request->user(), 'PulseUserSetting', $settings->id, $settings->environment, [
            'risk_per_trade_percent' => $settings->risk_per_trade_percent,
            'daily_loss_limit' => $settings->daily_loss_limit,
            'max_open_positions' => $settings->max_open_positions,
        ], $request);

        return back()->with('success', 'Pulse risk controls were updated. Existing exchange orders and positions were not changed.');
    }

    private function defaults(): array
    {
        return [
            'environment' => 'testnet', 'execution_mode' => 'signal_only', 'auto_trade_enabled' => false,
            'emergency_stop' => false, 'default_leverage' => 3, 'margin_type' => 'ISOLATED',
            'position_mode' => 'BOTH', 'risk_per_trade_percent' => 1, 'sizing_mode' => 'fixed_notional',
            'fixed_notional' => 25, 'minimum_signal_score' => 70, 'default_order_type' => 'MARKET',
            'take_profit_percent' => 2, 'stop_loss_percent' => 1, 'daily_loss_limit' => 0,
            'max_open_positions' => 2, 'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
            'notification_preferences' => ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true],
        ];
    }
}
