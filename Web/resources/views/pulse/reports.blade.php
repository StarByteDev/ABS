@extends('pulse.layout')
@section('title','Pulse Reports & P&L')
@section('heading','Reports & P&L')
@section('content')
<div class="pulse-page-hero">
    <div><span>SYNCHRONIZED RECORDS</span><h1>Reports &amp; P&amp;L</h1><p>Review activity created during the selected period and financial results from Pulse trades closed during that period.</p></div>
    <form method="GET" class="report-period"><div class="pulse-field"><label for="report-from">From</label><input id="report-from" type="date" name="from" value="{{ $from->format('Y-m-d') }}"></div><div class="pulse-field"><label for="report-to">To</label><input id="report-to" type="date" name="to" value="{{ $to->format('Y-m-d') }}"></div><button class="pulse-button secondary">Apply Period</button></form>
</div>

@if(!($marketHealth['schema_ready'] ?? true))
<div class="pulse-warning"><b>Signal Intelligence setup required</b><span>{{ $marketHealth['recovery'] ?? 'Run php artisan abs:repair --seed, then refresh central market data.' }}</span></div>
@endif

<div class="pulse-metric-row four report-metrics">
    <article><span class="metric-icon">◷</span><div><small>TRADE RECORDS CREATED</small><strong>{{ $summary['trades'] }}</strong></div></article>
    <article><span class="metric-icon">✓</span><div><small>TRADES CLOSED</small><strong>{{ $summary['closed'] }}</strong></div></article>
    <article><span class="metric-icon">⌖</span><div><small>WIN RATE</small><strong>{{ $summary['win_rate'] === null ? '—' : number_format($summary['win_rate'],2).'%' }}</strong><em>{{ $summary['wins'] }} W · {{ $summary['losses'] }} L · {{ $summary['breakeven'] }} flat</em></div></article>
    <article><span class="metric-icon">◇</span><div><small>REALIZED P&amp;L</small><strong class="{{ $summary['realized_pnl']>=0?'positive':'negative' }}">{{ number_format($summary['realized_pnl'],4) }}</strong><em>Closed synchronized records</em></div></article>
    <article><span class="metric-icon">⌕</span><div><small>SCANNER RUNS</small><strong>{{ $summary['scanner_runs'] }}</strong></div></article>
    <article><span class="metric-icon">⌁</span><div><small>SIGNALS GENERATED</small><strong>{{ $summary['signals'] }}</strong></div></article>
    <article><span class="metric-icon">◈</span><div><small>USDT COMMISSIONS</small><strong>{{ number_format($summary['usdt_fees'],4) }}</strong><em>Only records labelled USDT</em></div></article>
    <article><span class="metric-icon">▥</span><div><small>NET AFTER USDT FEES</small><strong class="{{ $summary['net_after_usdt_fees']>=0?'positive':'negative' }}">{{ number_format($summary['net_after_usdt_fees'],4) }}</strong><em>Other fee assets excluded</em></div></article>
</div>

<div class="pulse-card-heading" style="margin-top:18px"><div><h2>Signal Intelligence Validation</h2><p>Outcome reporting uses frozen signal levels and future 1-minute candles. TP/SL validation starts only after entry; same-minute TP + SL is recorded as ambiguous.</p></div><span class="pulse-badge">CENTRAL DATA</span></div>
<div class="pulse-metric-row four report-metrics">
    <article><span class="metric-icon">◎</span><div><small>SIGNALS VALIDATED</small><strong>{{ number_format($signalSummary['signals']) }}</strong><em>{{ $signalSummary['entries'] }} reached entry</em></div></article>
    <article><span class="metric-icon">↳</span><div><small>ENTRY RATE</small><strong>{{ $signalSummary['entry_rate'] === null ? '—' : number_format($signalSummary['entry_rate'],2).'%' }}</strong><em>{{ $signalSummary['expired_no_entry'] }} expired before entry</em></div></article>
    <article><span class="metric-icon">✓</span><div><small>DECISIVE WIN RATE</small><strong>{{ $signalSummary['decisive_win_rate'] === null ? '—' : number_format($signalSummary['decisive_win_rate'],2).'%' }}</strong><em>{{ $signalSummary['wins'] }} TP · {{ $signalSummary['losses'] }} SL</em></div></article>
    <article><span class="metric-icon">≈</span><div><small>AMBIGUOUS</small><strong>{{ number_format($signalSummary['ambiguous']) }}</strong><em>Never counted as a win</em></div></article>
    <article><span class="metric-icon">↗</span><div><small>AVERAGE MFE</small><strong>{{ $signalSummary['avg_mfe_r'] === null ? '—' : number_format((float)$signalSummary['avg_mfe_r'],2).'R' }}</strong><em>Maximum favorable excursion</em></div></article>
    <article><span class="metric-icon">↘</span><div><small>AVERAGE MAE</small><strong>{{ $signalSummary['avg_mae_r'] === null ? '—' : number_format((float)$signalSummary['avg_mae_r'],2).'R' }}</strong><em>Maximum adverse excursion</em></div></article>
    <article><span class="metric-icon">◉</span><div><small>CENTRAL PRICE FEED</small><strong>{{ strtoupper((string)($marketHealth['last_run_status'] ?? 'WAITING')) }}</strong><em>{{ !empty($marketHealth['latest_price_observed_at']) ? 'Latest '.$marketHealth['latest_price_observed_at'] : 'Awaiting first scheduled sync' }}</em></div></article>
    <article><span class="metric-icon">◷</span><div><small>VALIDATION DETAIL</small><strong>{{ config('pulse.validation.detailed_retention_days',7) }} DAYS</strong><em>Daily aggregates retained permanently</em></div></article>
