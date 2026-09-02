@extends('pulse.layout')
@section('title','Market Scanner')
@section('heading','Market Scanner')
@section('content')
<?php
    $filters = $page['filters'];
    $can = fn (string $capability): bool => (bool) data_get($page, "capabilities.{$capability}.enabled", false);
?>
<div class="pp-page pp-scanner">
    <header class="pp-page-head">
        <div class="pp-title"><h1>Market Scanner</h1><p>Scan the markets you selected from your package and prioritize setups using liquidity, trend and strategy criteria.</p></div>
        <div class="pp-head-actions">
            <?php if ($can('settings')): ?><a class="pp-button muted" href="{{ route('pulse.settings.edit') }}#selected-markets">Manage Pairs</a><?php else: ?><span class="pp-button disabled" aria-disabled="true">Pair Settings Not Included</span><?php endif; ?>
            <form method="POST" action="{{ route('pulse.scanner.run') }}" data-run-market-scan data-refresh-url="{{ route('pulse.scanner.refresh') }}">@csrf<input type="hidden" name="timeframe" data-scan-run-timeframe value="{{ in_array($filters['timeframe'], ['15m','4h'], true) ? $filters['timeframe'] : 'all' }}"><button class="pp-button primary" type="submit" data-scan-run-button>@include('pulse.partials.icon', ['name' => 'pulse']) <span data-scan-run-label>Run Market Scan</span></button></form>
            <div class="pp-environment"><b>BINANCE {{ strtoupper($page['settings']->environment) }}</b><span class="{{ $page['connection_ready'] ? 'connected' : '' }}">{{ $page['connection_ready'] ? 'Connected' : 'Connection check required' }}</span><small>Last scan: <span data-scanner-last-scan>{{ $page['latest_run']?->completed_at?->diffForHumans() ?? 'not yet' }}</span></small></div>
        </div>
    </header>
    <div class="pp-async-status" data-scan-action-message hidden></div>

    @include('pulse.partials.scanner-metrics')

    <section class="pp-card pp-scanner-filters">
        <h2>Scanner Filters</h2>
        <form method="GET" action="{{ route('pulse.scanner') }}" class="pp-filter-row" data-scan-filters>
            <label class="pp-field">Quote Asset<select name="quote"><option value="all" {{ strtolower($filters['quote']) === 'all' ? 'selected' : '' }}>All Quotes</option><?php foreach ($page['quote_assets'] as $quote): ?><option value="{{ $quote }}" {{ strtoupper($filters['quote']) === strtoupper($quote) ? 'selected' : '' }}>{{ $quote }}</option><?php endforeach; ?></select></label>
            <label class="pp-field">Direction<select name="direction"><option value="all" {{ $filters['direction'] === 'all' ? 'selected' : '' }}>All</option><option value="long" {{ $filters['direction'] === 'long' ? 'selected' : '' }}>Long</option><option value="short" {{ $filters['direction'] === 'short' ? 'selected' : '' }}>Short</option></select></label>
            <label class="pp-field">Strategy<select name="strategy"><option value="all" {{ $filters['strategy'] === 'all' ? 'selected' : '' }}>All Included Strategies</option><?php foreach ($page['strategy_options'] as $strategy): ?><option value="{{ $strategy->slug }}" {{ $filters['strategy'] === $strategy->slug ? 'selected' : '' }}>{{ $strategy->name }}</option><?php endforeach; ?></select></label>
            <label class="pp-field">Minimum Score<input type="number" name="min_score" min="0" max="100" step="1" value="{{ number_format((float) $filters['min_score'], 0, '.', '') }}" aria-label="Minimum scanner score"></label>
            <label class="pp-field">Timeframe<select name="timeframe"><option value="all" {{ $filters['timeframe'] === 'all' ? 'selected' : '' }}>15M · 4H</option><option value="15m" {{ $filters['timeframe'] === '15m' ? 'selected' : '' }}>15M</option><option value="4h" {{ $filters['timeframe'] === '4h' ? 'selected' : '' }}>4H</option></select></label>
            <label class="pp-field">Liquidity<select name="liquidity"><option value="high-medium" {{ $filters['liquidity'] === 'high-medium' ? 'selected' : '' }}>High &amp; Medium</option><option value="high" {{ $filters['liquidity'] === 'high' ? 'selected' : '' }}>High</option><option value="all" {{ $filters['liquidity'] === 'all' ? 'selected' : '' }}>All</option></select></label>
            <div class="pp-filter-actions"><a class="pp-button muted" href="{{ route('pulse.scanner') }}">Reset</a><button class="pp-button primary" type="submit">Apply Filters</button></div>
        </form>
        <div class="pp-filter-chips"><span class="pp-chip">{{ strtolower($filters['quote']) === 'all' ? 'All Quotes' : $filters['quote'] }} <b>×</b></span><span class="pp-chip">Score ≥ {{ number_format($filters['min_score'], 0) }} <b>×</b></span><span class="pp-chip">{{ $filters['timeframe'] === 'all' ? '15M + 4H' : strtoupper($filters['timeframe']) }} <b>×</b></span><span class="pp-chip">Tradable Only <b>×</b></span></div>
    </section>

    {{-- Scanner Results (rendered as an async-refresh fragment) --}}
    @include('pulse.partials.scanner-results')

    {{-- Market Conditions · Scan Quality · Saved Scanner View (async-refresh fragments) --}}
    @include('pulse.partials.scanner-bottom')
</div>
@endsection
