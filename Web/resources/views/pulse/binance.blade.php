@extends('pulse.layout')
@section('title','Pulse Binance Futures Connection')
@section('heading','Binance Connection')
@section('content')
@php
    $verifiedCount = $connections->filter(fn($connection)=>$connection->last_tested_at && ! $connection->last_error && data_get($connection->permissions,'can_trade',false))->count();
    $activeConnection = $connections->firstWhere('is_active', true);
@endphp
<div class="pulse-page-hero">
    <div><span>NON-CUSTODIAL FUTURES CONNECTIVITY</span><h1>Binance Futures Connection</h1><p>Choose Practice / Testnet or Live USD‑M Futures. Your funds remain on Binance and Pulse never needs withdrawal permission.</p></div>
    <div class="binance-active-summary"><small>ACTIVE ENVIRONMENT</small><strong>{{ $activeConnection ? ($activeConnection->environment === 'live' ? 'LIVE FUTURES' : 'TESTNET') : 'NOT SELECTED' }}</strong><span class="{{ $activeConnection?'positive':'' }}">{{ $activeConnection ? 'Connection selected for Pulse' : 'Connect and verify an account' }}</span></div>
</div>

<section class="binance-mode-grid">
    <article class="binance-mode-card {{ $activeConnection?->environment==='testnet'?'active':'' }}" data-binance-mode-card="testnet">
        <div class="binance-mode-top"><span class="binance-mode-icon">T</span><div><small>PRACTICE ENVIRONMENT</small><h2>Binance Testnet</h2></div><span class="binance-mode-badge practice">SAFER START</span></div>
        <p>Use demo Futures balances to verify API connectivity, pair selection and execution workflows without using real funds.</p>
        <ul><li>USD‑M Futures API</li><li>No real capital</li><li>Recommended for first connection</li></ul>
        <button type="button" class="pulse-button secondary" data-binance-mode-select="testnet">Configure Testnet</button>
    </article>
    <article class="binance-mode-card {{ $activeConnection?->environment==='live'?'active':'' }} {{ $liveAllowed?'':'disabled' }}" data-binance-mode-card="live">
        <div class="binance-mode-top"><span class="binance-mode-icon live">L</span><div><small>REAL TRADING ENVIRONMENT</small><h2>Binance Live Futures</h2></div><span class="binance-mode-badge live">REAL FUNDS</span></div>
        <p>Connect your real Binance USD‑M Futures account for manual Futures execution after plan, platform and risk safeguards pass.</p>
        <ul><li>Real Futures orders</li><li>Plan + Admin permission required</li><li>Withdrawals must remain disabled</li></ul>
        @if($liveAllowed)<button type="button" class="pulse-button" data-binance-mode-select="live">Configure Live Futures</button>@else<span class="pulse-button disabled" aria-disabled="true">Live Not Enabled For This Account</span>@endif
    </article>
</section>

<div class="pulse-grid pulse-grid-two">
    <article class="pulse-work-card binance-credential-card">
        <div class="pulse-card-heading"><div><h2>Add or Replace API Connection</h2><p>Credentials are encrypted before database storage. Pulse verifies the connection automatically after you save it.</p></div></div>
        <form method="POST" action="{{ route('pulse.binance.store') }}" class="pulse-form" data-binance-connection-form>@csrf
            <div class="binance-form-environment">
                <label class="binance-form-mode active" data-binance-form-mode="testnet"><input type="radio" name="environment" value="testnet" checked><span><b>Testnet</b><small>Practice USD‑M Futures</small></span></label>
                <label class="binance-form-mode {{ $liveAllowed?'':'disabled' }}" data-binance-form-mode="live"><input type="radio" name="environment" value="live" @disabled(!$liveAllowed)><span><b>Live</b><small>Real USD‑M Futures</small></span></label>
            </div>
            <div class="pulse-field"><label for="binance-label">Connection label</label><input id="binance-label" name="label" value="Binance USD-M Futures" maxlength="100" required></div>
            <div class="pulse-field"><label for="binance-key">API key</label><div class="secret-input"><input id="binance-key" type="password" name="api_key" autocomplete="off" maxlength="255" required><button type="button" class="pulse-button secondary" data-secret-toggle="binance-key">Show</button></div></div>
            <div class="pulse-field"><label for="binance-secret">API secret</label><div class="secret-input"><input id="binance-secret" type="password" name="api_secret" autocomplete="new-password" maxlength="255" required><button type="button" class="pulse-button secondary" data-secret-toggle="binance-secret">Show</button></div></div>
            <div class="binance-key-warning"><b>Security requirement</b><span>Enable Futures trading and account read access only. Do not enable withdrawal permission. For Live, restrict the key to your server IP when Binance supports it for your setup.</span></div>
            <button class="pulse-button" type="submit">Save & Verify Connection</button>
        </form>
    </article>
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><h2>Connection Safety Checklist</h2></div>
        <div class="pulse-step-list">
            <div><span>1</span><p><b>Create a dedicated Binance API key</b><small>Keep Testnet and Live keys separate.</small></p></div>
            <div><span>2</span><p><b>Enable USD‑M Futures trading</b><small>Pulse submits Futures orders through Binance FAPI only.</small></p></div>
            <div><span>3</span><p><b>Withdrawals disabled</b><small>Pulse does not require withdrawal or transfer authority.</small></p></div>
            <div><span>4</span><p><b>Save once — Pulse verifies automatically</b><small>ABS checks account access and Futures trading permission immediately after saving.</small></p></div>
            <div><span>5</span><p><b>First verified connection becomes active</b><small>No extra activation step is required for your first successful connection. Additional verified environments remain available for deliberate switching.</small></p></div>
        </div>
    </article>
