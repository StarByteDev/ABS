<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseAlert;
use App\Models\PulseUserSetting;
use App\Services\MarketDataService;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request, MarketDataService $market)
    {
        $user = $request->user();
        $storedWatchlist = $user->watchlists()->orderBy('sort_order')->orderBy('symbol')->get();
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $user->id], [
            'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
            'notification_preferences' => ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true],
        ]);
        $storedSymbols = $storedWatchlist->pluck('symbol')->map(fn ($symbol) => strtoupper((string) $symbol));
        $symbols = $storedSymbols;
        if ($symbols->isEmpty()) {
            $symbols = collect($settings->selected_pairs ?? []);
        }

        $overview = $market->cachedOverview();
        $marketBySymbol = collect($overview['core'] ?? [])->keyBy('symbol');
        $watchlist = $symbols->map(function ($symbol) use ($marketBySymbol, $storedSymbols) {
            $symbol = strtoupper((string) $symbol);
            $row = $marketBySymbol->get($symbol, []);
            return [
                'symbol' => $symbol,
                'pair' => $row['pair'] ?? str_replace('USDT', '/USDT', $symbol),
                'price' => $row['price'] ?? null,
                'change_percent' => $row['change_percent'] ?? null,
                'is_stored' => $storedSymbols->contains($symbol),
            ];
        })->values();

        return view('pulse.alerts', [
            'alerts' => PulseAlert::query()->where('user_id', $user->id)->latest()->paginate(25),
            'watchlist' => $watchlist,
            'storedWatchlist' => $storedWatchlist,
            'settings' => $settings,
            'marketUpdatedAt' => data_get($overview, 'updated_at'),
        ]);
    }

    public function read(Request $request, PulseAlert $alert)
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
        $alert->update(['is_read' => true]);

        return back()->with('success', 'Alert marked as read.');
    }

    public function readAll(Request $request)
    {
        PulseAlert::query()->where('user_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'All Pulse alerts were marked as read.');
    }
}
