<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulseMarketDataRun;
use App\Models\PulseMarketPrice;
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
