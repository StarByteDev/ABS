<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MarketDataService;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function overview(MarketDataService $market, Request $request)
    {
        return response()->json([
            'data' => $market->overview($request->boolean('refresh')),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function movers(MarketDataService $market, Request $request)
    {
        return response()->json([
            'data' => $market->movers(
                (int) $request->integer('limit', 5),
                $request->boolean('refresh'),
            ),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function chart(MarketDataService $market, Request $request, string $symbol)
    {
        return response()->json([
            'data' => $market->chart(
                $symbol,
                (string) $request->query('interval', '1h'),
                (int) $request->integer('limit', 80),
                $request->boolean('refresh'),
            ),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
