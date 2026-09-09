@extends('layouts.app')
@section('title','Pulse Trading Intelligence — Alpha Block Solutions')
@section('content')
<section class="page-hero container pulse-public-hero focused-page-hero premium-flow-hero">
    <h1>Trade smarter with <span class="gradient-text">Pulse Trading Intelligence.</span></h1>
    <p>Pulse combines live market intelligence, strategy-based signals, risk planning, exchange connectivity, trade monitoring and performance reporting to support more informed trading decisions.</p>
    <div class="hero-actions">
        <?php if ($hasAccount): ?>
            <a class="button button-primary" href="{{ route('pulse.access') }}">Open My Pulse Access</a>
            <a class="button button-ghost" href="{{ route('dashboard') }}">My ABS Account</a>
        <?php else: ?>
            <a class="button button-primary" href="{{ route('pulse.free-signal') }}">Watch Ad · Get Free Signal</a>
            <a class="button button-ghost" href="{{ route('login', ['service' => 'pulse']) }}">Member Login</a>
            <a class="button button-ghost" href="{{ route('register', ['service' => 'pulse']) }}">Create an Account</a>
        <?php endif; ?>
    </div>
</section>

<section class="container pulse-capability-flow" id="capabilities">
    <header class="premium-flow-heading">
        <h2>Your complete trading-intelligence workflow</h2>
        <p>Move from market analysis to signal review, risk planning, execution and performance tracking without switching between disconnected tools.</p>
    </header>
    <div class="pulse-flow-list">
        <article class="pulse-flow-item"><span>01</span><div><h3>Scan Markets</h3><p>ABS reviews the Admin-defined USD-M Futures market universe across 15M and 4H automatically using the strategy intelligence assigned to your Pulse plan.</p></div><strong>SCAN</strong></article>
        <article class="pulse-flow-item"><span>02</span><div><h3>Review Signals</h3><p>Unlock the single highest-ranked qualifying Best Signal with confidence, qualifying strategy evidence, entry reference, stop loss, take profit and risk-to-reward.</p></div><strong>REVIEW</strong></article>
        <article class="pulse-flow-item"><span>03</span><div><h3>Set Your Risk</h3><p>Choose leverage, order size, daily limits, stop-loss and take-profit preferences, maximum open positions and emergency protection.</p></div><strong>CONTROL</strong></article>
        <article class="pulse-flow-item"><span>04</span><div><h3>Connect Binance</h3><p>Securely connect your own Binance USD-M Futures account. API credentials are encrypted and never displayed after saving.</p></div><strong>CONNECT</strong></article>
        <article class="pulse-flow-item"><span>05</span><div><h3>Manage Trades</h3><p>Monitor submitted orders, open positions, protection orders, commissions and realized or unrealized profit and loss.</p></div><strong>MANAGE</strong></article>
        <article class="pulse-flow-item"><span>06</span><div><h3>Track Performance</h3><p>Review scanner activity, signals, trades, risk events and account performance through Pulse web access and the mobile-ready API.</p></div><strong>REPORT</strong></article>
    </div>
</section>

<section class="container pulse-access-flow">
    <header class="premium-flow-heading compact-heading">
        <h2>Getting started with Pulse</h2>
        <p>Create your ABS account, explore Pulse market intelligence and choose the plan that fits how you trade.</p>
    </header>
    <ol class="pulse-access-timeline">
        <li><span>1</span><div><h3>Create your ABS account</h3><p>Use one secure account for Alpha Block Solutions and Pulse.</p></div></li>
        <li><span>2</span><div><h3>Explore Pulse</h3><p>Explore live markets, market intelligence and guided trade setups with the Pulse Trial when available.</p></div></li>
        <li><span>3</span><div><h3>Choose your package</h3><p>Select a 1-day, 3-day, 7-day or 30-day Pulse package based on the tools and market coverage you need.</p></div></li>
        <li><span>4</span><div><h3>Use automatic intelligence</h3><p>ABS controls the market universe, 15M + 4H evaluation, strategies and qualification rules. You only manage risk, alerts and optional exchange execution preferences.</p></div></li>
    </ol>
</section>

<section class="container pulse-premium-plans public-membership-plans" id="plans">
    <header class="pulse-premium-plan-heading">
        <span>PULSE PLANS</span>
        <h2>Choose the Pulse plan that fits your workflow</h2>
        <p>Each package is priced directly in USDT. Submit the transfer TXID and Admin activates the package after verification.</p>
    </header>

    <?php if (($commerce['trial_banner_enabled'] ?? false) && $trialPlan && !$hasAccount): ?>
        <div class="pulse-trial-slim-banner"><div class="pulse-trial-icon">✦</div><div><b>Explore Pulse with a complimentary Trial</b><span>Access market intelligence, live insights and guided trade opportunities with Pulse.</span></div><a href="{{ route('register',['service'=>'pulse']) }}">Create account →</a></div>
    <?php endif; ?>

    <div class="pulse-membership-grid">
        <?php $__absForelseEmpty1 = true; foreach ($plans as $plan): $__absForelseEmpty1 = false; ?>
            @include('pulse.partials.membership-plan-card',['plan'=>$plan,'access'=>$access ?? null,'commerce'=>$commerce])
        <?php endforeach; if ($__absForelseEmpty1): ?>
            <div class="content-empty full-span"><b>No public Pulse plan is currently available.</b><span>Please contact {{ config('brand.support_email') }} for assistance.</span></div>
        <?php endif; ?>
    </div>

    <div class="pulse-membership-trust-row"><div><span>✓</span><p><b>Direct USDT packages</b><small>Pay the published package amount directly in USDT.</small></p></div><div><span>◫</span><p><b>Admin verification</b><small>Submit the TXID and optional proof; access activates only after verification.</small></p></div><div><span>⌾</span><p><b>Best Signal included</b><small>No separate wallet balance or per-signal charge is used.</small></p></div></div>
</section>

<section class="pulse-safety-band">
    <div class="container">
        <div><h2>Trade with context. Manage risk with discipline.</h2><p>Pulse combines market insight, signal evidence and configurable safeguards to support better decisions. No signal can guarantee an outcome.</p></div>
    </div>
</section>
@endsection
