@extends('pulse.layout')
@section('title','Pulse Strategies')
@section('heading','Pulse Strategies')
@section('content')
<?php
    $money = fn ($value, $signed = false) => ($signed && (float) $value >= 0 ? '+' : ((float) $value < 0 ? '-' : '')).'$'.number_format(abs((float) $value), 2);
    $families = collect($page['families']);
    $catalog = collect($page['strategy_catalog'] ?? []);
    $periodDisplay = match ($page['period']) {'7d' => '7 days', '30d' => '30 days', '90d' => '90 days', default => 'all time'};
    $can = fn (string $capability): bool => (bool) data_get($page, "capabilities.{$capability}.enabled", false);
?>
<div class="pp-page pp-strategies pp-strategies-simple">
    <header class="pp-page-head pp-page-head-simple">
        <div class="pp-title">
            <h1>Pulse Strategies</h1>
            <p>See which strategy engines are active in your plan and how they are performing.</p>
        </div>
        <div class="pp-head-actions">
            <nav class="pp-period" aria-label="Strategy period"><?php foreach (['7d' => '7D', '30d' => '30D', '90d' => '90D', 'all' => 'ALL TIME'] as $key => $label): ?><a class="{{ $page['period'] === $key ? 'active' : '' }}" href="{{ route('pulse.strategies', ['period' => $key]) }}">{{ $label }}</a><?php endforeach; ?></nav>
            <a class="pp-button primary" href="{{ route('pulse.scanner') }}">@include('pulse.partials.icon', ['name' => 'search']) Open Scanner</a>
            <div class="pp-environment"><b>BINANCE {{ strtoupper($page['settings']->environment) }}</b><span class="{{ $page['connection_ready'] ? 'connected' : '' }}">{{ $page['connection_ready'] ? 'Connected' : 'Connection check required' }}</span><small>Updated {{ $page['last_evaluation']?->diffForHumans() ?? 'after next evaluation' }}</small></div>
        </div>
    </header>

    <section class="pp-metrics four pp-metrics-simple">
        <article class="pp-metric"><span class="pp-metric-icon green">@include('pulse.partials.icon', ['name' => 'target'])</span><div class="pp-metric-copy"><small>Active Strategies</small><strong>{{ $page['active_strategies'] }} / {{ $page['total_strategies'] ?? 15 }}</strong><em>Included in your plan</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon">@include('pulse.partials.icon', ['name' => 'pulse'])</span><div class="pp-metric-copy"><small>Signals Generated</small><strong>{{ $page['signals_generated'] }}</strong><em>{{ $periodDisplay }}</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon cyan">@include('pulse.partials.icon', ['name' => 'check'])</span><div class="pp-metric-copy"><small>Signals Actioned</small><strong>{{ $page['signals_actioned'] }}</strong><em>{{ number_format((float)$page['engagement'], 1) }}% engagement</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon green">@include('pulse.partials.icon', ['name' => 'coin'])</span><div class="pp-metric-copy"><small>Realized P&amp;L</small><strong class="{{ $page['realized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['realized_pnl'], true) }}</strong><em>{{ $page['closed_trades'] }} closed trades</em></div></article>
    </section>

    <div class="pp-strategy-catalog-head">
        <div><h2 class="pp-strategy-heading">Strategy Catalog</h2><p>Only the strategies included in your plan participate in scanning and scoring.</p></div>
        <div class="pp-strategy-catalog-summary"><b>{{ $page['active_strategies'] }} ACTIVE</b><span>{{ max(0, ($page['total_strategies'] ?? 15) - $page['active_strategies']) }} unavailable on this plan</span></div>
    </div>

    <section class="pp-strategy-cards pp-strategy-catalog pp-strategy-catalog-simplified">
        <?php foreach ($catalog as $index => $strategy): $included = (bool) $strategy['included']; $tone = $index % 3 === 1 ? 'blue' : ($index % 3 === 2 ? 'orange' : ''); ?>
            <article class="pp-card pp-strategy-card pp-strategy-engine pp-strategy-engine-simple {{ $included ? '' : 'locked' }}" id="strategy-{{ $strategy['slug'] }}">
                <div class="pp-strategy-simple-head">
                    <span class="pp-strategy-icon {{ $tone }}">@include('pulse.partials.icon', ['name' => $included ? 'target' : 'lock'])</span>
                    <div class="pp-strategy-simple-copy"><small>STRATEGY {{ str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) }}</small><h3>{{ $strategy['name'] }}</h3></div>
                    <span class="{{ $included ? 'pp-active-badge' : 'pp-locked-badge' }}">{{ $included ? 'ACTIVE' : 'LOCKED' }}</span>
                </div>
                <p class="pp-strategy-simple-description">{{ $strategy['description'] }}</p>
                <div class="pp-strategy-simple-stats">
                    <div><small>Timeframe</small><strong>{{ strtoupper($strategy['timeframe']) }}</strong></div>
                    <div><small>Signals</small><strong>{{ $strategy['signals'] }}</strong></div>
                    <div><small>Avg Score</small><strong>{{ $strategy['average_score'] > 0 ? number_format((float)$strategy['average_score'],0) : '—' }}</strong></div>
                </div>
                <div class="pp-strategy-simple-action">
                    <?php if ($included): ?><a class="pp-button muted" href="{{ route('pulse.scanner', ['strategy' => $strategy['slug']]) }}">Use in Scanner</a><?php else: ?><a class="pp-button muted" href="{{ route('pulse.plans') }}">View Plan Access</a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="pp-strategy-insights" id="strategy-performance">
        <article class="pp-card pp-strategy-performance-card">
            <div class="pp-section-head"><h2>{{ $page['period'] === 'all' ? 'All-Time' : strtoupper($page['period']) }} Strategy Performance</h2><span>Simple performance summary</span></div>
            <div class="pp-results-scroll">
                <table class="pp-table pp-strategy-performance-table">
                    <thead><tr><th>Strategy</th><th>Signals</th><th>Avg. Score</th><th>Open</th><th>Realized P&amp;L</th></tr></thead>
                    <tbody><?php foreach ($families as $family): ?><tr><td>{{ $family['name'] }}</td><td>{{ $family['signals'] }}</td><td>{{ number_format($family['average_score'], 0) }}</td><td>{{ $family['open_positions'] }}</td><td class="{{ $family['realized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($family['realized_pnl'], true) }}</td></tr><?php endforeach; ?></tbody>
                    <tfoot><tr><td>TOTAL</td><td>{{ $page['signals_generated'] }}</td><td>{{ number_format($page['average_setup_score'], 0) }}</td><td>{{ $families->sum('open_positions') }}</td><td class="{{ $page['realized_pnl'] >= 0 ? 'pp-positive' : 'pp-negative' }}">{{ $money($page['realized_pnl'], true) }}</td></tr></tfoot>
                </table>
            </div>
        </article>

        <article class="pp-card pp-strategy-essentials">
            <div class="pp-section-head"><h2>Strategy Controls &amp; Health</h2><span class="pp-positive">Operational</span></div>
            <div class="pp-strategy-essential-list">
                <div><span>Minimum Signal Score</span><strong>{{ number_format((float) $page['settings']->minimum_signal_score, 0) }}</strong></div>
                <div><span>Risk per Trade</span><strong>{{ number_format((float) $page['settings']->risk_per_trade_percent, 2) }}%</strong></div>
                <div><span>Maximum Open Positions</span><strong>{{ $page['maximum_open_positions'] }}</strong></div>
                <div><span>Evaluation Engine</span><strong class="pp-positive">Operational</strong></div>
                <div><span>Market Data</span><strong class="pp-positive">Current</strong></div>
                <div><span>Last Evaluation</span><strong>{{ $page['last_evaluation']?->diffForHumans() ?? 'not yet' }}</strong></div>
            </div>
            <?php if ($can('settings')): ?><a class="pp-button" href="{{ route('pulse.risk.index') }}">@include('pulse.partials.icon', ['name' => 'settings']) Manage Risk Controls</a><?php endif; ?>
        </article>
    </section>
</div>
@endsection
