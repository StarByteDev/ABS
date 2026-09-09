@extends('pulse.layout')
@section('title','Pulse Access & Payments — Alpha Block Solutions')
@section('heading','Pulse Access & Payments')
@section('content')
<section class="container membership-account-shell">
    <header class="membership-account-head"><div><span class="membership-eyebrow">PULSE ACCESS</span><h1>My Pulse access</h1><p>Review your current package, direct USDT payment requests and available upgrade path.</p></div><a class="button button-primary" href="{{ route('pulse.plans') }}">View Pulse Packages</a></header>
    <div class="membership-account-grid">
        <article class="membership-account-status"><small>CURRENT ACCESS</small><h2>{{ $access?->plan?->name ?? 'No active Pulse package' }}</h2><div class="membership-status-line"><span class="admin-status {{ $access?->isActive()?'good':'muted' }}">{{ $access?->isActive()?'Active':ucfirst($access?->status ?? 'Not assigned') }}</span>@if($access?->ends_at)<span>Until {{ $access->ends_at->format('d M Y') }}</span>@endif</div>@if($access?->isActive())<a class="button button-primary" href="{{ route('pulse.dashboard') }}">Open Pulse</a>@endif @if($nextPlan)<div class="membership-next-tier"><small>NEXT AVAILABLE PACKAGE</small><b>{{ $nextPlan->name }} · {{ number_format((float)$nextPlan->effectiveMonthlyPrice(),2) }} USDT</b><a class="button button-ghost" href="{{ route('pulse.membership.checkout',$nextPlan) }}">Subscribe / Upgrade</a></div>@endif</article>
        <article class="membership-account-status"><small>DIRECT USDT COMMERCE</small><h2>Transfer → verify → activate</h2><p>Choose a package, transfer the exact USDT amount to the published wallet, submit the TXID and optional proof, and Admin activates your package after verification.</p><p><b>{{ $commerce['network'] ?: 'Network pending configuration' }}</b></p></article>
    </div>

    <section class="membership-request-history"><div class="membership-section-heading"><div><h2>Package payment history</h2><p>Track every direct USDT package request and its Admin verification status.</p></div></div><div class="membership-history-list">
    @forelse($requests as $item)
        <article><div><span class="membership-request-id">#{{ $item->id }}</span><h3>{{ $item->plan?->name }}</h3><p>{{ number_format((float)$item->final_amount,2) }} {{ $item->currency }} · {{ $item->created_at?->format('d M Y H:i') }} · {{ $item->payment_reference ?: 'No TXID' }}</p></div><div class="membership-history-actions"><span class="admin-status {{ $item->status==='approved'?'good':($item->status==='rejected'?'danger':(in_array($item->status,['submitted','under_review'])?'warn':'muted')) }}">{{ ucfirst(str_replace('_',' ',$item->status)) }}</span>@if($item->isOpen())<form method="POST" action="{{ route('pulse.membership.cancel',$item) }}">@csrf @method('PATCH')<button class="link-button" onclick="return confirm('Cancel this payment request?')">Cancel</button></form>@endif</div></article>
    @empty
        <div class="content-empty"><b>No package payment requests yet.</b><span>Select a Pulse package when you are ready.</span></div>
    @endforelse
    </div></section>

    <section class="pulse-premium-plans compact-membership-plans"><header class="pulse-premium-plan-heading"><span>YOUR PACKAGE PATH</span><h2>Available direct-USDT packages</h2><p>Best Signal is included with active access. There is no per-signal wallet charge.</p></header><div class="pulse-membership-grid">@forelse($plans as $plan)@include('pulse.partials.membership-plan-card',['plan'=>$plan,'access'=>$access,'commerce'=>$commerce,'nextPlan'=>$nextPlan])@empty<div class="content-empty full-span"><b>No public Pulse package is currently available.</b></div>@endforelse</div></section>
</section>
@endsection
