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
        $settings = $membership->settings();
        if (! $settings['requests_enabled']) {
            throw ValidationException::withMessages(['membership' => 'New Pulse plan requests are temporarily unavailable.']);
        }

        $promotion = null;
        if ($request->filled('promo')) {
            $promotion = $membership->resolvePromotion((string) $request->query('promo'), $request->user(), $plan);
        }
        $quote = $membership->quote($plan, $promotion);

        return view('pulse.membership.checkout', [
            'plan' => $plan,
            'promotion' => $promotion,
            'quote' => $quote,
            'commerce' => $settings,
            'access' => $request->user()->pulseAccess()->with('plan')->first(),
            'assignedPromotions' => $this->assignedPromotions($request->user()->id, $plan->id),
        ]);
    }

    public function store(Request $request, PulseMembershipService $membership, PulseAuditService $audit, BrandedMailService $mail)
    {
        if ($request->filled('payment_reference')) {
            $request->merge(['payment_reference' => trim((string) $request->input('payment_reference'))]);
        }
        $data = $request->validate([
            'pulse_plan_id' => ['required', 'integer', 'exists:pulse_plans,id'],
            'promotion_code' => ['nullable', 'string', 'max:80'],
            'payment_reference' => ['nullable', 'string', 'max:190', Rule::unique('pulse_membership_requests', 'payment_reference')],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'user_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = PulsePlan::query()->findOrFail((int) $data['pulse_plan_id']);
        $this->assertRequestable($plan);
        $settings = $membership->settings();
        if (! $settings['requests_enabled']) {
            throw ValidationException::withMessages(['membership' => 'New Pulse plan requests are temporarily unavailable.']);
        }

        $promotion = $membership->resolvePromotion($data['promotion_code'] ?? null, $request->user(), $plan);
        $quote = $membership->quote($plan, $promotion);
        $freeVoucher = $promotion && $promotion->type === 'gift_voucher' && $promotion->discount_type === 'full';
        if ($plan->requires_payment && (float) $quote['base_amount'] <= 0 && ! $freeVoucher) {
            throw ValidationException::withMessages(['membership' => 'The plan rate is not currently published. Please try again later or contact support.']);
        }
        $requiresTransfer = $plan->requires_payment && (float) $quote['final_amount'] > 0;

        if ($requiresTransfer && ($settings['wallet_address'] === '' || $settings['network'] === '')) {
            throw ValidationException::withMessages(['membership' => 'Payment details are not currently published. Please try again later or contact support.']);
        }
        if ($requiresTransfer && blank($data['payment_reference'] ?? null)) {
            throw ValidationException::withMessages(['payment_reference' => 'Enter the USDT transaction reference or transaction hash after completing the transfer.']);
        }
        if ($requiresTransfer && $settings['proof_required'] && ! $request->hasFile('payment_proof')) {
            throw ValidationException::withMessages(['payment_proof' => 'Upload payment proof to submit this plan request.']);
        }

        $membershipRequest = $membership->createRequest($request, $request->user(), $plan, $promotion, $quote, $settings);
        $audit->record('membership.request_submitted', $request->user(), 'PulseMembershipRequest', $membershipRequest->id, null, [
            'plan_id' => $plan->id,
            'final_amount' => (float) $membershipRequest->final_amount,
            'currency' => $membershipRequest->currency,
            'promotion' => $promotion?->code,
            'status' => $membershipRequest->status,
        ], $request);

        $mail->adminNewSubscription($membershipRequest, 'web');

        if ($membershipRequest->status === 'approved') {
            $mail->planActivated($membershipRequest);
            return redirect()->route('pulse.membership.index')->with('success', 'Your gift voucher was accepted and Pulse access is active.');
        }

        $mail->planRequestReceived($membershipRequest);
        return redirect()->route('pulse.membership.index')->with('success', 'Your Pulse plan request has been submitted for verification.');
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
