<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseMembershipRequest;
use App\Models\PulsePlan;
use App\Models\PulsePromotionCode;
use App\Services\PulseAuditService;
use App\Services\BrandedMailService;
use App\Services\PulseMembershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MembershipController extends Controller
{
    public function index(Request $request, PulseMembershipService $membership)
    {
        $access = $request->user()->pulseAccess()->with('plan')->first();

        return view('pulse.membership.index', [
            'access' => $access,
            'plans' => $membership->upgradePathPlans($access),
            'nextPlan' => $membership->nextUpgradePlan($access),
            'requests' => PulseMembershipRequest::query()->where('user_id', $request->user()->id)->with(['plan', 'promotion'])->latest()->limit(20)->get(),
            'assignedPromotions' => $this->assignedPromotions($request->user()->id),
            'commerce' => $membership->settings(),
        ]);
    }

    public function checkout(Request $request, PulsePlan $plan, PulseMembershipService $membership)
    {
        $this->assertRequestable($plan);
        $commerce = $membership->settings();
        abort_unless($commerce['requests_enabled'], 404);

        return view('pulse.membership.checkout', [
            'plan' => $plan,
            'commerce' => $commerce,
            'quote' => $membership->quote($plan),
            'assignedPromotions' => $commerce['promotions_enabled'] ? $this->assignedPromotions($request->user()->id, $plan->id) : collect(),
        ]);
    }

    public function store(Request $request, PulseMembershipService $membership, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate([
            'pulse_plan_id' => ['required', 'integer', Rule::exists('pulse_plans', 'id')],
            'payment_reference' => ['required', 'string', 'min:6', 'max:190', Rule::unique('pulse_membership_requests', 'payment_reference')],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:8192'],
            'promotion_code' => ['nullable', 'string', 'max:80'],
            'user_notes' => ['nullable', 'string', 'max:2000'],
            'risk_acknowledgement' => ['accepted'],
        ]);

        $plan = PulsePlan::query()->findOrFail((int) $data['pulse_plan_id']);
        $this->assertRequestable($plan);
        $commerce = $membership->settings();
        if (! $commerce['requests_enabled']) {
            throw ValidationException::withMessages(['membership' => 'New Pulse package payment requests are temporarily paused.']);
        }
        if ((float) $plan->effectiveMonthlyPrice() > 0 && ($commerce['wallet_address'] === '' || $commerce['network'] === '')) {
            throw ValidationException::withMessages(['membership' => 'USDT payment details are not configured. Please contact support before transferring funds.']);
        }
        if ($commerce['proof_required'] && ! $request->hasFile('payment_proof')) {
            throw ValidationException::withMessages(['payment_proof' => 'Payment proof is required for this package request.']);
        }

        $promotion = null;
        if ($commerce['promotions_enabled'] && trim((string) ($data['promotion_code'] ?? '')) !== '') {
            $promotion = $membership->resolvePromotion((string) $data['promotion_code'], $request->user(), $plan);
        }
        $quote = $membership->quote($plan, $promotion);
        $membershipRequest = $membership->createRequest($request, $request->user(), $plan, $promotion, $quote, $commerce);

        $audit->record('membership.request_submitted', $request->user(), 'PulseMembershipRequest', $membershipRequest->id, null, [
            'plan_id' => $plan->id,
            'amount' => $quote['final_amount'],
            'currency' => $quote['currency'],
            'network' => $commerce['network'],
        ], $request);
        $mail->adminNewSubscription($membershipRequest, 'web');
        $mail->planRequestReceived($membershipRequest);

        return redirect()->route('pulse.membership.index')->with('success', 'Payment submitted for Admin verification. Your Pulse package will activate after the USDT transaction is approved.');
    }

    public function cancel(Request $request, PulseMembershipRequest $membershipRequest, PulseAuditService $audit)
    {
        abort_unless((int) $membershipRequest->user_id === (int) $request->user()->id, 403);
        if (! $membershipRequest->isOpen()) {
            return back()->withErrors(['membership' => 'Only an open Pulse plan request can be cancelled.']);
        }

        $membershipRequest->update(['status' => 'cancelled']);
        $audit->record('membership.request_cancelled', $request->user(), 'PulseMembershipRequest', $membershipRequest->id, null, [], $request);

        return back()->with('success', 'Pulse plan request cancelled.');
    }

    private function assignedPromotions(int $userId, ?int $planId = null)
    {
        return PulsePromotionCode::query()
            ->where('assigned_user_id', $userId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->when($planId, fn ($q) => $q->where(fn ($x) => $x->whereNull('applicable_plan_id')->orWhere('applicable_plan_id', $planId)))
            ->with('plan')
            ->latest()
            ->get()
            ->filter(function (PulsePromotionCode $promotion) use ($userId): bool {
                if ($promotion->max_uses > 0 && $promotion->redemptions()->count() >= $promotion->max_uses) return false;
                if ($promotion->per_user_limit > 0 && $promotion->redemptions()->where('user_id', $userId)->count() >= $promotion->per_user_limit) return false;
                return true;
            })
            ->values();
    }

    private function assertRequestable(PulsePlan $plan): void
    {
        abort_unless($plan->is_active && $plan->is_public && ! $plan->is_trial && $plan->request_enabled, 404);
    }
}
