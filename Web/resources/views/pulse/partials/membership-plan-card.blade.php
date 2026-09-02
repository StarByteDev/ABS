@php
    $isProfessional = str_contains(strtolower($plan->slug), 'professional') || $plan->is_featured;
    $currentPlan = isset($access) && $access && (int) $access->pulse_plan_id === (int) $plan->id && $access->isActive();
    $isNextUpgrade = isset($nextPlan) && $nextPlan && (int) $nextPlan->id === (int) $plan->id && ! $currentPlan;
    $hasActivePaidPlan = isset($access) && $access && $access->isActive() && $access->plan && ! $access->plan->is_trial;
    $ctaLabel = $isNextUpgrade
        ? ($hasActivePaidPlan ? 'Upgrade to '.$plan->name : 'Subscribe to '.$plan->name)
        : 'Subscribe to '.$plan->name;
    $ctaUrl = auth()->check()
        ? route('pulse.membership.checkout', $plan)
        : route('register', ['service' => 'pulse', 'plan' => $plan->slug]);
    $effectiveMonthlyPrice = $plan->effectiveMonthlyPrice();
@endphp
<article class="pulse-membership-card {{ $isProfessional ? 'professional' : 'intelligence' }} {{ $plan->is_featured ? 'featured' : '' }} {{ $currentPlan ? 'current-plan' : '' }} {{ $isNextUpgrade ? 'next-upgrade' : '' }}">
    @if($currentPlan)
        <div class="pulse-plan-state-badge active">✓ CURRENT PLAN · ACTIVE</div>
    @elseif($isNextUpgrade)
        <div class="pulse-plan-state-badge upgrade">NEXT TIER · RECOMMENDED UPGRADE</div>
    @endif

    <div class="pulse-membership-card-head">
        <div class="pulse-plan-symbol" aria-hidden="true">{!! $isProfessional ? '◇' : '⬡' !!}</div>
        <div class="grow">
            <div class="pulse-plan-title-line"><h3>{{ $plan->name }}</h3><?php if ($plan->badge): ?><span>{{ $plan->badge }}</span><?php endif; ?></div>
            <p>{{ $plan->description }}</p>
        </div>
    </div>
    <?php if ($effectiveMonthlyPrice > 0): ?>
        <div class="pulse-plan-price"><strong>{{ rtrim(rtrim(number_format($effectiveMonthlyPrice,2), '0'), '.') }}</strong><span>{{ strtoupper($plan->currency ?: 'USDT') }} / {{ $plan->access_days }} days</span></div>
    <?php endif; ?>
    <dl class="pulse-premium-feature-list">
        <div><dt><span>⌁</span>Daily market scans</dt><dd>{{ $plan->scanner_runs_per_day > 0 ? 'Up to '.$plan->scanner_runs_per_day : 'Unlimited' }}</dd></div>
        <div><dt><span>⌁</span>Daily signals</dt><dd>{{ $plan->signals_per_day > 0 ? 'Up to '.$plan->signals_per_day : 'Unlimited' }}</dd></div>
        <div><dt><span>◎</span>Markets tracked</dt><dd>Up to {{ $plan->max_selected_pairs }}</dd></div>
        <div><dt><span>◫</span>Open positions</dt><dd>{{ $plan->max_open_trades > 0 ? 'Up to '.$plan->max_open_trades : 'Not included' }}</dd></div>
        <div><dt><span>◉</span>Scanner & signals</dt><dd>{{ $plan->allows('scanner',false) && $plan->allows('signals',false) ? 'Included' : 'Plan controlled' }}</dd></div>
        <div><dt><span>◌</span>Reports & alerts</dt><dd>{{ $plan->allows('reports',false) || $plan->allows('alerts',false) ? 'Included' : 'Not included' }}</dd></div>
        <div><dt><span>◇</span>Exchange tools</dt><dd>{{ $plan->allows('binance',false) || $plan->allows('orders',false) ? 'Included' : 'Not included' }}</dd></div>
        <div><dt><span>✓</span>Plan activation</dt><dd>Verified</dd></div>
    </dl>
    <div class="pulse-plan-card-actions">
        @if($currentPlan)
            <span class="pulse-membership-cta current-active" aria-disabled="true">✓ Active on your account</span>
            <a class="pulse-membership-secondary" href="{{ route('pulse.membership.index') }}">View membership & billing</a>
        @elseif($plan->request_enabled && ($commerce['requests_enabled'] ?? true))
            <a class="pulse-membership-cta" href="{{ $ctaUrl }}">{{ $ctaLabel }} <span>→</span></a>
            <?php if ($commerce['promotions_enabled'] ?? true): ?>
                <a class="pulse-membership-secondary" href="{{ $ctaUrl }}#promotion">Apply coupon / gift voucher</a>
            <?php endif; ?>
        @else
            <span class="pulse-membership-cta disabled">Plan requests unavailable</span>
        @endif
    </div>
</article>
