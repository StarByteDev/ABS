<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulseMarketDataRun;
use App\Models\PulseMarketPrice;
use App\Models\PulseTrade;
use App\Models\PulseSignalValidation;
use App\Services\PulseAuditService;
use App\Services\PulseMarketDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AdminMarketDataController extends Controller
{
    public function index(PulseMarketDataService $market)
    {
        return view('admin.market-data', [
            'health' => $market->health(),
            'runs' => Schema::hasTable('pulse_market_data_runs')
                ? PulseMarketDataRun::query()->latest('id')->limit(20)->get()
                : collect(),
            'prices' => Schema::hasTable('pulse_market_prices')
                ? PulseMarketPrice::query()->orderByDesc('observed_at')->limit(30)->get()
                : collect(),
            'executionHealth' => Schema::hasTable('pulse_trades') ? [
                'active_trades' => PulseTrade::query()->whereIn('status',['submitting','pending','open','closing','protection_failed'])->count(),
                'last_trade_sync_at' => PulseTrade::query()->max('last_synced_at'),
                'stale_active_trades' => PulseTrade::query()->whereIn('status',['open','closing','protection_failed'])->where(fn($q)=>$q->whereNull('last_synced_at')->orWhere('last_synced_at','<',now()->subMinutes(3)))->count(),
                'closed_24h' => PulseTrade::query()->where('status','closed')->where('closed_at','>=',now()->subDay())->count(),
                'realized_pnl_24h' => (float) PulseTrade::query()->where('status','closed')->where('closed_at','>=',now()->subDay())->sum('realized_pnl'),
                'tp_hits_24h' => PulseTrade::query()->where('status','closed')->where('closed_at','>=',now()->subDay())->where('close_reason','take_profit')->count(),
                'sl_hits_24h' => PulseTrade::query()->where('status','closed')->where('closed_at','>=',now()->subDay())->where('close_reason','stop_loss')->count(),
            ] : [],
            'validationHealth' => Schema::hasTable('pulse_signal_validations') ? [
                'pending' => PulseSignalValidation::query()->whereNull('resolved_at')->count(),
                'last_checked_at' => PulseSignalValidation::query()->max('last_checked_at'),
                'resolved_24h' => PulseSignalValidation::query()->whereNotNull('resolved_at')->where('resolved_at','>=',now()->subDay())->count(),
            ] : [],
        ]);
    }

    public function refresh(Request $request, PulseMarketDataService $market, PulseAuditService $audit)
    {
        try {
            $run = $market->syncCentral();
            $audit->record('admin.market_data_refreshed', $request->user(), 'PulseMarketDataRun', $run->id, null, [
                'prices_updated' => $run->prices_updated,
                'candle_symbols_updated' => $run->candle_symbols_updated,
                'validation_symbols_updated' => $run->validation_symbols_updated,
            ], $request);

            return back()->with('success', 'Central ABS market feed refreshed from Binance Futures and stored in the ABS database.');
        } catch (\Throwable $e) {
            return back()->withErrors(['market_data' => 'Market refresh failed: '.$e->getMessage()]);
        }
    }
}
