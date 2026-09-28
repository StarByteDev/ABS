@extends('pulse.layout')
@section('title','Pulse Access & Payments — Alpha Block Solutions')
@section('heading','Pulse Access & Payments')
@section('content')
@php
    $openRequest = $requests->first(fn($item) => $item->isOpen());
    $openPresentation = $openRequest ? ($requestPresentations[$openRequest->id] ?? null) : null;
@endphp
<section class="container membership-account-shell membership-v1563">
    <header class="membership-account-head">
        <div><span class="membership-eyebrow">ABS PULSE · ACCESS & PAYMENTS</span><h1>Pulse access</h1><p>Your active package, pending payments and verification history in one place.</p></div>
        <a class="button button-primary" href="{{ route('pulse.plans') }}">View Pulse Packages</a>
    </header>

    @if($openRequest && $openPresentation)
        <section class="membership-pending-banner">
            <div class="membership-pending-icon">◷</div>
            <div class="membership-pending-copy">
                <small>PAYMENT VERIFICATION</small>
                <h2>{{ $openPresentation['headline'] }}</h2>
                <p>{{ $openPresentation['message'] }}</p>
                <div class="membership-pending-facts">
                    <span><b>{{ $openPresentation['plan_name'] }}</b><small>Package</small></span>
                    <span><b>{{ number_format((float)$openPresentation['amount'],2) }} {{ $openPresentation['currency'] }}</b><small>Submitted</small></span>
                    <span><b>{{ $openPresentation['status_label'] }}</b><small>Status</small></span>
                </div>
            </div>
        </section>
    @endif

    <div class="membership-account-grid">
        <article class="membership-account-status membership-current-access">
            <small>CURRENT ACCESS</small>
            <h2>{{ $access?->plan?->name ?? 'No active Pulse package' }}</h2>
            <div class="membership-status-line">
                <span class="admin-status {{ $access?->isActive()?'good':'muted' }}">{{ $access?->isActive()?'Active':ucfirst($access?->status ?? 'Not assigned') }}</span>
                @if($access?->ends_at)<span>Until {{ $access->ends_at->format('d M Y') }}</span>@endif
            </div>
            @if(!empty($currentPlanHighlights))
                <div class="membership-compact-benefits">@foreach($currentPlanHighlights as $item)<span>✓ {{ $item }}</span>@endforeach</div>
            @endif
            @if($access?->isActive())<a class="button button-primary" href="{{ route('pulse.dashboard') }}">Open ABS Pulse</a>@endif
        </article>

        <article class="membership-account-status membership-next-access">
            <small>{{ $nextPlan ? 'NEXT AVAILABLE PACKAGE' : 'PAYMENT METHOD' }}</small>
            @if($nextPlan)
                <h2>{{ $nextPlan->name }}</h2>
                <div class="membership-next-price">{{ number_format((float)$nextPlan->effectiveMonthlyPrice(),2) }} <span>USDT / {{ $nextPlan->access_days }} days</span></div>
                <div class="membership-compact-benefits">@foreach($nextPlanHighlights as $item)<span>✓ {{ $item }}</span>@endforeach</div>
                <a class="button button-ghost" href="{{ route('pulse.membership.checkout',$nextPlan) }}">Subscribe / Upgrade</a>
            @else
                <h2>Direct USDT</h2>
                <p>Package payments use {{ $commerce['network'] ?: 'the configured network' }} and activate after transaction verification.</p>
                <a class="button button-ghost" href="{{ route('pulse.plans') }}">Review Packages</a>
            @endif
        </article>
    </div>

    <section class="membership-request-history">
        <div class="membership-section-heading"><div><small>PAYMENT ACTIVITY</small><h2>Payment history</h2><p>Track every package request from submission through approval.</p></div></div>
        <div class="membership-history-list">
        @forelse($requests as $item)
            @php $presentation = $requestPresentations[$item->id] ?? null; @endphp
            <article>
                <div class="membership-history-main">
                    <span class="membership-request-id">REQUEST #{{ $item->id }}</span>
                    <h3>{{ $item->plan?->name }}</h3>
                    <p>{{ number_format((float)$item->final_amount,2) }} {{ $item->currency }} · {{ $item->created_at?->format('d M Y · H:i') }}</p>
                    @if($item->payment_reference)<small class="membership-txid">TXID {{ \Illuminate\Support\Str::limit($item->payment_reference,36) }}</small>@endif
                </div>
                <div class="membership-history-actions">
                    <span class="admin-status {{ $item->status==='approved'?'good':($item->status==='rejected'?'danger':(in_array($item->status,['submitted','under_review'])?'warn':'muted')) }}">{{ $presentation['status_label'] ?? ucfirst(str_replace('_',' ',$item->status)) }}</span>
                    @if($item->isOpen())
                        <form method="POST" action="{{ route('pulse.membership.cancel',$item) }}">@csrf @method('PATCH')<button class="link-button" onclick="return confirm('Cancel this payment request?')">Cancel</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="content-empty"><b>No package payments yet.</b><span>Your payment activity will appear here after submission.</span></div>
        @endforelse
        </div>
    </section>

    <section class="pulse-premium-plans compact-membership-plans">
        <header class="pulse-premium-plan-heading"><span>AVAILABLE ACCESS</span><h2>Pulse packages</h2><p>Choose the access level that fits how you use ABS Pulse.</p></header>
        <div class="pulse-membership-grid">@forelse($plans as $plan)@include('pulse.partials.membership-plan-card',['plan'=>$plan,'access'=>$access,'commerce'=>$commerce,'nextPlan'=>$nextPlan])@empty<div class="content-empty full-span"><b>No public Pulse package is currently available.</b></div>@endforelse</div>
    </section>
</section>
@endsection
