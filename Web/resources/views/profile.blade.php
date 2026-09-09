@extends('pulse.layout')
@section('title','My Profile — Alpha Block Solutions')
@section('heading','My Profile')
@section('content')
<?php
    $plan = $pulseAccess?->plan;
    $planName = $plan?->name ?? 'No Pulse plan';
    $accessStatus = $pulseAccessActive ? 'Active' : ucfirst((string) ($pulseAccess?->status ?: 'Not active'));
    $expiry = $pulseAccess?->ends_at;
    $daysRemaining = $expiry ? max(0, now()->diffInDays($expiry, false)) : null;
    $roleLabel = $user->isAdmin() ? 'Administrator' : ($user->isPrivateMember() ? 'Private Member' : 'ABS Member');
    $verified = $user->hasVerifiedEmail();
    $marketCore = collect($market['core'] ?? [])->take(4);
    $pnl30 = (float) ($profileStats['realized_pnl_30d'] ?? 0);
?>

<div class="pp-page pp-profile-page">
    <header class="pp-page-head">
        <div class="pp-title">
            <h1>My Profile</h1>
            <p>Review your Alpha Block Solutions identity, Pulse access, connected services and account activity.</p>
        </div>
        <div class="pp-head-actions">
            <?php if ($pulseAccessActive): ?><a class="pp-button primary" href="{{ route('pulse.dashboard') }}">Open Pulse Dashboard</a><?php else: ?><a class="pp-button primary" href="{{ route('pulse.plans') }}">View Pulse Plans</a><?php endif; ?>
            <a class="pp-button" href="{{ route('pulse.membership.index') }}">Membership &amp; Billing</a>
        </div>
    </header>

    <section class="pp-metrics five" aria-label="Account summary">
        <article class="pp-metric">
            <span class="pp-metric-icon green">@include('pulse.partials.icon', ['name' => 'user'])</span>
            <div class="pp-metric-copy"><small>Account Status</small><strong>{{ strtoupper($user->status) }}</strong><em>{{ $roleLabel }}</em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon cyan">@include('pulse.partials.icon', ['name' => 'diamond'])</span>
            <div class="pp-metric-copy"><small>Pulse Access</small><strong>{{ $accessStatus }}</strong><em>{{ $planName }}</em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon">@include('pulse.partials.icon', ['name' => 'pulse'])</span>
            <div class="pp-metric-copy"><small>Active Signals</small><strong>{{ $profileStats['active_signals'] }}</strong><em>{{ $profileStats['open_positions'] }} open / in-flight position(s)</em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon orange">@include('pulse.partials.icon', ['name' => 'briefcase'])</span>
            <div class="pp-metric-copy"><small>Trades — 30D</small><strong>{{ $profileStats['executed_trades_30d'] }}</strong><em class="{{ $pnl30 >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $pnl30 >= 0 ? '+' : '-' }}${{ number_format(abs($pnl30), 2) }} realized P&amp;L</em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon">@include('pulse.partials.icon', ['name' => 'bell'])</span>
            <div class="pp-metric-copy"><small>Unread Alerts</small><strong>{{ $profileStats['unread_alerts'] }}</strong><em>{{ $connectionReady ? 'Binance '.$environment.' connected' : 'Exchange connection review recommended' }}</em></div>
        </article>
    </section>

    <section class="pp-profile-grid">
        <article class="pp-card pp-profile-identity">
            <div class="pp-profile-card-head"><div><small>ACCOUNT IDENTITY</small><h2>Personal account</h2></div><span class="pp-status-chip {{ $verified ? 'good' : 'warn' }}">{{ $verified ? 'EMAIL VERIFIED' : 'VERIFY EMAIL' }}</span></div>
            <div class="pp-profile-person">
                <span class="pp-profile-avatar">@include('pulse.partials.icon', ['name' => 'user'])</span>
                <div><h3>{{ $user->name }}</h3><p>{{ $user->email }}</p><span>{{ $roleLabel }}</span></div>
            </div>
            <dl class="pp-profile-details">
                <div><dt>Account role</dt><dd>{{ $roleLabel }}</dd></div>
                <div><dt>Member since</dt><dd>{{ $user->created_at?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Last sign-in</dt><dd>{{ $user->last_login_at?->format('d M Y H:i') ?? 'First session' }}</dd></div>
                <div><dt>Email verification</dt><dd class="{{ $verified ? 'pp-positive' : 'pp-warning' }}">{{ $verified ? 'Verified' : 'Pending' }}</dd></div>
            </dl>
            <div class="pp-profile-actions">
                <a class="pp-button" href="{{ route('home') }}">ABS Home</a>
                <a class="pp-button muted" href="{{ route('logout') }}">Sign Out</a>
            </div>
        </article>

        <article class="pp-card pp-profile-access">
            <div class="pp-profile-card-head"><div><small>PULSE MEMBERSHIP</small><h2>Plan &amp; access</h2></div><span class="pp-status-chip {{ $pulseAccessActive ? 'good' : 'warn' }}">{{ strtoupper($accessStatus) }}</span></div>
            <div class="pp-plan-primary">
                <span class="pp-plan-icon">@include('pulse.partials.icon', ['name' => 'diamond'])</span>
                <div><small>CURRENT PLAN</small><strong>{{ $planName }}</strong><p>{{ $pulseAccessActive ? 'Pulse capabilities are available according to your active plan and account permissions.' : 'Activate a Pulse plan to unlock your eligible trading-intelligence capabilities.' }}</p></div>
            </div>
            <dl class="pp-profile-details compact">
                <div><dt>Environment</dt><dd>{{ strtoupper($environment) }}</dd></div>
                <div><dt>Access start</dt><dd>{{ $pulseAccess?->starts_at?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Access expiry</dt><dd>{{ $expiry?->format('d M Y') ?? ($pulseAccessActive ? 'No fixed expiry' : '—') }}</dd></div>
                <div><dt>Days remaining</dt><dd>{{ $daysRemaining !== null ? $daysRemaining : ($pulseAccessActive ? 'Continuous' : '—') }}</dd></div>
                <div><dt>Max open positions</dt><dd>{{ $plan?->max_open_trades ?? '—' }}</dd></div>
                <div><dt>Package market limit</dt><dd>{{ $plan?->max_selected_pairs ?? '—' }}</dd></div>
                <div><dt>Mobile API</dt><dd class="{{ $plan?->allow_mobile_api ? 'pp-positive' : '' }}">{{ $plan?->allow_mobile_api ? 'Enabled' : 'Plan controlled' }}</dd></div>
                <div><dt>Binance {{ $environment }}</dt><dd class="{{ $connectionReady ? 'pp-positive' : 'pp-warning' }}">{{ $connectionReady ? 'Connected' : 'Not ready' }}</dd></div>
            </dl>
            <?php if ($latestMembershipRequest && in_array($latestMembershipRequest->status, ['submitted','under_review'], true)): ?>
                <div class="pp-profile-notice">@include('pulse.partials.icon', ['name' => 'clock'])<div><strong>Membership request under review</strong><span>{{ $latestMembershipRequest->plan?->name ?? 'Pulse plan' }} · submitted {{ $latestMembershipRequest->created_at?->diffForHumans() }}</span></div></div>
            <?php endif; ?>
            <div class="pp-profile-actions"><a class="pp-button primary" href="{{ route('pulse.access') }}">View Plan &amp; Limits</a><a class="pp-button" href="{{ route('pulse.plans') }}">Available Plans</a></div>
        </article>
    </section>

    <section class="pp-card pp-services-panel">
        <div class="pp-profile-card-head"><div><small>ABS SERVICES</small><h2>Services linked to this account</h2></div><span>Access is controlled by your account role, plan and administrator permissions.</span></div>
        <div class="pp-services-grid">
            <article class="pp-service-tile primary">
                <span class="pp-service-icon">@include('pulse.partials.icon', ['name' => 'pulse'])</span>
                <div><small>TRADING INTELLIGENCE</small><h3>Pulse Intelligence</h3><p>Market scanning, signal review, strategies, execution workflow, positions, risk controls, alerts and performance reporting.</p></div>
                <span class="pp-status-chip {{ $pulseAccessActive ? 'good' : 'warn' }}">{{ $pulseAccessActive ? 'ACTIVE' : 'NOT ACTIVE' }}</span>
                <?php if ($pulseAccessActive): ?><a href="{{ route('pulse.dashboard') }}">Open Pulse Dashboard →</a><?php else: ?><a href="{{ route('pulse.plans') }}">Explore Pulse Plans →</a><?php endif; ?>
            </article>
            <article class="pp-service-tile">
                <span class="pp-service-icon">@include('pulse.partials.icon', ['name' => 'document'])</span>
                <div><small>INVITATION SERVICE</small><h3>Private Member Portal</h3><p>Invitation-only private reporting and account statement access for approved members.</p></div>
                <span class="pp-status-chip {{ $user->isPrivateMember() ? 'good' : 'muted' }}">{{ $user->isPrivateMember() ? 'ACTIVE' : 'RESTRICTED' }}</span>
                <?php if ($user->isPrivateMember()): ?><a href="{{ route('private.index') }}">Open Private Portal →</a><?php else: ?><span class="pp-service-disabled">Not assigned to this account</span><?php endif; ?>
            </article>
            <?php if ($user->isAdmin()): ?>
                <article class="pp-service-tile">
                    <span class="pp-service-icon">@include('pulse.partials.icon', ['name' => 'settings'])</span>
                    <div><small>ENTERPRISE CONSOLE</small><h3>Administration</h3><p>Manage users, plans, approvals, content, communications, Pulse operations, backups and application controls.</p></div>
                    <span class="pp-status-chip good">ADMIN</span>
                    <a href="{{ route('admin.dashboard') }}">Open Administration →</a>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <section class="pp-profile-lower">
        <article class="pp-card pp-watchlist-card">
            <div class="pp-profile-card-head"><div><small>PERSONAL WATCHLIST</small><h2>Markets you follow</h2></div><span>{{ $watchlist->count() }} saved</span></div>
            <form class="pp-watchlist-form" method="POST" action="{{ route('watchlist.store') }}">@csrf
                <label><span>Market symbol</span><input name="symbol" placeholder="BTCUSDT" required></label>
                <label><span>Display name <em>optional</em></span><input name="display_name" placeholder="Bitcoin"></label>
                <button class="pp-button primary" type="submit">Add Asset</button>
            </form>
            <div class="pp-watchlist-rows">
                <?php $__profileEmpty=true; foreach ($watchlist as $item): $__profileEmpty=false; ?>
                    <div><span><strong>{{ $item->display_name ?: $item->symbol }}</strong><small>{{ $item->symbol }}</small></span><form method="POST" action="{{ route('watchlist.destroy',$item->symbol) }}">@csrf @method('DELETE')<button type="submit">Remove</button></form></div>
                <?php endforeach; if ($__profileEmpty): ?><div class="pp-empty">No markets are saved yet. Add a symbol above to create your personal watchlist.</div><?php endif; ?>
            </div>
        </article>

        <article class="pp-card pp-market-card">
            <div class="pp-profile-card-head"><div><small>MARKET CONTEXT</small><h2>Core markets</h2></div><a class="pp-link" href="{{ route('markets') }}">All markets →</a></div>
            <div class="pp-market-rows">
                <?php foreach ($marketCore as $coin): ?>
                    <?php $price=$coin['price']??null; $change=$coin['change_percent']??null; ?>
                    <div><span><strong>{{ $coin['pair'] ?? 'Market' }}</strong><small>{{ is_numeric($price) ? '$'.number_format((float)$price,(float)$price < 1 ? 4 : 2) : 'Live price unavailable' }}</small></span><em class="{{ is_numeric($change) ? ((float)$change >= 0 ? 'pp-positive' : 'pp-negative') : '' }}">{{ is_numeric($change) ? (((float)$change >= 0 ? '+' : '').number_format((float)$change,2).'%') : '—' }}</em></div>
                <?php endforeach; ?>
                <?php if ($marketCore->isEmpty()): ?><div class="pp-empty">Market data is updating. Public market intelligence remains available from the ABS navigation.</div><?php endif; ?>
            </div>
        </article>
    </section>
</div>
@endsection
