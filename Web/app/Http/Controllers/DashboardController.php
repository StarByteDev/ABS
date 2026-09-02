<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // V14.7.9: /dashboard is compatibility-only. The legacy account
        // dashboard is never rendered for an authenticated customer.
        if ($user->hasPulseAccess()) {
            return redirect()->route('pulse.dashboard');
        }

        return redirect()->route('pulse.access');
    }
}
