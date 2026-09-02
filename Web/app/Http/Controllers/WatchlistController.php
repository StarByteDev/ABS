<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['symbol' => ['required', 'string', 'max:30'], 'display_name' => ['nullable', 'string', 'max:100']]);
        $request->user()->watchlists()->updateOrCreate(['symbol' => strtoupper($data['symbol'])], ['display_name' => $data['display_name'] ?? null]);
        return back()->with('success', 'Watchlist updated.');
    }

    public function destroy(Request $request, string $symbol)
    {
        $request->user()->watchlists()->where('symbol', strtoupper($symbol))->delete();
        return back()->with('success', 'Asset removed from your watchlist.');
    }
}
