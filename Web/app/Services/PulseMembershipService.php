<?php

namespace App\Services;

use App\Models\PulseMembershipRequest;
use App\Models\PulsePlan;
use App\Models\PulsePromotionCode;
use App\Models\PulsePromotionRedemption;
use App\Models\PulseSystemSetting;
use App\Models\User;
use App\Models\UserServiceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
