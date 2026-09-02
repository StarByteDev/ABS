@extends('pulse.layout')
@section('title','Pulse Plans')
@section('heading','Plans & Access')
@section('content')
<section class="pulse-premium-plans workspace-membership-plans">
    <header class="pulse-premium-plan-heading">
        <span>PULSE PLANS</span>
        <h2>Your current plan and upgrade path</h2>
        <p>Your active package is clearly marked. When a higher tier is available, ABS highlights the next recommended upgrade without offering downgrade-only plans.</p>
    </header>

    @if($nextPlan)
        <div class="pulse-plan-upgrade-summary">
            <div>
                <small>{{ $access?->isActive() && $access?->plan && ! $access->plan->is_trial ? 'NEXT HIGHER TIER' : 'RECOMMENDED STARTING PLAN' }}</small>
                <b>{{ $nextPlan->name }}</b>
                <span>{{ $access?->isActive() && $access?->plan && ! $access->plan->is_trial ? 'Upgrade when you are ready for higher limits and capabilities.' : 'Subscribe to activate your first paid Pulse package.' }}</span>
            </div>
            <a class="pulse-button secondary" href="{{ route('pulse.membership.checkout', $nextPlan) }}">{{ $access?->isActive() && $access?->plan && ! $access->plan->is_trial ? 'Upgrade now' : 'Subscribe now' }} →</a>
        </div>
    @elseif($access?->isActive())
        <div class="pulse-plan-upgrade-summary">
            <div><small>TOP TIER ACTIVE</small><b>{{ $access?->plan?->name }}</b><span>You are already on the highest currently available Pulse tier.</span></div>
            <a class="pulse-button secondary" href="{{ route('pulse.membership.index') }}">Membership & billing</a>
        </div>
    @endif

    <?php if (($commerce['trial_banner_enabled'] ?? false) && $access?->plan?->is_trial && $access->isActive()): ?>
        <div class="pulse-trial-slim-banner"><div class="pulse-trial-icon">✦</div><div><b>Pulse Trial is active</b><span>Explore your current Pulse access<?php if ($access->ends_at): ?> until {{ $access->ends_at->format('d M Y') }}<?php endif; ?>.</span></div><a href="{{ route('pulse.dashboard') }}">Open Pulse →</a></div>
    <?php endif; ?>

    <div class="pulse-membership-grid">
        <?php $__absForelseEmpty1 = true; foreach ($plans as $plan): $__absForelseEmpty1 = false; ?>
            @include('pulse.partials.membership-plan-card',['plan'=>$plan,'access'=>$access,'commerce'=>$commerce,'nextPlan'=>$nextPlan])
        <?php endforeach; if ($__absForelseEmpty1): ?>
            <div class="content-empty full-span"><b>No public Pulse plan is currently available.</b><span>Please contact {{ config('brand.support_email') }} for assistance.</span></div>
        <?php endif; ?>
    </div>

    <div class="pulse-membership-trust-row"><div><span>✓</span><p><b>Verified activation</b><small>Plan access is confirmed after payment or voucher verification.</small></p></div><div><span>◫</span><p><b>Coupons & gift vouchers</b><small>Apply eligible codes securely when requesting a plan.</small></p></div><div><span>⌾</span><p><b>Secure account access</b><small>Your Pulse access remains linked to your authenticated ABS account.</small></p></div></div>

    <div class="pulse-membership-account-link"><a class="pulse-button secondary" href="{{ route('pulse.membership.index') }}">View plan requests & history</a></div>
</section>
@endsection
