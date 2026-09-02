<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseMembershipRequest;
use App\Models\PulsePlan;
use App\Services\PulseMembershipService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request, PulseMembershipService $membership)
    {
        $access = $request->user()->pulseAccess()->with('plan')->first();
        $trialPlan = PulsePlan::query()->where('is_active', true)->where('is_trial', true)->orderBy('sort_order')->first();

        return view('pulse.plans', [
            'plans' => $membership->upgradePathPlans($access),
            'access' => $access,
            'nextPlan' => $membership->nextUpgradePlan($access),
            'trialPlan' => $trialPlan,
            'commerce' => $membership->settings(),
            'requests' => PulseMembershipRequest::query()->where('user_id', $request->user()->id)->with('plan')->latest()->limit(5)->get(),
        ]);
    }
}
