@extends('pulse.layout')
@section('title','Pulse Risk Controls')
@section('heading','Risk Controls')
@section('content')
@php
    $dailyLimit = (float)$settings->daily_loss_limit;
    $dailyUsed = max(0, -$todayPnl);
    $dailyUsage = $dailyLimit > 0 ? min(100, (int)round(($dailyUsed/$dailyLimit)*100)) : 0;
@endphp
<div class="pulse-page-hero">
    <div><span>ACCOUNT SAFETY</span><h1>Risk Controls</h1><p>Set account-level execution limits and review Pulse records that may require immediate attention. Changes apply to future order requests only.</p></div>
    <span class="risk-master-state {{ $settings->emergency_stop ? 'stopped' : 'ready' }}"><i></i>{{ $settings->emergency_stop ? 'EMERGENCY STOP ACTIVE' : 'EXECUTION CONTROLS ACTIVE' }}</span>
</div>

<div class="pulse-metric-row four">
    <article><span class="metric-icon">◇</span><div><small>POSITION UTILIZATION</small><strong>{{ $planMaxOpen > 0 ? $riskUtilization.'%' : 'Not enabled' }}</strong><em>{{ $planMaxOpen > 0 ? $openCount.' of '.$maxOpen.' local open/in-flight' : 'Execution is not included in this plan' }}</em></div></article>
    <article><span class="metric-icon">◷</span><div><small>TODAY'S REALIZED P&amp;L</small><strong class="{{ $todayPnl>=0?'positive':'negative' }}">{{ number_format($todayPnl,4) }}</strong><em>Synchronized closed trades</em></div></article>
    <article><span class="metric-icon">!</span><div><small>PROTECTION REVIEWS</small><strong class="{{ $protectionIssues>0?'negative':'' }}">{{ $protectionIssues }}</strong><em>Failed or review required</em></div></article>
    <article><span class="metric-icon">◈</span><div><small>RISK PER TRADE</small><strong>{{ number_format((float)$settings->risk_per_trade_percent,2) }}%</strong><em>Configured maximum input</em></div></article>
</div>

