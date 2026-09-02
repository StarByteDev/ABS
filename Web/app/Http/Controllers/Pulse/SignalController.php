<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseSignal;
use App\Models\PulseUserSetting;
use App\Services\PulseTradeService;
use App\Services\PulsePageDataService;
use Illuminate\Http\Request;

class SignalController extends Controller
{
    public function index(Request $request, PulsePageDataService $pages)
    {
        return view('pulse.signals.index', [
            'page' => $pages->signals($request->user(), $request->only([
                'status', 'direction', 'symbol', 'timeframe', 'strategy', 'min_score', 'selected',
            ])),
        ]);
    }

    public function dismiss(Request $request, PulseSignal $signal, PulsePageDataService $pages)
    {
        $pages->dismissSignal($request->user(), $signal);

        return redirect()->route('pulse.signals.index')->with('success', 'Signal dismissed. It remains available in signal history.');
    }

    public function show(Request $request, PulseSignal $signal)
    {
        abort_unless($signal->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        return view('pulse.signals.show', [
            'signal' => $signal,
            'settings' => PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], [
                'environment' => 'testnet', 'default_leverage' => 3, 'default_order_type' => 'MARKET',
                'position_mode' => 'BOTH', 'fixed_notional' => 25,
            ]),
        ]);
    }

    public function execute(Request $request, PulseSignal $signal, PulseTradeService $trades)
    {
        abort_unless($signal->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
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
            return redirect()->route('pulse.signals.index', ['selected' => $signal->id, 'open_trade' => $signal->id])
                ->withErrors(['execution' => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('pulse.signals.index', ['selected' => $signal->id])
            ->with('success', 'Trade submitted to Binance. Pulse will track the order, entry and TP/SL protection automatically; use Monitor Trade from this signal when needed.');
    }
}