</div>

<div class="pulse-grid pulse-grid-two">
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><div><h2>Closed Results by Market</h2><p>Grouped by the symbol on trades whose closed timestamp falls in the selected period.</p></div></div>
        <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Pair</th><th>Closed Trades</th><th>Realized P&amp;L</th></tr></thead><tbody><?php $__absForelseEmpty1 = true; foreach ($bySymbol as $row): $__absForelseEmpty1 = false; ?><tr><td><b>{{ str_replace('USDT','/USDT',$row->symbol) }}</b></td><td>{{ $row->trades }}</td><td class="{{ $row->realized_pnl>=0?'positive':'negative' }}">{{ number_format((float)$row->realized_pnl,4) }}</td></tr><?php endforeach; if ($__absForelseEmpty1): ?><tr><td colspan="3">No synchronized trade closed in the selected period.</td></tr><?php endif; ?></tbody></table></div>
    </article>
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><div><h2>Commission Totals by Asset</h2><p>Commission assets are kept separate because adding different assets would be misleading without an exchange-rate conversion.</p></div></div>
        <div class="fee-asset-list"><?php $__absForelseEmpty2 = true; foreach ($feesByAsset as $row): $__absForelseEmpty2 = false; ?><div><span><b>{{ $row->asset }}</b><small>Stored commission total</small></span><strong>{{ number_format((float)$row->fees,8) }}</strong></div><?php endforeach; if ($__absForelseEmpty2): ?><div class="pulse-card-empty">No stored commission record for trades closed in this period.</div><?php endif; ?></div>
        <div class="pulse-warning"><b>Accounting scope</b><span>Funding payments, rebates, transfers and exchange adjustments are not calculated here unless stored on a synchronized Pulse trade. Use the Binance statement as the final accounting record.</span></div>
    </article>
</div>

<div class="pulse-grid pulse-grid-two">
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><div><h2>Strategy Signal Performance</h2><p>Permanent aggregates remain separated by strategy version, timeframe and direction.</p></div></div>
        <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Strategy</th><th>Version</th><th>TF</th><th>Side</th><th>Samples</th><th>Win Rate</th><th>Ambiguous</th><th>MFE / MAE</th></tr></thead><tbody>
        @forelse($byStrategy as $row)<tr><td><b>{{ ucwords(str_replace('-',' ',$row['strategy_slug'])) }}</b></td><td>{{ $row['strategy_version'] }}</td><td>{{ strtoupper($row['timeframe']) }}</td><td>{{ $row['direction'] }}</td><td>{{ $row['samples'] }}</td><td>{{ $row['win_rate'] === null ? '—' : number_format((float)$row['win_rate'],2).'%' }}</td><td>{{ $row['ambiguous'] }}</td><td>{{ $row['avg_mfe_r'] === null ? '—' : number_format((float)$row['avg_mfe_r'],2).'R' }} / {{ $row['avg_mae_r'] === null ? '—' : number_format((float)$row['avg_mae_r'],2).'R' }}</td></tr>
        @empty<tr><td colspan="8">Strategy validation aggregates will appear after signals resolve.</td></tr>@endforelse
        </tbody></table></div>
    </article>
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><div><h2>Strategy Learning State</h2><p>Reliability uses sample-size protection, recency weighting and hierarchical fallback when evidence is sparse.</p></div></div>
        <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Strategy</th><th>TF</th><th>Side</th><th>Evidence</th><th>Samples</th><th>Reliability</th></tr></thead><tbody>
        @forelse($learningStates as $state)<tr><td><b>{{ ucwords(str_replace('-',' ',$state->strategy_slug)) }}</b><br><small>{{ $state->strategy_version }}</small></td><td>{{ strtoupper($state->timeframe) }}</td><td>{{ $state->direction }}</td><td>{{ strtoupper($state->evidence_level) }}</td><td>{{ $state->sample_size }}</td><td>{{ number_format((float)$state->reliability_score,2) }}%</td></tr>
        @empty<tr><td colspan="6">Learning state will activate as validated evidence accumulates.</td></tr>@endforelse
        </tbody></table></div>
    </article>