<div class="pulse-grid pulse-grid-two">
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><div><h2>Risk Limit Configuration</h2><p>These limits are also constrained by installation and plan maximums.</p></div><span>{{ $plan?->name ?? 'Pulse' }}</span></div>
        <form method="POST" action="{{ route('pulse.risk.update') }}" class="pulse-form">@csrf @method('PUT')
            <div class="pulse-form-grid">
                <div class="pulse-field"><label for="risk-percent">Risk per trade (%)</label><input id="risk-percent" type="number" name="risk_per_trade_percent" min="0.1" max="{{ config('pulse.risk.max_risk_per_trade',5) }}" step="0.1" value="{{ $settings->risk_per_trade_percent }}" required><small>Used as a sizing control; it does not guarantee the final loss amount.</small></div>
                <div class="pulse-field"><label for="daily-loss">Daily realized loss limit</label><input id="daily-loss" type="number" name="daily_loss_limit" min="0" step="0.01" value="{{ $settings->daily_loss_limit }}" required><small>Set 0 to disable. Evaluated from synchronized closed Pulse trades.</small></div>
                <div class="pulse-field"><label for="risk-positions">Maximum open positions</label><?php if ($planMaxOpen > 0): ?><input id="risk-positions" type="number" name="max_open_positions" min="1" max="{{ $planMaxOpen }}" value="{{ $maxOpen }}" required><small>Current plan maximum: {{ $planMaxOpen }}.</small><?php else: ?><input type="hidden" name="max_open_positions" value="1"><input id="risk-positions" value="Not included in this plan" disabled><small>Execution limits do not apply because this plan has no open-position allowance.</small><?php endif; ?></div>
                <div class="pulse-field"><label for="risk-stop">Default stop loss distance (%)</label><input id="risk-stop" type="number" name="stop_loss_percent" min="0.1" max="25" step="0.1" value="{{ $settings->stop_loss_percent }}" required></div>
                <div class="pulse-field"><label for="risk-target">Default take profit distance (%)</label><input id="risk-target" type="number" name="take_profit_percent" min="0.1" max="50" step="0.1" value="{{ $settings->take_profit_percent }}" required></div>
            </div>
            <button class="pulse-button" type="submit">Save Risk Controls</button>
        </form>
    </article>
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><h2>Control Status</h2></div>
        <div class="risk-control-status">
            <div><span><b>Concurrent position limit</b><small>{{ $planMaxOpen > 0 ? $openCount.' local open/in-flight record(s) against a limit of '.$maxOpen.'.' : 'Open-position execution is not included in the current plan.' }}</small></span><em>{{ $planMaxOpen > 0 ? $riskUtilization.'%' : 'N/A' }}</em><i style="--value:{{ $riskUtilization }}%"><u></u></i></div>
            <div><span><b>Daily realized loss limit</b><small>{{ $dailyLimit > 0 ? number_format($dailyUsed,4).' used of '.number_format($dailyLimit,4) : 'Disabled — no daily realized loss threshold configured.' }}</small></span><em>{{ $dailyLimit > 0 ? $dailyUsage.'%' : 'Off' }}</em><i style="--value:{{ $dailyUsage }}%"><u></u></i></div>
            <div><span><b>Exchange-side protection</b><small>{{ $protectionIssues > 0 ? $protectionIssues.' local record(s) require immediate review.' : 'No active local protection failure is recorded.' }}</small></span><em class="{{ $protectionIssues>0?'negative':'positive' }}">{{ $protectionIssues>0?'Review':'Clear' }}</em></div>
            <div><span><b>Emergency stop</b><small>{{ $settings->emergency_stop ? 'New execution is blocked; exchange state still requires review.' : 'New execution remains subject to all other controls.' }}</small></span><em class="{{ $settings->emergency_stop?'negative':'positive' }}">{{ $settings->emergency_stop?'Active':'Inactive' }}</em></div>
        </div>
        <?php if (! ($settings->emergency_stop)): ?>
            <form method="POST" action="{{ route('pulse.emergency-stop') }}" class="emergency-stop-form">@csrf<button class="pulse-button danger" data-confirm="Enable the emergency stop, cancel pending Pulse orders and request closure of active Pulse positions? Review Binance immediately after this action.">Activate Emergency Stop</button><p>This action affects eligible Pulse records and sends exchange requests. It cannot guarantee that every exchange request succeeds.</p></form>
        <?php else: ?>
            <a class="pulse-button secondary" href="{{ route('pulse.settings.edit') }}#environment">Review Before Disabling Stop</a>
        <?php endif; ?>
    </article>
</div>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Open Risk Register</h2><p>Local Pulse records counted against the concurrent-position limit.</p></div><a href="{{ route('pulse.positions') }}">Compare with Binance</a></div>
    <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Pair</th><th>Side</th><th>Status</th><th>Protection</th><th>Entry</th><th>Stop</th><th>Target</th><th>Unrealized</th><th>Last Sync</th><th></th></tr></thead><tbody><?php $__absForelseEmpty1 = true; foreach ($openTrades as $trade): $__absForelseEmpty1 = false; ?><tr><td><b>{{ $trade->symbol }}</b></td><td>{{ $trade->side }}</td><td><span class="pulse-badge status-{{ $trade->status }}">{{ strtoupper(str_replace('_',' ',$trade->status)) }}</span></td><td>{{ strtoupper(str_replace('_',' ',$trade->protection_status ?: 'not recorded')) }}</td><td>{{ $trade->entry_price ?: '—' }}</td><td>{{ $trade->stop_loss ?: '—' }}</td><td>{{ $trade->take_profit ?: '—' }}</td><td class="{{ (float)$trade->unrealized_pnl>=0?'positive':'negative' }}">{{ number_format((float)$trade->unrealized_pnl,4) }}</td><td>{{ $trade->last_synced_at?->diffForHumans() ?? 'Not yet' }}</td><td><a class="review-link" href="{{ route('pulse.trades.show',$trade) }}">Review</a></td></tr><?php endforeach; if ($__absForelseEmpty1): ?><tr><td colspan="10">No local open or in-flight Pulse record.</td></tr><?php endif; ?></tbody></table></div>
</article>
<p class="pulse-accuracy-note">Pulse limits are application controls. They do not replace Binance account controls, liquidation rules or direct exchange monitoring. Synchronized local data can lag the exchange.</p>
@endsection
