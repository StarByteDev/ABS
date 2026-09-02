<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Services\PulsePageDataService;
use Illuminate\Http\Request;

class StrategyController extends Controller
{
    public function __invoke(Request $request, PulsePageDataService $pages)
    {
        return view('pulse.strategies', [
            'page' => $pages->strategies($request->user(), (string) $request->query('period', '30d')),
        ]);
    }
}