</div>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Recent Signal Validation</h2><p>Detailed validation is intentionally short-retention; compact daily reporting and learning state remain permanent.</p></div></div>
    <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Generated</th><th>Pair</th><th>TF</th><th>Side</th><th>State</th><th>Outcome</th><th>Entry Hit</th><th>MFE</th><th>MAE</th><th>Duration</th></tr></thead><tbody>
    @forelse($recentValidations as $validation)<tr><td>{{ $validation->generated_at?->format('d M H:i') }}</td><td><b>{{ $validation->symbol }}</b></td><td>{{ strtoupper($validation->timeframe) }}</td><td>{{ $validation->direction }}</td><td>{{ strtoupper(str_replace('_',' ',$validation->state)) }}</td><td>{{ $validation->outcome ? strtoupper(str_replace('_',' ',$validation->outcome)) : 'PENDING' }}</td><td>{{ $validation->entry_hit_at?->format('d M H:i') ?? 'Waiting' }}</td><td>{{ $validation->mfe_r === null ? '—' : number_format((float)$validation->mfe_r,2).'R' }}</td><td>{{ $validation->mae_r === null ? '—' : number_format((float)$validation->mae_r,2).'R' }}</td><td>{{ $validation->duration_seconds === null ? '—' : gmdate('H:i:s',(int)$validation->duration_seconds) }}</td></tr>
    @empty<tr><td colspan="10">No detailed signal validation record in the selected period.</td></tr>@endforelse
    </tbody></table></div>
</article>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Recent Pulse Activity</h2><p>Trade records created in the selected period. A record can close in a later reporting period.</p></div><a href="{{ route('pulse.trades.index') }}">Full Trade History</a></div>
    <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Created</th><th>Closed</th><th>Pair</th><th>Side</th><th>Status</th><th>Environment</th><th>Realized P&amp;L</th><th>Commission</th><th>Asset</th></tr></thead><tbody><?php $__absForelseEmpty3 = true; foreach ($recent as $trade): $__absForelseEmpty3 = false; ?><tr><td>{{ $trade->created_at?->format('d M Y H:i') }}</td><td>{{ $trade->closed_at?->format('d M Y H:i') ?? '—' }}</td><td><b>{{ $trade->symbol }}</b></td><td>{{ $trade->side }}</td><td><span class="pulse-badge status-{{ $trade->status }}">{{ strtoupper(str_replace('_',' ',$trade->status)) }}</span></td><td class="environment-{{ $trade->environment }}">{{ $trade->environment === 'live' ? 'LIVE' : 'PRACTICE' }}</td><td class="{{ (float)$trade->realized_pnl>=0?'positive':'negative' }}">{{ number_format((float)$trade->realized_pnl,4) }}</td><td>{{ number_format((float)$trade->fees,8) }}</td><td>{{ $trade->commission_asset ?: 'Unknown' }}</td></tr><?php endforeach; if ($__absForelseEmpty3): ?><tr><td colspan="9">No Pulse trade record was created in the selected period.</td></tr><?php endif; ?></tbody></table></div>
</article>
<p class="pulse-accuracy-note">Reports use synchronized local Pulse records and the centralized Pulse market-data/validation architecture. Binance account statements and transaction history remain authoritative. Unrealized P&amp;L is intentionally excluded from closed-period realized results.</p>
@endsection
