@php
    $isCurrent = $access?->isActive() && (int)($access?->pulse_plan_id ?? 0) === (int)$plan->id;
    $price = (float)$plan->effectiveMonthlyPrice();
    $paid = (bool)$plan->requires_payment && $price > 0;
    $ctaUrl = auth()->check() ? route('pulse.membership.checkout',$plan) : route('register',['service'=>'pulse']);
@endphp
<article class="pulse-membership-card {{ $plan->is_featured?'featured':'' }} {{ $isCurrent?'current':'' }}">
    <div class="pulse-plan-card-top">
        <div><span>{{ $plan->badge ?: ($plan->access_days.' DAY ACCESS') }}</span><h3>{{ $plan->name }}</h3><p>{{ $plan->description }}</p></div>
        @if($isCurrent)<strong class="pulse-current-badge">CURRENT</strong>@endif
    </div>
    <div class="pulse-plan-price"><strong>{{ number_format($price,2) }}</strong><span>USDT / {{ $plan->access_days }} {{ $plan->access_days===1?'day':'days' }}</span></div>
    <small class="pulse-legal-note">Direct USDT transfer. Admin verification is required before access activates.</small>
    <dl class="pulse-plan-feature-list">
        <div><dt><span>✓</span>Best Signal</dt><dd>Included with active package</dd></div>
        <div><dt><span>◫</span>Market access</dt><dd>Up to {{ number_format((int)$plan->max_selected_pairs) }} markets</dd></div>
        <div><dt><span>⌁</span>Timeframes</dt><dd>15M + 4H intelligence</dd></div>
        <div><dt><span>⌾</span>Activation</dt><dd>Admin-verified USDT payment</dd></div>
    </dl>
    @if($isCurrent)
        <a class="pulse-membership-cta" href="{{ route('pulse.dashboard') }}">Open Pulse <span>→</span></a>
    @elseif($plan->request_enabled && (!$paid || ($commerce['requests_enabled'] ?? false)))
        <a class="pulse-membership-cta" href="{{ $ctaUrl }}">{{ auth()->check() ? 'Pay with USDT' : 'Create Account to Subscribe' }} <span>→</span></a>
    @else
        <span class="pulse-membership-cta disabled">Package request unavailable</span>
    @endif
</article>
