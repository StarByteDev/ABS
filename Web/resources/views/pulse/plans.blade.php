@extends('pulse.layout')
@section('title','Pulse Plans')
@section('heading','Plans & Access')
@section('content')
<section class="pulse-premium-plans workspace-membership-plans">
    <header class="pulse-premium-plan-heading"><span>PULSE PLANS</span><h2>Your current plan and available package path</h2><p>Pulse packages are purchased by direct USDT transfer. Admin verifies the transaction and activates access. Best Signal is included with active access.</p></header>
    @if($nextPlan)<div class="pulse-plan-upgrade-summary"><div><small>{{ $access?->isActive()?'NEXT AVAILABLE PACKAGE':'RECOMMENDED STARTING PLAN' }}</small><b>{{ $nextPlan->name }}</b><span>{{ number_format((float)$nextPlan->effectiveMonthlyPrice(),2) }} USDT · {{ $nextPlan->access_days }} days access</span></div><a class="pulse-button secondary" href="{{ route('pulse.membership.checkout',$nextPlan) }}">Pay with USDT →</a></div>@elseif($access?->isActive())<div class="pulse-plan-upgrade-summary"><div><small>CURRENT ACCESS</small><b>{{ $access?->plan?->name }}</b><span>Your current package is active.</span></div><a class="pulse-button secondary" href="{{ route('pulse.membership.index') }}">Payment history</a></div>@endif
    <div class="pulse-membership-grid">@forelse($plans as $plan)@include('pulse.partials.membership-plan-card',['plan'=>$plan,'access'=>$access,'commerce'=>$commerce,'nextPlan'=>$nextPlan])@empty<div class="content-empty full-span"><b>No public Pulse plan is currently available.</b></div>@endforelse</div>
    <div class="pulse-membership-trust-row"><div><span>✓</span><p><b>Direct USDT payment</b><small>Transfer the exact package amount to the published wallet.</small></p></div><div><span>◫</span><p><b>Admin verification</b><small>Access activates only after the transaction is verified.</small></p></div><div><span>⌾</span><p><b>No per-signal charge</b><small>Best Signal is included while your package is active.</small></p></div></div>
    <div class="pulse-membership-account-link"><a class="pulse-button secondary" href="{{ route('pulse.membership.index') }}">Open Access & Payments</a></div>
</section>
@endsection
