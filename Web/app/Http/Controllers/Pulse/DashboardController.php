<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseAlert;
use App\Services\PulsePageDataService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PulsePageDataService $pages)
    {
        return view('pulse.dashboard', [
            'page' => $pages->dashboard($request->user(), (string) $request->query('period', '30d')),
            'unreadAlerts' => PulseAlert::query()
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->count(),
        ]);
    }
}
