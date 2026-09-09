<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseAchievement;
use App\Models\PulseMission;
use App\Models\PulsePlan;
use App\Models\PulsePointLedger;
use App\Models\PulsePointPack;
use App\Models\PulsePointPurchase;
use App\Models\PulseRewardClaim;
use App\Models\PulseSignal;
use App\Models\PulseUserAchievement;
use App\Models\PulseUserMission;
use App\Models\UserServiceAccess;
use App\Services\BrandedMailService;
use App\Services\PulseAiExplanationService;
use App\Services\PulseAuditService;
use App\Services\PulseGamificationService;
use App\Services\PulseMembershipService;
use App\Services\PulsePointService;
use App\Services\PulseRewardedAdService;
use App\Services\PulseShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class PointsController extends Controller
{
    public function index(Request $request, PulsePointService $points, PulseMembershipService $membership, PulseRewardedAdService $rewardedAds)
    {
        $user = $request->user();
        $wallet = $points->wallet($user);
        $missions = PulseMission::query()->where('is_active', true)->orderBy('sort_order')->get()->map(function (PulseMission $mission) use ($user) {
            $periodKey = app(PulseGamificationService::class)->periodKey($mission->period);
            $progress = PulseUserMission::query()->where('user_id', $user->id)->where('pulse_mission_id', $mission->id)->where('period_key', $periodKey)->first();
            return ['mission' => $mission, 'progress' => $progress, 'period_key' => $periodKey];
        });
        $achievements = PulseAchievement::query()->where('is_active', true)->orderBy('sort_order')->get()->map(function (PulseAchievement $achievement) use ($user) {
            return ['achievement' => $achievement, 'unlock' => PulseUserAchievement::query()->where('user_id', $user->id)->where('pulse_achievement_id', $achievement->id)->first()];
        });
        $access = UserServiceAccess::query()->with('plan')->where('user_id', $user->id)->where('service', 'pulse')->first();
        $plans = $membership->upgradePathPlans($access)->filter(fn (PulsePlan $plan) => (bool) $plan->allow_points_activation && (int) $plan->price_points > 0)->values();

        return view('pulse.points.index', [
            'wallet' => $wallet,
            'ledger' => PulsePointLedger::query()->where('user_id', $user->id)->latest('id')->limit(40)->get(),
            'packs' => PulsePointPack::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'purchases' => PulsePointPurchase::query()->with('pack')->where('user_id', $user->id)->latest()->limit(20)->get(),
            'missions' => $missions,
            'achievements' => $achievements,
            'plans' => $plans,
            'access' => $access,
            'commerce' => $membership->settings(),
            'checkinClaimed' => PulseRewardClaim::query()->where('user_id', $user->id)->where('reward_type', 'daily_checkin')->where('reward_key', 'checkin:'.today()->toDateString())->exists(),
            'nextLevelXp' => max(250, (int) $user->pulse_level * 250),
            'rewardedAds' => $rewardedAds->userStatus($user),
        ]);
    }

    public function purchase(Request $request, PulseAuditService $audit, PulseMembershipService $membership, BrandedMailService $mail)
    {
        $request->merge(['payment_reference' => trim((string) $request->input('payment_reference'))]);
        $data = $request->validate([
            'pulse_point_pack_id' => ['required', 'integer', 'exists:pulse_point_packs,id'],
            'payment_reference' => ['required', 'string', 'max:190', Rule::unique('pulse_point_purchases', 'payment_reference')],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'user_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $pack = PulsePointPack::query()->where('is_active', true)->findOrFail((int) $data['pulse_point_pack_id']);
        $commerce = $membership->settings();
        if (! ($commerce['points_purchase_enabled'] ?? true)) {
            return back()->withErrors(['points' => 'Pulse Sparks purchases are temporarily unavailable.']);
        }
        if ($commerce['wallet_address'] === '' || $commerce['network'] === '') {
            return back()->withErrors(['points' => 'Pulse Sparks payment details are not currently published.']);
        }
        if ($commerce['proof_required'] && ! $request->hasFile('payment_proof')) {
            return back()->withErrors(['payment_proof' => 'Upload payment proof to submit this Pulse Sparks purchase.']);
        }

        $open = PulsePointPurchase::query()->where('user_id', $request->user()->id)->whereIn('status', ['submitted', 'under_review'])->exists();
        if ($open) return back()->withErrors(['points' => 'You already have a Pulse Sparks purchase awaiting verification.']);

        $proof = $request->hasFile('payment_proof') ? $request->file('payment_proof')->store('pulse-point-payment-proofs', 'local') : null;
        $purchase = PulsePointPurchase::create([
            'user_id' => $request->user()->id,
            'pulse_point_pack_id' => $pack->id,
            'status' => 'submitted',
            'points_snapshot' => $pack->points,
            'bonus_points_snapshot' => $pack->bonus_points,
            'amount_usdt' => $pack->price_usdt,
            'network' => $commerce['network'],
            'wallet_address_snapshot' => $commerce['wallet_address'],
            'payment_reference' => $data['payment_reference'],
            'payment_proof_path' => $proof,
            'user_notes' => trim((string) ($data['user_notes'] ?? '')) ?: null,
        ]);
        $audit->record('points.purchase_submitted', $request->user(), 'PulsePointPurchase', $purchase->id, null, ['pack_id' => $pack->id, 'points' => $purchase->totalPoints(), 'amount_usdt' => (float) $purchase->amount_usdt], $request);
        try { $mail->adminNewPointPurchase($purchase, 'web'); } catch (\Throwable) { /* purchase remains authoritative */ }
        return back()->with('success', 'Pulse Sparks purchase submitted. Sparks will be credited after Admin verifies the USDT transaction.');
    }

    public function cancelPurchase(Request $request, PulsePointPurchase $purchase, PulseAuditService $audit)
    {
        abort_unless((int) $purchase->user_id === (int) $request->user()->id, 403);
        try {
            DB::transaction(function () use ($request, $purchase): void {
                $locked = PulsePointPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $locked->user_id === (int) $request->user()->id, 403);
                if (! $locked->isOpen()) throw new RuntimeException('Only a pending Pulse Sparks purchase can be cancelled.');
                $locked->update(['status' => 'cancelled']);
            }, 5);
        } catch (RuntimeException $e) {
            return back()->withErrors(['points' => $e->getMessage()]);
        }
        $audit->record('points.purchase_cancelled', $request->user(), 'PulsePointPurchase', $purchase->id, null, [], $request);
        return back()->with('success', 'Pulse Sparks purchase request cancelled.');
    }

    public function checkIn(Request $request, PulseGamificationService $gamification)
    {
        $result = $gamification->checkIn($request->user());
        $message = $result['already_claimed'] ? 'Today’s Pulse check-in reward was already claimed.' : 'Daily Pulse check-in complete. Your streak and rewards have been updated.';
        return $request->expectsJson() ? response()->json(['message' => $message, 'data' => $result]) : back()->with('success', $message);
    }

    public function rewardedAd(Request $request, PulseGamificationService $gamification)
    {
        $data = $request->validate([
            'provider_reference' => ['required', 'string', 'max:190'],
            'signature' => ['required', 'string', 'max:255'],
        ]);
        try {
            $claim = $gamification->rewardVerifiedAd($request->user(), trim($data['provider_reference']), ['signature' => trim($data['signature'])]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['message' => 'Verified rewarded-ad reward processed.', 'data' => $claim]);
    }

    public function activatePlan(Request $request, PulsePlan $plan, PulsePointService $points, PulseAuditService $audit)
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'max:190']]);
        $user = $request->user();
        if (! $plan->is_active || ! $plan->is_public || $plan->is_trial || ! $plan->allow_points_activation || (int) $plan->price_points <= 0) {
            return back()->withErrors(['plan' => 'This Pulse package is not available for Spark activation.']);
        }

        $current = UserServiceAccess::query()->with('plan')->where('user_id', $user->id)->where('service', 'pulse')->first();
        if ($current?->isActive() && $current->plan && (int) $plan->id !== (int) $current->plan->id) {
            $lowerOrder = (int) $plan->sort_order < (int) $current->plan->sort_order;
            $lowerPrice = $plan->effectivePointsPrice() < $current->plan->effectivePointsPrice();
            if ($lowerOrder && $lowerPrice) return back()->withErrors(['plan' => 'A lower Pulse tier cannot replace your currently active plan.']);
        }

        try {
            DB::transaction(function () use ($user, $plan, $points, $data): void {
                $entry = $points->debit($user, (int) $plan->price_points, 'plan_activation', 'plan-activation:'.$user->id.':'.$data['idempotency_key'], 'Activated '.$plan->name.' with Pulse Sparks.', 'PulsePlan', $plan->id, ['access_days' => $plan->access_days]);
                if (! $entry->wasRecentlyCreated) return; // idempotent retry: do not extend access twice
                $access = UserServiceAccess::query()->where('user_id', $user->id)->where('service', 'pulse')->lockForUpdate()->first();
                $samePlan = $access && (int) $access->pulse_plan_id === (int) $plan->id;
                $base = $samePlan && $access->ends_at && $access->ends_at->isFuture() ? $access->ends_at->copy() : now();
                UserServiceAccess::updateOrCreate(['user_id' => $user->id, 'service' => 'pulse'], [
                    'status' => 'active', 'pulse_plan_id' => $plan->id, 'starts_at' => $samePlan && $access?->starts_at ? $access->starts_at : now(),
                    'ends_at' => $base->addDays(max(1, (int) $plan->access_days)), 'permissions' => $samePlan ? ($access?->permissions ?: []) : [],
                    'notes' => 'Activated with Pulse Sparks.',
                ]);
            }, 5);
        } catch (RuntimeException $e) {
            return back()->withErrors(['points' => $e->getMessage()]);
        }

        $audit->record('points.plan_activated', $user, 'PulsePlan', $plan->id, null, ['points' => (int) $plan->price_points], $request);
        return back()->with('success', $plan->name.' activated with Pulse Sparks.');
    }

    public function share(Request $request, PulseSignal $signal, PulseShareService $share, PulseGamificationService $gamification)
    {
        abort_unless((int) $signal->user_id === (int) $request->user()->id, 403);
        $data = $request->validate(['channel' => ['nullable', 'string', 'max:40']]);
        $payload = $share->payload($signal);
        $claim = $gamification->rewardSocialShare($request->user(), $signal, $data['channel'] ?? null);
        return response()->json(['message' => 'Share card ready.', 'data' => $payload, 'reward' => $claim]);
    }

    public function explain(Request $request, PulseSignal $signal, PulseAiExplanationService $ai)
    {
        abort_unless((int) $signal->user_id === (int) $request->user()->id, 403);
        try { $text = $ai->explain($request->user(), $signal); }
        catch (RuntimeException $e) { return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 422) : back()->withErrors(['ai' => $e->getMessage()]); }
        return $request->expectsJson() ? response()->json(['message' => 'AI explanation ready.', 'data' => ['explanation' => $text]]) : back()->with('success', 'AI explanation generated.');
    }
}
