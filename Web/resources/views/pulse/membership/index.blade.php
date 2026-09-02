@extends('pulse.layout')
@section('title','My Pulse Access — Alpha Block Solutions')
@section('heading','Membership & Billing')
@section('content')
<section class="container membership-account-shell">
    <header class="membership-account-head"><div><span class="membership-eyebrow">PULSE ACCESS</span><h1>My Pulse access</h1><p>Review your current plan, recent access requests and available Pulse options.</p></div><?php if ($access?->isActive()): ?><a class="button button-ghost" href="{{ route('pulse.dashboard') }}">Pulse Dashboard</a><?php else: ?><a class="button button-ghost" href="{{ route('pulse.plans') }}">Plans &amp; Access</a><?php endif; ?></header>

    <div class="membership-account-grid">
        <article class="membership-account-status"><small>CURRENT ACCESS</small><h2>{{ $access?->plan?->name ?? 'No active Pulse plan' }}</h2><div class="membership-status-line"><span class="admin-status {{ $access?->isActive()?'good':'muted' }}">{{ $access?->isActive()?'Active':ucfirst($access?->status ?? 'Not assigned') }}</span><?php if ($access?->ends_at): ?><span>Until {{ $access->ends_at->format('d M Y') }}</span><?php endif; ?></div><?php if ($access?->isActive()): ?><a class="button button-primary" href="{{ route('pulse.dashboard') }}">Open Pulse</a><?php endif; ?>@if($nextPlan)<div class="membership-next-tier"><small>NEXT HIGHER TIER</small><b>{{ $nextPlan->name }}</b><a class="button button-ghost" href="{{ route('pulse.membership.checkout',$nextPlan) }}">Upgrade</a></div>@endif</article>
        <article class="membership-account-status"><small>PLAN SUPPORT</small><h2>Need help with a request?</h2><p>Contact {{ config('brand.support_email') }} and include your request number or transaction reference.</p><a class="button button-ghost" href="mailto:{{ config('brand.support_email') }}?subject=Pulse%20plan%20support">Contact support</a></article>
    </div>

    <?php if ($commerce['promotions_enabled'] && $assignedPromotions->isNotEmpty()): ?>
    <section class="membership-personal-offers">
        <div class="membership-section-heading"><div><span class="membership-eyebrow">YOUR OFFERS</span><h2>Coupons & gift vouchers</h2><p>These codes are available specifically for your ABS account.</p></div></div>
        <div class="membership-offer-grid">
            <?php foreach ($assignedPromotions as $offer): ?>
            <article class="membership-offer-card">
                <div><span class="membership-offer-type">{{ $offer->type==='gift_voucher'?'GIFT VOUCHER':'COUPON' }}</span><h3>{{ $offer->label ?: $offer->code }}</h3><p>{{ $offer->displayBenefit() }}<?php if ($offer->access_days): ?> · {{ $offer->access_days }} days access <?php endif; ?> <?php if ($offer->plan): ?> · {{ $offer->plan->name }} <?php endif; ?></p></div>
                <div class="membership-offer-code"><code>{{ $offer->code }}</code><?php if ($offer->applicable_plan_id): ?><a class="button button-ghost" href="{{ route('pulse.membership.checkout',['plan'=>$offer->applicable_plan_id,'promo'=>$offer->code]) }}">Use offer</a><?php endif; ?></div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="membership-request-history"><div class="membership-section-heading"><div><h2>Recent requests</h2><p>Track the latest Pulse plan requests linked to your account.</p></div></div><div class="membership-history-list">
    <?php $__absForelseEmpty1 = true; foreach ($requests as $item): $__absForelseEmpty1 = false; ?>
        <article><div><span class="membership-request-id">#{{ $item->id }}</span><h3>{{ $item->plan?->name }}</h3><p>{{ $item->created_at?->format('d M Y H:i') }} · {{ number_format((float)$item->final_amount,2) }} {{ $item->currency }}<?php if ($item->promotion_code_snapshot): ?> · {{ $item->promotion_code_snapshot }}<?php endif; ?></p></div><div class="membership-history-actions"><span class="admin-status {{ $item->status==='approved'?'good':($item->status==='rejected'?'danger':(in_array($item->status,['submitted','under_review'])?'warn':'muted')) }}">{{ ucfirst(str_replace('_',' ',$item->status)) }}</span><?php if ($item->isOpen()): ?><form method="POST" action="{{ route('pulse.membership.cancel',$item) }}">@csrf @method('PATCH')<button class="link-button" onclick="return confirm('Cancel this plan request?')">Cancel</button></form><?php endif; ?></div></article>
    <?php endforeach; if ($__absForelseEmpty1): ?><article class="empty"><div><h3>No plan requests yet.</h3><p>Select a Pulse plan below whenever you are ready.</p></div></article><?php endif; ?>
    </div></section>

    <section class="pulse-premium-plans compact-membership-plans"><header class="pulse-premium-plan-heading"><span>YOUR PLAN PATH</span><h2>Current package and available upgrades</h2><p>Downgrade-only tiers are hidden while your current package stays active.</p></header><div class="pulse-membership-grid"><?php $__absForelseEmpty2 = true; foreach ($plans as $plan): $__absForelseEmpty2 = false; ?>@include('pulse.partials.membership-plan-card',['plan'=>$plan,'access'=>$access,'commerce'=>$commerce,'nextPlan'=>$nextPlan])<?php endforeach; if ($__absForelseEmpty2): ?><div class="content-empty full-span"><b>No public Pulse plan is currently available.</b><span>Please contact {{ config('brand.support_email') }} for assistance.</span></div><?php endif; ?></div></section>
</section>
@endsection
