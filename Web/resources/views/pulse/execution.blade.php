@extends('pulse.layout')
@section('title','Trade Execution')
@section('heading','Trade Execution')
@section('content')
<?php
    $money = fn ($value, $signed = false) => ($signed && (float) $value >= 0 ? '+' : ((float) $value < 0 ? '-' : '')).'$'.number_format(abs((float) $value), 2);
    $number = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $percent = fn ($value, $decimals = 1) => number_format((float) $value, $decimals).'%';
    $selected = $page['selected_signal'];
    $ticket = $page['ticket'];
    $calculation = $page['calculation'];
    $asset = $selected ? str_replace('/USDT', '', $selected['pair']) : 'Asset';
    $expires = $selected && $selected['expires_at'] ? max(0, (int) ceil(now()->diffInMinutes($selected['expires_at']))).' min' : '—';
    $assetMark = $asset === 'BTC' ? '₿' : substr($asset, 0, 1);
    $pendingCommitments = max(0, $page['position_capacity_used'] - $page['open_positions']);
    $positionCapacityNote = $page['position_limit_reached']
        ? ($pendingCommitments > 0 ? $pendingCommitments.' pending '.($pendingCommitments === 1 ? 'order' : 'orders').' · capacity reached' : 'Configured limit reached')
        : ($pendingCommitments > 0 ? $pendingCommitments.' pending '.($pendingCommitments === 1 ? 'order' : 'orders').' included in limit' : 'Within configured limit');
    $executionMessage = $page['execution_ready']
        ? 'All pre-trade safeguards have passed. Review the final ticket before submission.'
        : ($page['position_limit_reached']
            ? 'The order calculation is valid, but the open-position safeguard prevents submission.'
            : 'The order remains on hold until every pre-trade safeguard passes.');
    $can = fn (string $capability): bool => (bool) data_get($page, "capabilities.{$capability}.enabled", false);
