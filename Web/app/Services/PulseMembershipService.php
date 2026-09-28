<?php

namespace App\Services;

use App\Models\PulseMembershipRequest;
use App\Models\PulsePlan;
use App\Models\PulsePromotionCode;
use App\Models\PulsePromotionRedemption;
use App\Models\PulseSystemSetting;
use App\Models\PulseAlert;
use App\Models\User;
use App\Models\UserServiceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PulseMembershipService
{

    /**
     * Public paid plans in administrator-defined upgrade order.
     * sort_order is the tier hierarchy; price/name are deterministic fallbacks.
     */
    public function orderedPublicPlans(): Collection
    {
        return PulsePlan::query()
            ->publiclyAvailable()
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->orderBy('name')
            ->get();
    }

    /**
     * Return the next eligible paid tier for the current access. Trials and
     * accounts without an active paid plan are offered the first paid tier.
     */
    public function nextUpgradePlan(?UserServiceAccess $access): ?PulsePlan
    {
        $plans = $this->orderedPublicPlans();
        if ($plans->isEmpty()) {
            return null;
        }

        $current = $access?->isActive() ? $access->plan : null;
        if (! $current || $current->is_trial) {
            return $plans->first();
        }

        $next = $plans->first(fn (PulsePlan $plan): bool =>
            (int) $plan->id !== (int) $current->id
            && (int) $plan->sort_order > (int) $current->sort_order
        );

        if ($next) {
            return $next;
        }

        $currentPrice = max(0.0, (float) $current->effectiveMonthlyPrice());
        return $plans
            ->filter(fn (PulsePlan $plan): bool =>
                (int) $plan->id !== (int) $current->id
                && max(0.0, (float) $plan->effectiveMonthlyPrice()) > $currentPrice
            )
            ->sortBy(fn (PulsePlan $plan) => [max(0.0, (float) $plan->effectiveMonthlyPrice()), (int) $plan->sort_order, $plan->name])
            ->first();
    }

    /**
     * Hide downgrade-only tiers from an active user's package screen while
     * keeping the current tier and every higher tier visible.
     */
    public function upgradePathPlans(?UserServiceAccess $access): Collection
    {
        $plans = $this->orderedPublicPlans();
        $current = $access?->isActive() ? $access->plan : null;

        if (! $current || $current->is_trial) {
            return $plans;
        }

        // An already-active plan remains visible as CURRENT even if an admin
        // later retires/hides that tier from new subscriptions.
        if (! $plans->contains(fn (PulsePlan $plan): bool => (int) $plan->id === (int) $current->id)) {
            $plans = collect([$current])->concat($plans);
        }

        $currentPrice = max(0.0, (float) $current->effectiveMonthlyPrice());
        return $plans->filter(function (PulsePlan $plan) use ($current, $currentPrice): bool {
            if ((int) $plan->id === (int) $current->id) {
                return true;
            }

            return (int) $plan->sort_order > (int) $current->sort_order
                || max(0.0, (float) $plan->effectiveMonthlyPrice()) > $currentPrice;
        })->values();
    }

    /**
     * Short, customer-facing plan value points shared by web, email and mobile.
     * Keep these concise so all clients describe the same package consistently.
     */
    public function planHighlights(PulsePlan $plan): array
    {
        $highlights = [
            'Best Signal access with active package',
            ($plan->pair_access_mode === 'all' ? 'Full synchronized market universe' : 'Up to '.number_format(max(1, (int) $plan->max_selected_pairs)).' selected markets'),
            '15M + 4H Pulse strategy intelligence',
        ];

        if ($plan->allows('signals', true)) $highlights[] = 'Qualified signal details with entry, targets and protective stop';
        if ($plan->allows('alerts', false)) $highlights[] = 'Pulse alerts and watchlist monitoring';
        if ($plan->allows('reports', false)) $highlights[] = 'Performance and trading reports';
        if ($plan->allows('mobile_api', false)) $highlights[] = 'ABS Pulse mobile access';

        return array_values(array_unique(array_slice($highlights, 0, 6)));
    }

    /**
     * One normalized payment-request presentation for web and mobile clients.
     */
    public function requestStatusPayload(PulseMembershipRequest $request): array
    {
        $request->loadMissing('plan');
        $status = (string) $request->status;
        [$label, $headline, $message] = match ($status) {
            'submitted' => ['Awaiting verification', 'Payment submitted', 'Your transaction is queued for administrator verification. Access activates after the payment is confirmed on-chain.'],
            'under_review' => ['Under review', 'Verification in progress', 'Your payment is being reviewed. No further action is needed unless ABS contacts you for additional information.'],
            'approved' => ['Active', 'Payment approved', 'Your payment was approved and the selected Pulse access has been activated.'],
            'rejected' => ['Needs attention', 'Payment not approved', 'The payment could not be approved. Review the administrator note and contact support if you need assistance.'],
            'cancelled' => ['Cancelled', 'Request cancelled', 'This payment request was cancelled and no package activation was applied.'],
            default => [ucfirst(str_replace('_', ' ', $status)), 'Payment request update', 'Review the latest status shown in your ABS Pulse account.'],
        };

        return [
            'id' => (int) $request->id,
            'status' => $status,
            'status_label' => $label,
            'headline' => $headline,
            'message' => $message,
            'plan_name' => $request->plan?->name,
            'amount' => (float) $request->final_amount,
            'currency' => (string) $request->currency,
            'network' => $request->network,
            'payment_reference' => $request->payment_reference,
            'activation_days' => (int) $request->activation_days,
            'submitted_at' => $request->created_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'admin_note' => $request->admin_notes,
            'benefits' => $request->plan ? $this->planHighlights($request->plan) : [],
            'timeline' => [
                ['key' => 'submitted', 'label' => 'Payment submitted', 'complete' => true],
                ['key' => 'verification', 'label' => 'Transaction verification', 'complete' => in_array($status, ['approved','rejected'], true), 'active' => in_array($status, ['submitted','under_review'], true)],
                ['key' => 'activation', 'label' => 'Pulse access active', 'complete' => $status === 'approved'],
            ],
        ];
    }

    /**
     * Persistent in-app alert for every administrator, independent of email delivery.
     * Existing pulse_alerts storage is used; no schema change is required.
     */
    public function notifyAdministrators(PulseMembershipRequest $request, string $source = 'web'): void
    {
        try {
            if (! Schema::hasTable('pulse_alerts') || ! Schema::hasTable('users')) return;
            $request->loadMissing(['user','plan']);
            $admins = User::query()->where('role', 'admin')->where('status', 'active')->get(['id']);
            foreach ($admins as $admin) {
                PulseAlert::query()->create([
                    'user_id' => $admin->id,
                    'type' => 'package_payment',
                    'title' => 'Pulse payment awaiting verification',
                    'message' => ($request->user?->name ?? 'A member').' submitted '.number_format((float) $request->final_amount, 2).' '.$request->currency.' for '.($request->plan?->name ?? 'Pulse access').'.',
                    'severity' => 'info',
                    'is_read' => false,
                    'action_url' => route('admin.pulse.memberships', ['q' => $request->user?->email]),
                    'data' => ['membership_request_id' => $request->id, 'source' => $source],
                ]);
            }
        } catch (\Throwable) {
            // Email + pending-payment counters still surface the request if alerts are unavailable.
        }
    }

    public function mobilePlanPayload(PulsePlan $plan, ?UserServiceAccess $access): array
    {
        $next = $this->nextUpgradePlan($access);
        $current = $access?->isActive() && (int) $access->pulse_plan_id === (int) $plan->id;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'badge' => $plan->badge,
            'access_days' => (int) $plan->access_days,
            'price' => round((float) $plan->effectiveMonthlyPrice(), 2),
            'currency' => strtoupper((string) ($plan->currency ?: 'USDT')),
            'requires_payment' => (bool) $plan->requires_payment,
            'request_enabled' => (bool) $plan->request_enabled,
            'max_open_trades' => (int) $plan->max_open_trades,
            'max_markets' => (int) $plan->max_selected_pairs,
            'pair_access_mode' => (string) ($plan->pair_access_mode ?: 'all'),
            'capability_matrix' => $plan->capabilityMatrix(),
            'is_current_plan' => $current,
            'is_next_upgrade' => $next && (int) $next->id === (int) $plan->id,
            'subscription_state' => $current ? 'active' : (($next && (int) $next->id === (int) $plan->id) ? 'next_upgrade' : 'available'),
            'commerce_model' => 'direct_usdt_admin_verification',
            'commerce_label' => 'USDT transfer · Admin verified',
            'benefits' => $this->planHighlights($plan),
        ];
    }

    public function settings(): array
    {
        return [
            'requests_enabled' => (bool) PulseSystemSetting::value('membership_requests_enabled', true),
            'wallet_address' => trim((string) PulseSystemSetting::value('usdt_wallet_address', '')),
            'network' => trim((string) PulseSystemSetting::value('usdt_network', 'TRC20')),
            'payment_instructions' => trim((string) PulseSystemSetting::value('usdt_payment_instructions', 'Send the exact USDT amount to the configured wallet, then submit the transaction reference for Admin verification. Pulse access activates only after the payment is approved.')),
            'proof_required' => (bool) PulseSystemSetting::value('payment_proof_required', false),
            'promotions_enabled' => (bool) PulseSystemSetting::value('promotion_codes_enabled', false),
            'trial_auto_assign_enabled' => (bool) PulseSystemSetting::value('trial_auto_assign_enabled', true),
            'trial_banner_enabled' => (bool) PulseSystemSetting::value('trial_banner_enabled', true),
            'trial_duration_days' => max(1, (int) PulseSystemSetting::value('trial_duration_days', 7)),
            'commerce_model' => 'direct_usdt_admin_verification',
        ];
    }

    public function resolvePromotion(?string $code, User $user, PulsePlan $plan): ?PulsePromotionCode
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        if (! (bool) PulseSystemSetting::value('promotion_codes_enabled', true)) {
            throw ValidationException::withMessages(['promotion_code' => 'Promotion codes are not currently available.']);
        }

        /** @var PulsePromotionCode|null $promotion */
        $promotion = PulsePromotionCode::query()->whereRaw('UPPER(code) = ?', [$code])->first();
        if (! $promotion || ! $promotion->is_active) {
            throw ValidationException::withMessages(['promotion_code' => 'This coupon or gift voucher is not valid.']);
        }

        if ($promotion->valid_from && $promotion->valid_from->isFuture()) {
            throw ValidationException::withMessages(['promotion_code' => 'This coupon or gift voucher is not active yet.']);
        }
        if ($promotion->valid_until && $promotion->valid_until->isPast()) {
            throw ValidationException::withMessages(['promotion_code' => 'This coupon or gift voucher has expired.']);
        }
        if ($promotion->applicable_plan_id && (int) $promotion->applicable_plan_id !== (int) $plan->id) {
            throw ValidationException::withMessages(['promotion_code' => 'This coupon or gift voucher is not valid for the selected plan.']);
        }
        if ($promotion->assigned_user_id && (int) $promotion->assigned_user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['promotion_code' => 'This coupon or gift voucher is not available for this account.']);
        }

        $uses = $promotion->redemptions()->count();
        if ($promotion->max_uses > 0 && $uses >= $promotion->max_uses) {
            throw ValidationException::withMessages(['promotion_code' => 'This coupon or gift voucher has reached its usage limit.']);
        }

        $userUses = $promotion->redemptions()->where('user_id', $user->id)->count();
        if ($promotion->per_user_limit > 0 && $userUses >= $promotion->per_user_limit) {
            throw ValidationException::withMessages(['promotion_code' => 'This coupon or gift voucher has already been used for this account.']);
        }

        return $promotion;
    }

    public function quote(PulsePlan $plan, ?PulsePromotionCode $promotion = null): array
    {
        $base = $plan->effectiveMonthlyPrice();
        $discount = $promotion?->discountFor($base) ?? 0.0;
        $final = max(0.0, round($base - $discount, 2));

        return [
            'base_amount' => round($base, 2),
            'discount_amount' => round($discount, 2),
            'final_amount' => $final,
            'currency' => strtoupper($plan->currency ?: 'USDT'),
            'activation_days' => max(1, (int) ($promotion?->access_days ?: $plan->access_days ?: 30)),
        ];
    }

    public function createRequest(Request $httpRequest, User $user, PulsePlan $plan, ?PulsePromotionCode $promotion, array $quote, array $settings): PulseMembershipRequest
    {
        return DB::transaction(function () use ($httpRequest, $user, $plan, $promotion, $quote, $settings): PulseMembershipRequest {
            $openDuplicate = PulseMembershipRequest::query()
                ->where('user_id', $user->id)
                ->where('pulse_plan_id', $plan->id)
                ->whereIn('status', ['submitted', 'under_review'])
                ->exists();

            if ($openDuplicate) {
                throw ValidationException::withMessages(['plan' => 'You already have a Pulse plan request under review.']);
            }

            $proofPath = null;
            if ($httpRequest->hasFile('payment_proof')) {
                $proofPath = $httpRequest->file('payment_proof')->store('pulse-payment-proofs', 'local');
            }

            $membershipRequest = PulseMembershipRequest::create([
                'user_id' => $user->id,
                'pulse_plan_id' => $plan->id,
                'status' => 'submitted',
                'base_amount' => $quote['base_amount'],
                'discount_amount' => $quote['discount_amount'],
                'final_amount' => $quote['final_amount'],
                'currency' => $quote['currency'],
                'network' => $settings['network'] ?: null,
                'wallet_address_snapshot' => $settings['wallet_address'] ?: null,
                'payment_reference' => trim((string) $httpRequest->input('payment_reference')) ?: null,
                'payment_proof_path' => $proofPath,
                'promotion_code_id' => $promotion?->id,
                'promotion_code_snapshot' => $promotion?->code,
                'activation_days' => $quote['activation_days'],
                'user_notes' => trim((string) $httpRequest->input('user_notes')) ?: null,
            ]);

            if ($promotion && $promotion->type === 'gift_voucher' && $promotion->auto_activate && (float) $quote['final_amount'] <= 0) {
                $this->activateMembership($membershipRequest, null, $quote['activation_days']);
            }

            return $membershipRequest->fresh(['plan', 'promotion']);
        });
    }

    public function activateMembership(PulseMembershipRequest $membershipRequest, ?User $administrator, ?int $days = null, ?string $adminNotes = null): UserServiceAccess
    {
        return DB::transaction(function () use ($membershipRequest, $administrator, $days, $adminNotes): UserServiceAccess {
            $membershipRequest->loadMissing(['user', 'plan', 'promotion']);
            $plan = $membershipRequest->plan;
            $user = $membershipRequest->user;

            if (! $plan || ! $user) {
                throw ValidationException::withMessages(['request' => 'The Pulse plan request no longer has a valid user or plan.']);
            }

            $duration = max(1, (int) ($days ?: $membershipRequest->activation_days ?: $plan->access_days ?: 30));
            $access = UserServiceAccess::query()->where('user_id', $user->id)->where('service', 'pulse')->first();
            $samePlan = $access && (int) $access->pulse_plan_id === (int) $plan->id;
            $base = $samePlan && $access->ends_at && $access->ends_at->isFuture() ? $access->ends_at->copy() : now();
            $trialUsedAt = $access?->trial_used_at;

            $access = UserServiceAccess::updateOrCreate(
                ['user_id' => $user->id, 'service' => 'pulse'],
                [
                    'status' => 'active',
                    'pulse_plan_id' => $plan->id,
                    'approved_by' => $administrator?->id,
                    'starts_at' => $samePlan && $access?->starts_at ? $access->starts_at : now(),
                    'ends_at' => $base->addDays($duration),
                    'trial_used_at' => $trialUsedAt,
                    'permissions' => $samePlan ? ($access?->permissions ?: []) : [],
                    'notes' => $adminNotes ?: null,
                ],
            );

            $membershipRequest->update([
                'status' => 'approved',
                'reviewed_by' => $administrator?->id,
                'reviewed_at' => now(),
                'activated_access_id' => $access->id,
                'admin_notes' => $adminNotes ?: $membershipRequest->admin_notes,
            ]);

            if ($membershipRequest->promotion_code_id && ! PulsePromotionRedemption::query()->where('membership_request_id', $membershipRequest->id)->exists()) {
                PulsePromotionRedemption::create([
                    'promotion_code_id' => $membershipRequest->promotion_code_id,
                    'user_id' => $user->id,
                    'pulse_plan_id' => $plan->id,
                    'membership_request_id' => $membershipRequest->id,
                    'discount_amount' => $membershipRequest->discount_amount,
                    'redeemed_at' => now(),
                ]);
            }

            return $access;
        });
    }
}