</div>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Saved Futures Connections</h2><p>You may keep both Testnet and Live credentials saved. Only one verified environment is active for Pulse execution at a time.</p></div></div>
    <div class="connection-list binance-connection-list">
        @forelse($connections as $connection)
            @php($isReady = $connection->last_tested_at && ! $connection->last_error && data_get($connection->permissions,'can_trade',false))
            <section class="{{ $connection->is_active?'active-connection':'' }}">
                <div class="connection-status-mark {{ $isReady?'ready':'blocked' }}"><i>{{ $isReady?'✓':'!' }}</i></div>
                <div><span class="pulse-badge environment-{{ $connection->environment }}">{{ $connection->environment === 'live' ? 'LIVE FUTURES' : 'TESTNET' }}</span><h3>{{ $connection->label }}</h3><p>{{ $connection->maskedKey() }}</p>@if($connection->is_active)<span class="binance-current-pill">CURRENTLY ACTIVE</span>@endif</div>
                <dl><div><dt>Last tested</dt><dd>{{ $connection->last_tested_at?->format('d M Y H:i T') ?? 'Never' }}</dd></div><div><dt>Connection</dt><dd class="{{ $connection->last_error?'negative':($connection->last_tested_at?'positive':'') }}">{{ $connection->last_error ? 'Failed' : ($connection->last_tested_at ? 'Verified' : 'Not tested') }}</dd></div><div><dt>Futures trading</dt><dd class="{{ data_get($connection->permissions,'can_trade')?'positive':'' }}">{{ data_get($connection->permissions,'can_trade') ? 'Confirmed' : 'Not confirmed' }}</dd></div></dl>
                <div class="pulse-actions">
                    <form method="POST" action="{{ route('pulse.binance.test',$connection) }}">@csrf<button class="pulse-button secondary">Test Connection</button></form>
                    @if($isReady && !$connection->is_active)<form method="POST" action="{{ route('pulse.binance.activate',$connection) }}">@csrf<button class="pulse-button">Use {{ $connection->environment==='live'?'Live':'Testnet' }}</button></form>@endif
                    @if($connection->is_active)<span class="pulse-button disabled">Active</span>@endif
                    <form method="POST" action="{{ route('pulse.binance.destroy',$connection) }}">@csrf @method('DELETE')<button class="pulse-button danger" data-confirm="Remove these encrypted credentials? This does not close exchange positions or cancel exchange orders.">Remove</button></form>
                </div>
                @if($connection->last_error)<div class="connection-error"><b>Latest test error</b><span>{{ $connection->last_error }}</span></div>@endif
            </section>
        @empty<div class="pulse-card-empty">No Binance connection is configured. Start with a Practice / Testnet key; Pulse will save, verify and activate the first successful connection automatically.</div>@endforelse
    </div>
</article>
<p class="pulse-accuracy-note">Live Futures execution remains subject to your Pulse plan, Admin platform controls, verified API trading permission, emergency stop, risk limits, position limits and pre-trade validation.</p>
@endsection
