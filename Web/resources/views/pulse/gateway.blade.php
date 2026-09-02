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
            <a class="button button-primary" href="{{ route('login', ['service' => 'pulse']) }}">Login to Pulse</a>
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
        <article class="pulse-flow-item"><span>01</span><div><h3>Scan Markets</h3><p>Review selected USD-M Futures markets across your preferred timeframe using the strategy modules included with your Pulse plan.</p></div><strong>SCAN</strong></article>
        <article class="pulse-flow-item"><span>02</span><div><h3>Review Signals</h3><p>See LONG, SHORT and NEUTRAL results with a confidence score, strategy evidence, entry reference, stop loss, take profit and risk-to-reward.</p></div><strong>REVIEW</strong></article>
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
        <li><span>3</span><div><h3>Choose your plan</h3><p>Select Pulse Intelligence or Pulse Professional based on the tools and limits you need.</p></div></li>
        <li><span>4</span><div><h3>Set your preferences</h3><p>Configure markets, alerts, risk preferences and exchange tools included with your selected plan.</p></div></li>
    </ol>
</section>

<section class="container pulse-premium-plans public-membership-plans" id="plans">
    <header class="pulse-premium-plan-heading">
        <span>PULSE PLANS</span>
        <h2>Choose the Pulse plan that fits your workflow</h2>
        <p>Each plan defines your access, usage limits and available Pulse trading tools.</p>
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

    <div class="pulse-membership-trust-row"><div><span>✓</span><p><b>Verified activation</b><small>Plan access is confirmed after payment or voucher verification.</small></p></div><div><span>◫</span><p><b>Coupons & gift vouchers</b><small>Eligible codes can be applied securely during checkout.</small></p></div><div><span>⌾</span><p><b>Secure account access</b><small>Your Pulse access stays linked to your authenticated ABS account.</small></p></div></div>
</section>

<section class="pulse-safety-band">
    <div class="container">
        <div><h2>Trade with context. Manage risk with discipline.</h2><p>Pulse combines market insight, signal evidence and configurable safeguards to support better decisions. No signal can guarantee an outcome.</p></div>
    </div>
</section>
@endsection
