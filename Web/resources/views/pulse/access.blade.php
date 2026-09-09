@extends('pulse.layout')
@section('title','My Pulse Access')
@section('heading','My Pulse Access')
@section('content')
@php
    $accessService = app(\App\Services\PulseAccessService::class);
    $capabilities = $access ? $accessService->capabilityMatrix(auth()->user()) : [];
@endphp
<div class="pulse-grid pulse-grid-two">
<article class="pulse-panel">
    <h2>{{ $access?->plan?->name ?? 'Pulse access is not active' }}</h2>
    <?php if ($access): ?>
        <div class="pulse-form-grid">
            <div class="pulse-field"><label>Status</label><input readonly value="{{ ucfirst($access->status) }}"></div>
            <div class="pulse-field"><label>Start date</label><input readonly value="{{ $access->starts_at?->format('d M Y') ?? 'Active immediately' }}"></div>
            <div class="pulse-field"><label>End date</label><input readonly value="{{ $access->ends_at?->format('d M Y') ?? 'No expiry date' }}"></div>
            <div class="pulse-field"><label>Plan</label><input readonly value="{{ $access->plan?->name ?? 'Not assigned' }}"></div>
        </div>
        <h3>Features in your current plan</h3>
        <div class="pulse-form-grid three">
            <?php foreach ($capabilities as $key => $feature): ?>
                <div class="pulse-stat">
                    <small>{{ $feature['label'] }}</small>
                    <strong style="font-size:15px" class="{{ $feature['enabled'] ? 'positive' : 'muted-value' }}">{{ $feature['enabled'] ? 'Available' : 'Not included' }}</strong>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($access->notes && !str_contains(strtolower($access->notes), 'review')): ?>
            <div class="pulse-warning"><b>Account note</b><span>{{ $access->notes }}</span></div>
        <?php endif; ?>
    <?php else: ?>
        <div class="content-empty"><b>Your Pulse plan is not active.</b><span>Please contact Alpha Block Solutions if you believe access should be enabled.</span></div>
    <?php endif; ?>
</article>
<aside class="pulse-panel">
    <h2>Available Pulse plans</h2>
    <div class="pulse-action-list">
        <?php $__absForelseEmpty1 = true; foreach ($plans as $plan): $__absForelseEmpty1 = false; ?>
            <div>
                <b>{{ $plan->name }}</b>
                <small>{{ $plan->description }} · Best Signal included · {{ number_format((float)$plan->effectiveMonthlyPrice(),2) }} USDT / {{ $plan->access_days }} days · up to {{ $plan->max_selected_pairs }} package markets.</small>
            </div>
        <?php endforeach; if ($__absForelseEmpty1): ?>
            <div><b>No active plan is currently published.</b><small>Contact {{ config('brand.support_email') }} for assistance.</small></div>
        <?php endif; ?>
    </div>
    <a class="pulse-button" href="{{ route('pulse.membership.index') }}">View Pulse Plans</a>
</aside>
</div>
@endsection
