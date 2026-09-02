@extends('pulse.layout')
@section('title','Pulse Dashboard')
@section('heading','Pulse Dashboard')
@section('content')
<?php
    $money = fn ($value, $signed = false) => ($signed && (float) $value >= 0 ? '+' : ((float) $value < 0 ? '-' : '')).'$'.number_format(abs((float) $value), 2);
    $percent = fn ($value, $signed = false, $decimals = 1) => ($signed && (float) $value >= 0 ? '+' : '').number_format((float) $value, $decimals).'%';
    $number = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $performance = collect($page['performance_points']);
    $performanceValues = $performance->pluck('value');
    $performanceMin = min(0, (float) ($performanceValues->min() ?? 0));
    $performanceMax = max(1, (float) ($performanceValues->max() ?? 0));
    $performanceRange = max(1, $performanceMax - $performanceMin);
    $performanceCoordinates = $performance->map(function (array $point) use ($performanceMin, $performanceRange): string {
        $y = 96 - (((float) $point['value'] - $performanceMin) / $performanceRange) * 86;
        return number_format((float) $point['x'], 2, '.', '').','.number_format($y, 2, '.', '');
    })->implode(' ');
    $chartArea = '0,100 '.$performanceCoordinates.' 100,100';
    $chartMax = max(600, ceil($performanceMax / 600) * 600);
    $chartMin = min(-600, floor($performanceMin / 600) * 600);
    $chartLabels = collect(range(0, 5))->map(function (int $index) use ($page): string {
        $days = $page['period'] === '7d' ? 7 : ($page['period'] === '24h' ? 1 : 30);
        return now()->subDays($days)->addDays((int) round(($days / 5) * $index))->format('M j');
    });
    $accountActive = auth()->user()->status === 'active';
    $coreOperational = $accountActive && $page['connection_ready'] && ! $page['settings']->emergency_stop;
    $riskWithinLimits = $page['risk_utilization'] <= 100
        && ! collect($page['open_positions'])->contains(fn (array $position): bool => in_array($position['protection_status'], ['failed', 'review_required'], true));
    $can = fn (string $capability): bool => (bool) data_get($page, "capabilities.{$capability}.enabled", false);
?>