?>
<div class="pp-page pp-execution">
    <header class="pp-page-head">
        <div class="pp-title"><h1>Trade Execution</h1><p>Review order parameters, calculated exposure and safeguards before submission.</p></div>
        <div class="pp-head-actions">
            <?php if ($can('trades')): ?><form method="POST" action="{{ route('pulse.trades.sync') }}">@csrf<button class="pp-button" type="submit">@include('pulse.partials.icon', ['name' => 'refresh']) Sync Orders</button></form><?php else: ?><span class="pp-button disabled" aria-disabled="true">@include('pulse.partials.icon', ['name' => 'refresh']) Sync Not Included</span><?php endif; ?>
            <?php if ($can('orders')): ?><a class="pp-button" href="{{ route('pulse.orders') }}">@include('pulse.partials.icon', ['name' => 'list']) Order History</a><?php else: ?><span class="pp-button disabled" aria-disabled="true">@include('pulse.partials.icon', ['name' => 'list']) Orders Not Included</span><?php endif; ?>
            <div class="pp-environment"><b>BINANCE {{ strtoupper($page['settings']->environment) }}</b><span class="{{ $page['connection_ready'] ? 'connected' : '' }}">{{ $page['connection_ready'] ? 'Connected' : 'Connection check required' }}</span><small>Last sync: {{ $page['connection']?->last_tested_at?->diffForHumans() ?? 'not yet' }}</small></div>
        </div>
    </header>

    <section class="pp-metrics five">
        <article class="pp-metric"><span class="pp-metric-icon">@include('pulse.partials.icon', ['name' => 'document'])</span><div class="pp-metric-copy"><small>Pending Tickets</small><strong>{{ $page['pending_tickets'] }}</strong><em>Awaiting final review</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon">@include('pulse.partials.icon', ['name' => 'calendar'])</span><div class="pp-metric-copy"><small>Orders Today</small><strong>{{ $page['orders_today'] }}</strong><em>{{ $page['filled_today'] }} filled · {{ $page['blocked_today'] }} blocked</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon cyan">@include('pulse.partials.icon', ['name' => 'pulse'])</span><div class="pp-metric-copy"><small>Fill Rate</small><strong>{{ $percent($page['fill_rate']) }}</strong><em>{{ ucfirst($page['settings']->environment) }} orders today</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon orange">@include('pulse.partials.icon', ['name' => 'briefcase'])</span><div class="pp-metric-copy"><small class="pp-warning">Open Positions</small><strong>{{ $page['open_positions'] }} / {{ $page['maximum_open_positions'] }}</strong><em>{{ $positionCapacityNote }}</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon orange">@include('pulse.partials.icon', ['name' => 'clock'])</span><div class="pp-metric-copy"><small>Execution Status</small><strong class="pp-warning">{{ $page['execution_ready'] ? 'READY' : 'ON HOLD' }}</strong><em>{{ $page['position_limit_reached'] ? 'Position limit safeguard' : 'Review pre-trade checks' }}</em></div></article>
    </section>

    <?php if ($page['position_limit_reached']): ?>
        <section class="pp-limit-banner">@include('pulse.partials.icon', ['name' => 'alert'])<div class="pp-limit-copy"><strong>Position limit reached</strong><span>You currently have {{ $page['position_capacity_used'] }} of {{ $page['maximum_open_positions'] }} permitted open positions or pending orders. Review active commitments before submitting another order.</span></div><div class="pp-limit-actions"><?php if ($can('orders')): ?><a class="pp-button warning" href="{{ route('pulse.positions') }}">View Open Positions</a><?php endif; ?><?php if ($can('settings')): ?><a class="pp-button warning" href="{{ route('pulse.risk.index') }}">Review Risk Controls</a><?php endif; ?></div></section>
    <?php endif; ?>

    <section class="pp-execution-grid">
        <div class="pp-execution-left">
            <article class="pp-card pp-selected-signal">
                <h2>Selected Signal</h2>
                <?php if ($selected): ?>
                    <div class="pp-signal-line"><span class="pp-coin">{{ $assetMark }}</span><strong>{{ $selected['pair'] }}</strong><div class="pp-signal-tags"><span class="pp-pill {{ strtolower($selected['direction']) }}">{{ $selected['direction'] }}</span><span class="pp-pill high">{{ $selected['strategy'] }}</span><span class="pp-pill high">Score {{ number_format($selected['score'], 0) }}</span><span class="pp-pill high">{{ $selected['timeframe'] }}</span></div></div>
                    <div class="pp-signal-values"><div><small>Entry Zone</small><strong>{{ number_format($selected['entry_low'], 0) }}–{{ number_format($selected['entry_high'], 0) }}</strong></div><div><small>Stop Loss</small><strong>{{ number_format($selected['stop_loss'], 0) }}</strong></div><div><small>Take Profit</small><strong>{{ number_format($selected['take_profit'], 0) }}</strong></div><div><small>Signal Expires</small><strong>{{ $expires }}</strong></div></div>
                    <div class="pp-signal-foot"><span class="pp-positive">{{ $selected['status'] }} · Reviewed</span><a class="pp-link" href="{{ route('pulse.signals.show', $selected['id']) }}">View Signal Evidence @include('pulse.partials.icon', ['name' => 'chevron-right'])</a></div>
                <?php else: ?><div class="pp-empty">No actionable signal is available. Review the signal queue before preparing an order.</div><?php endif; ?>
            </article>

            <div class="pp-ticket-row">
                <form id="pulse-order-ticket" class="pp-card pp-order-ticket" method="{{ $selected ? 'POST' : 'GET' }}" action="{{ $selected ? route('pulse.signals.execute', $selected['id']) : route('pulse.signals.index') }}" data-order-ticket data-account-equity="{{ $page['account']['equity'] }}">
                    <?php if ($selected): ?>@csrf<?php endif; ?>
                    <h2>Order Ticket</h2>
                    <input type="hidden" name="environment" value="{{ $page['settings']->environment }}">
                    <input type="hidden" name="position_side" value="{{ $page['settings']->position_mode ?: 'BOTH' }}">
                    <div class="pp-ticket-fields">
                        <label class="pp-ticket-field"><span>Order Type</span><select name="order_type"><option value="LIMIT" {{ $ticket['order_type'] === 'LIMIT' ? 'selected' : '' }}>Limit</option><option value="MARKET" {{ $ticket['order_type'] === 'MARKET' ? 'selected' : '' }}>Market</option></select></label>
                        <label class="pp-ticket-field"><span>Direction</span><select disabled><option>{{ ucfirst(strtolower($ticket['direction'])) }}</option></select></label>
                        <label class="pp-ticket-field"><span>Limit Price</span><input name="price" type="number" step="any" value="{{ $ticket['limit_price'] }}"></label>
                        <label class="pp-ticket-field"><span>Quantity</span><input name="quantity" type="number" step="any" value="{{ $ticket['quantity'] }}"></label>
                        <label class="pp-ticket-field"><span>Leverage</span><select name="leverage"><?php foreach ([1,2,3,5,10,15,20] as $value): ?><option value="{{ $value }}" {{ $ticket['leverage'] === $value ? 'selected' : '' }}>{{ $value }}×</option><?php endforeach; ?></select></label>
                        <label class="pp-ticket-field"><span>Time in Force</span><select name="time_in_force"><?php foreach (['GTC','IOC','FOK'] as $value): ?><option value="{{ $value }}" {{ $ticket['time_in_force'] === $value ? 'selected' : '' }}>{{ $value }}</option><?php endforeach; ?></select></label>
                        <label class="pp-ticket-field"><span>Stop Loss</span><input name="stop_loss" type="number" step="any" value="{{ $ticket['stop_loss'] }}"></label>
                        <label class="pp-ticket-field"><span>Take Profit</span><input name="take_profit" type="number" step="any" value="{{ $ticket['take_profit'] }}"></label>
                        <label class="pp-ticket-field"><span>Reduce Only</span><select disabled aria-label="Reduce Only is unavailable for a new signal entry"><option>Off</option></select></label>
                        <label class="pp-ticket-field"><span>Client Reference</span><input name="client_reference" value="{{ $ticket['client_reference'] }}"></label>
                    </div>
                    <label class="pp-confirm"><input type="checkbox" name="confirmed_review" value="1" checked required> Confirm signal and risk review</label>
                    <div class="pp-ticket-actions"><button class="pp-button muted" type="button" data-ticket-reset>Reset Ticket</button><button class="pp-button" type="button" data-ticket-calculate>Recalculate</button></div>
                </form>

                <article class="pp-card pp-calculation"><h2>Order Calculation</h2><div class="pp-calculation-list"><div><span>Notional Value</span><strong class="pp-positive" data-calculation="notional">{{ $money($calculation['notional_value']) }}</strong></div><div><span>Required Margin</span><strong class="pp-positive" data-calculation="margin">{{ $money($calculation['required_margin']) }}</strong></div><div><span>Estimated Risk</span><strong class="pp-negative" data-calculation="risk">{{ $money($calculation['estimated_risk']) }}</strong></div><div><span>Potential Reward</span><strong class="pp-positive" data-calculation="reward">{{ $money($calculation['potential_reward']) }}</strong></div><div><span>Risk / Reward</span><strong class="pp-cyan" data-calculation="ratio">1:{{ $number($calculation['risk_reward']) }}</strong></div><div><span>Configured Risk</span><strong class="pp-cyan" data-calculation="risk-percent">{{ $percent($calculation['configured_risk'], 2) }}</strong></div><div><span>Available Balance</span><strong>{{ $money($calculation['available_balance']) }}</strong></div></div></article>
            </div>

            <article class="pp-card pp-orders"><div class="pp-section-head"><h2>Recent {{ ucfirst($page['settings']->environment) }} Orders</h2></div><div class="pp-results-scroll"><table class="pp-table"><thead><tr><th>Time</th><th>Pair</th><th>Side</th><th>Type</th><th>Quantity</th><th>Average Fill</th><th>Status</th><th>Reference</th></tr></thead><tbody><?php foreach ($page['recent_orders'] as $order): ?><tr><td>{{ $order['created_at']?->isToday() ? $order['created_at']->format('H:i') : $order['created_at']?->diffForHumans() }}</td><td>{{ $order['pair'] }}</td><td class="{{ $order['side'] === 'SHORT' ? 'pp-negative' : 'pp-positive' }}">{{ $order['side'] }}</td><td>{{ $order['order_type'] }}</td><td>{{ number_format($order['quantity'], 3) }} {{ str_replace('/USDT', '', $order['pair']) }}</td><td>{{ $number($order['entry_price']) }}</td><td class="pp-positive">{{ in_array($order['status'], ['open','closed','closing'], true) ? 'Filled' : ucfirst($order['status']) }}</td><td>{{ $order['reference'] }}</td></tr><?php endforeach; ?><?php if (count($page['recent_orders']) === 0): ?><tr><td colspan="8" class="pp-empty">No recent orders are available.</td></tr><?php endif; ?></tbody></table></div><div class="pp-orders-link"><?php if ($can('orders')): ?><a class="pp-link" href="{{ route('pulse.orders') }}">View all orders @include('pulse.partials.icon', ['name' => 'chevron-right'])</a><?php else: ?><span class="pp-link disabled">Order history is not included</span><?php endif; ?></div></article>
        </div>

        <div class="pp-execution-right">
            <article class="pp-card pp-validation"><h2>Pre-Trade Validation <span>{{ $page['passed_checks'] }} passed · {{ $page['blocked_checks'] }} blocked</span></h2><div class="pp-checks"><?php foreach ($page['checks'] as $check): ?><div class="{{ $check['passed'] ? '' : 'blocked' }}">@include('pulse.partials.icon', ['name' => $check['passed'] ? 'check' : 'alert'])<span>{{ $check['label'] }}</span><strong class="{{ $check['passed'] ? 'pp-positive' : 'pp-warning' }}">{{ $check['value'] }}</strong></div><?php endforeach; ?></div><div class="pp-validation-note {{ $page['execution_ready'] ? 'ready' : '' }}">@include('pulse.partials.icon', ['name' => $page['execution_ready'] ? 'check' : 'info']) {{ $page['execution_ready'] ? 'All safeguards passed. Final review is required.' : 'Submission unavailable until all safeguards pass.' }}</div></article>
            <article class="pp-card pp-account"><h2>Account &amp; Exposure</h2><div class="pp-account-list"><div><span>Account Equity</span><strong>{{ $money($page['account']['equity']) }}</strong></div><div><span>Current Exposure</span><strong>{{ $percent($page['account']['current_exposure'], 0) }}</strong></div><div><span>Open Risk</span><strong>{{ $percent($page['account']['open_risk'], 2) }}</strong></div><div><span>Daily Risk</span><strong>{{ $number($page['account']['daily_risk'], 1) }} / {{ $number($page['account']['daily_risk_limit'], 1) }}%</strong></div><div><span>Available Capacity</span><strong>{{ $percent($page['account']['available_capacity'], 0) }}</strong></div><div><span>Open Positions</span><strong>{{ $page['open_positions'] }}</strong></div></div></article>
            <article class="pp-card pp-decision-box"><h2>Execution Decision</h2><p>{{ $executionMessage }}</p><div class="pp-decision-actions-grid"><button class="pp-button {{ $page['execution_ready'] ? 'primary' : 'disabled' }}" type="submit" form="pulse-order-ticket" {{ $page['execution_ready'] && $selected ? '' : 'disabled' }}>@include('pulse.partials.icon', ['name' => $page['execution_ready'] ? 'send' : 'lock']) Submit {{ ucfirst($page['settings']->environment) }} Order</button><button class="pp-button" type="button" form="pulse-order-ticket" data-ticket-draft>Save Draft</button></div><small data-draft-status>No order has been sent to Binance.</small></article>
        </div>
    </section>
</div>
@endsection
