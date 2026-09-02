<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulsePlan;
use App\Services\PulseMembershipService;
use Illuminate\Http\Request;

class EntryController extends Controller
{
    public function __invoke(Request $request, PulseMembershipService $membership)
    {
        if ($request->user()?->hasPulseAccess()) {
            return redirect()->route('pulse.dashboard');
        }

        return view('pulse.gateway', [
            'plans' => PulsePlan::query()->publiclyAvailable()->orderByDesc('is_featured')->orderBy('sort_order')->get(),
            'trialPlan' => PulsePlan::query()->where('is_active', true)->where('is_trial', true)->orderBy('sort_order')->first(),
            'commerce' => $membership->settings(),
            'hasAccount' => $request->user() !== null,
            'access' => $request->user()?->pulseAccess()->with('plan')->first(),
        ]);
    }

    public function access(Request $request)
    {
        $access = $request->user()->pulseAccess()->with('plan')->first();

        return view('pulse.access', [
            'access' => $access,
            'plans' => PulsePlan::query()->publiclyAvailable()->orderByDesc('is_featured')->orderBy('sort_order')->get(),
        ]);
    }
}