<div class="pp-page pp-dashboard">
    <header class="pp-page-head">
        <div class="pp-title">
            <h1>Pulse Dashboard</h1>
            <p>Track your trading activity, signal engagement and risk position in one consolidated view.</p>
        </div>
        <div class="pp-head-actions">
            <nav class="pp-period" aria-label="Dashboard period">
                <?php foreach (['24h' => '24H', '7d' => '7D', '30d' => '30D', 'all' => 'ALL TIME'] as $key => $label): ?>
                    <a class="{{ $page['period'] === $key ? 'active' : '' }}" href="{{ route('pulse.dashboard', ['period' => $key]) }}">{{ $label }}</a>
                <?php endforeach; ?>
            </nav>
            <?php if ($can('scanner')): ?><a class="pp-button" href="{{ route('pulse.scanner') }}">@include('pulse.partials.icon', ['name' => 'search']) Run Market Scan</a><?php else: ?><span class="pp-button disabled" aria-disabled="true">@include('pulse.partials.icon', ['name' => 'search']) Scanner Not Included</span><?php endif; ?>
            <?php if ($can('signals')): ?><a class="pp-button primary" href="{{ route('pulse.signals.index') }}">@include('pulse.partials.icon', ['name' => 'pulse']) Review Signals</a><?php else: ?><span class="pp-button disabled" aria-disabled="true">@include('pulse.partials.icon', ['name' => 'pulse']) Signals Not Included</span><?php endif; ?>
            <div class="pp-environment">
                <b>BINANCE {{ strtoupper($page['settings']->environment) }}</b>
                <span class="{{ $page['connection_ready'] ? 'connected' : '' }}">{{ $page['connection_ready'] ? 'Connected' : 'Connection check required' }}</span>
                <small>Last sync: {{ $page['connection']?->last_tested_at?->diffForHumans() ?? 'not yet' }}</small>
            </div>
        </div>
    </header>

    <h2 class="pp-dashboard-summary">Your {{ $page['period_label'] }} Summary</h2>
    <section class="pp-metrics five" aria-label="Trading summary">
        <article class="pp-metric">
            <span class="pp-metric-icon green">@include('pulse.partials.icon', ['name' => 'trend'])</span>
            <div class="pp-metric-copy"><small>Net P&amp;L</small><strong class="{{ $page['net_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['net_pnl'], true) }}</strong><em><span class="{{ $page['net_pnl_percent'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $percent($page['net_pnl_percent'], true) }}</span> over selected period</em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon">@include('pulse.partials.icon', ['name' => 'briefcase'])</span>
            <div class="pp-metric-copy"><small>Executed Trades</small><strong>{{ $page['executed_trades'] }}</strong><em><span class="pp-positive">{{ $page['winning_trades'] }} profitable</span> · <span class="pp-negative">{{ $page['losing_trades'] }} losing</span></em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon orange">@include('pulse.partials.icon', ['name' => 'target'])</span>
            <div class="pp-metric-copy"><small>Win Rate</small><strong>{{ $percent($page['win_rate']) }}</strong><em>Profit factor {{ $number($page['profit_factor']) }}</em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon cyan">@include('pulse.partials.icon', ['name' => 'pulse'])</span>
            <div class="pp-metric-copy"><small>Signals Actioned</small><strong>{{ $page['signals_actioned'] }} / {{ $page['signals_received'] }}</strong><em>{{ $percent($page['signal_engagement']) }} engagement</em></div>
        </article>
        <article class="pp-metric">
            <span class="pp-metric-icon">@include('pulse.partials.icon', ['name' => 'briefcase'])</span>
            <div class="pp-metric-copy"><small>Open Positions</small><strong>{{ $page['open_position_count'] }}</strong><em class="{{ $page['unrealized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['unrealized_pnl'], true) }} unrealized</em></div>
        </article>
    </section>

    <section class="pp-dashboard-main">
        <article class="pp-card pp-performance">
            <div class="pp-performance-top">
                <div><h2 class="pp-card-title">Performance Overview</h2><p class="pp-card-subtitle">{{ strtolower($page['period_label']) }} net performance</p></div>
                <strong>{{ $money($page['net_pnl'], true) }}</strong>
            </div>
            <div class="pp-chart-wrap">
                <div class="pp-chart-y"><span>{{ $money($chartMax) }}</span><span>{{ $money($chartMax * .75) }}</span><span>{{ $money($chartMax * .5) }}</span><span>$0</span><span>{{ $money($chartMin) }}</span></div>
                <div class="pp-chart">
                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Cumulative net performance chart">
                        <defs><linearGradient id="pp-chart-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#00c998" stop-opacity=".48"/><stop offset="1" stop-color="#00c998" stop-opacity=".08"/></linearGradient></defs>
                        <polygon class="area" points="{{ $chartArea }}"/>
                        <polyline class="line" points="{{ $performanceCoordinates }}"/>
                    </svg>
                </div>
                <div class="pp-chart-x"><?php foreach ($chartLabels as $label): ?><span>{{ $label }}</span><?php endforeach; ?></div>
            </div>
            <div class="pp-performance-stats">
                <div><small>Realized P&amp;L</small><strong class="{{ $page['realized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['realized_pnl'], true) }}</strong></div>
                <div><small>Unrealized P&amp;L</small><strong class="{{ $page['unrealized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['unrealized_pnl'], true) }}</strong></div>
                <div><small>Avg. Closed Trade</small><strong class="{{ $page['average_closed_trade'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['average_closed_trade'], true) }}</strong></div>
                <div><small>Avg. Planned R:R</small><strong>1:{{ $number($page['average_risk_reward']) }}</strong></div>
                <div><small>Maximum Drawdown</small><strong class="pp-negative">-{{ $percent($page['maximum_drawdown']) }}</strong></div>
            </div>
            <div style="display:flex;justify-content:flex-end;padding-top:3px"><?php if ($can('reports')): ?><a class="pp-link" href="{{ route('pulse.reports') }}">View performance report @include('pulse.partials.icon', ['name' => 'chevron-right'])</a><?php else: ?><span class="pp-link disabled">Performance reporting is not included</span><?php endif; ?></div>
        </article>

        <article class="pp-card pp-standing">
            <div class="pp-section-head"><h2>Pulse Account Standing</h2></div>
            <div class="pp-standing-list">
                <div><span>Plan</span><span>{{ $page['plan_name'] }}</span></div>
                <div><span>Account Status</span><span class="{{ $accountActive ? 'pp-positive' : 'pp-warning' }}">{{ ucfirst(auth()->user()->status) }}</span></div>
                <div><span>Trading Mode</span><span>Binance {{ ucfirst($page['settings']->environment) }}</span></div>
                <div><span>Exchange Connection</span><span class="{{ $page['connection_ready'] ? 'pp-positive' : 'pp-warning' }}">{{ $page['connection_ready'] ? 'Connected' : 'Review Required' }}</span></div>
                <div><span>Risk Controls</span><span class="{{ $page['settings']->emergency_stop ? 'pp-warning' : 'pp-positive' }}">{{ $page['settings']->emergency_stop ? 'Emergency Stop' : 'Configured' }}</span></div>
                <div><span>Service Access</span><span class="{{ auth()->user()->hasPulseAccess() ? 'pp-positive' : 'pp-warning' }}">{{ auth()->user()->hasPulseAccess() ? 'Active' : 'Review Required' }}</span></div>
                <div><span>Member Since</span><span>{{ $page['member_since']?->format('M Y') ?? '—' }}</span></div>
            </div>
            <div class="pp-standing-banner {{ $coreOperational ? '' : 'warning' }}">@include('pulse.partials.icon', ['name' => $coreOperational ? 'check' : 'alert']) {{ $coreOperational ? 'Account active · All core controls operational' : 'Account active · One or more controls require review' }}</div>
            <div class="pp-standing-footer"><a class="pp-link" href="{{ route('profile') }}">View account details @include('pulse.partials.icon', ['name' => 'chevron-right'])</a></div>
        </article>
    </section>

    <section class="pp-dashboard-secondary">
        <article class="pp-card pp-outcome">
            <h2 class="pp-card-title">Trade Outcome Summary</h2>
            <div class="pp-outcome-main">
                <div class="pp-outcome-count"><strong class="pp-positive">{{ $page['winning_trades'] }}</strong><span>Profitable</span></div>
                <div class="pp-donut" style="background:conic-gradient(var(--pulse-premium-red) 0 {{ 100 - $page['win_rate'] }}%,var(--pulse-premium-green) {{ 100 - $page['win_rate'] }}% 100%)"><span>{{ $page['executed_trades'] }}<small>Trades</small></span></div>
                <div class="pp-outcome-count"><strong class="pp-negative">{{ $page['losing_trades'] }}</strong><span>Losing</span></div>
            </div>
            <div class="pp-four-stats">
                <div><small>Best Trade</small><strong class="pp-positive">{{ $money($page['best_trade'], true) }}</strong></div>
                <div><small>Average Win</small><strong class="pp-positive">{{ $money($page['average_win'], true) }}</strong></div>
                <div><small>Average Loss</small><strong class="pp-negative">{{ $money($page['average_loss'], true) }}</strong></div>
                <div><small>Profit Factor</small><strong>{{ $number($page['profit_factor']) }}</strong></div>
            </div>
        </article>

        <article class="pp-card pp-signal-activity">
            <h2 class="pp-card-title">Signal Activity</h2>
            <div class="pp-signal-activity-body">
                <div class="pp-activity-list">
                    <div>@include('pulse.partials.icon', ['name' => 'history'])<span>Signals Received</span><b>{{ $page['signals_received'] }}</b></div>
                    <div>@include('pulse.partials.icon', ['name' => 'shield'])<span>High-Conviction</span><b>{{ $page['high_conviction_signals'] }}</b></div>
                    <div>@include('pulse.partials.icon', ['name' => 'send'])<span>Actioned</span><b>{{ $page['signals_actioned'] }}</b></div>
                    <div>@include('pulse.partials.icon', ['name' => 'clock'])<span>Expired</span><b>{{ $page['expired_signals'] }}</b></div>
                    <div>@include('pulse.partials.icon', ['name' => 'alert'])<span>Dismissed</span><b>{{ $page['dismissed_signals'] }}</b></div>
                    <div>@include('pulse.partials.icon', ['name' => 'shield'])<span>Average Setup Score</span><b>{{ number_format($page['average_setup_score'], 0) }}</b></div>
                </div>
                <div class="pp-engagement"><small>Engagement</small><strong>{{ $percent($page['signal_engagement']) }}</strong><div class="pp-progress"><i style="width:{{ min(100, $page['signal_engagement']) }}%"></i></div><div class="pp-decision-note">Signal scores are provided for decision support, not guaranteed outcomes.</div></div>
            </div>
            <div class="pp-signal-activity-footer"><?php if ($can('signals')): ?><a class="pp-link" href="{{ route('pulse.signals.index', ['status' => 'history']) }}">Review signal history @include('pulse.partials.icon', ['name' => 'chevron-right'])</a><?php else: ?><span class="pp-link disabled">Signal history is not included</span><?php endif; ?></div>
        </article>

        <article class="pp-card pp-risk">
            <h2 class="pp-card-title">Risk &amp; Exposure</h2>
            <div class="pp-risk-main">
                <div class="pp-risk-ring" style="--risk:{{ min(100, $page['risk_utilization']) }}%"><span>{{ number_format($page['risk_utilization'], 0) }}%</span></div>
                <div class="pp-risk-list">
                    <div><span>Daily Risk</span><span>{{ $number($page['daily_risk_percent'], 1) }} / {{ $number($page['daily_risk_limit'], 1) }}%</span></div>
                    <div><span>Open Risk</span><span>{{ $percent($page['open_risk_percent'], false, 2) }}</span></div>
                    <div><span>Available Capacity</span><span>{{ $percent($page['available_capacity'], false, 0) }}</span></div>
                    <div><span>Portfolio Exposure</span><span class="{{ $page['portfolio_exposure'] === 'High' ? 'pp-negative' : 'pp-warning' }}">{{ $page['portfolio_exposure'] }}</span></div>
                </div>
            </div>
            <div class="pp-risk-note {{ $riskWithinLimits ? '' : 'warning' }}">@include('pulse.partials.icon', ['name' => $riskWithinLimits ? 'check' : 'alert']) {{ $riskWithinLimits ? 'All positions remain within configured limits.' : 'Review exposure or position protection before adding risk.' }}</div>
        </article>
    </section>

    <article class="pp-card pp-positions">
        <div class="pp-section-head"><h2>Open Positions</h2></div>
        <div class="pp-results-scroll">
            <table class="pp-table">
                <thead><tr><th>Pair</th><th>Direction</th><th>Size</th><th>Entry Price</th><th>Mark Price</th><th>Unrealized P&amp;L</th><th>Stop Status</th></tr></thead>
                <tbody>
                <?php foreach ($page['open_positions'] as $position):
                    $asset = str_replace('/USDT', '', $position['pair']);
                    $protectionNeedsReview = in_array($position['protection_status'], ['failed', 'review_required'], true);
                    $protectionLabel = $protectionNeedsReview ? 'Review' : ($position['protection_status'] === 'confirmed' ? 'Active' : ucfirst(str_replace('_', ' ', (string) ($position['protection_status'] ?: 'Pending'))));
                ?>
                    <tr><td>{{ $position['pair'] }}</td><td><span class="pp-pill {{ strtolower($position['side']) }}">{{ $position['side'] }}</span></td><td>{{ $number($position['quantity'], $position['quantity'] < 1 ? 3 : 2) }} {{ $asset }}</td><td>{{ $number($position['entry_price']) }}</td><td>{{ $number($position['current_price']) }}</td><td class="{{ $position['unrealized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($position['unrealized_pnl'], true) }}</td><td class="{{ $protectionNeedsReview ? 'pp-warning' : ($position['protection_status'] === 'confirmed' ? 'pp-positive' : 'pp-muted') }}">{{ $protectionLabel }}</td></tr>
                <?php endforeach; ?>
                <?php if (count($page['open_positions']) === 0): ?><tr><td colspan="7" class="pp-empty">No open positions. New positions will appear here after an exchange fill is confirmed.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pp-position-total"><span>Total Unrealized P&amp;L</span><strong class="{{ $page['unrealized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['unrealized_pnl'], true) }}</strong><?php if ($can('orders')): ?><a class="pp-link" href="{{ route('pulse.positions') }}">Manage positions @include('pulse.partials.icon', ['name' => 'chevron-right'])</a><?php else: ?><span class="pp-link disabled">Position management is not included</span><?php endif; ?></div>
    </article>
</div>
@endsection
