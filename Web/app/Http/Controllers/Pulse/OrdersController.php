<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Services\PulseTradeService;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function __invoke(Request $request, PulseTradeService $trades)
    {
        $snapshot = null;
        $error = null;
        try { $snapshot = $trades->exchangeSnapshot($request->user(), $request->string('environment')->toString() ?: null); }
        catch (\Throwable $e) { $error = $e->getMessage(); }

        return view('pulse.orders', compact('snapshot', 'error'));
    }
}
