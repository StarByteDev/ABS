@extends('admin.layout')
@section('title','Pulse Strategy Intelligence')
@section('heading','Pulse Strategy Intelligence')
@section('description','Selected-period and all-time evidence for every strategy, including outcome mix and its current confidence contribution.')
@section('content')
@php
    $queryWithoutRange = request()->except(['from','to']);
    $selectedLabel = $from->format('d M Y').' — '.$to->format('d M Y');
@endphp

<section class="enterprise-command-bar compact strategy-command-bar">
    <div><span class="strategy-eyebrow">STRATEGY EVIDENCE · CONFIDENCE GOVERNANCE</span><h2>See which strategies are working and how evidence affects confidence</h2><p>Pulse resolves every eligible signal as TP, SL, ambiguous or expired, then uses evidence-protected reliability for 25% of final confidence. The technical model remains 75%.</p></div>
    <div class="enterprise-command-actions"><a class="button button-ghost" href="{{ route('admin.market-data') }}">Feed & Cron Health</a><a class="button button-ghost" href="{{ route('admin.pulse.strategies') }}">Strategy Controls</a></div>
</section>

<section class="enterprise-filter-surface report-toolbar">
    <div class="filter-surface-head"><div><h2>Report period and strategy filters</h2><p>All KPIs, charts and selected-period columns below use this exact range.</p></div><nav class="admin-range-tabs" aria-label="Quick date ranges"><a href="{{ route('admin.pulse.intelligence',array_merge($queryWithoutRange,['from'=>now()->subDay()->toDateString(),'to'=>today()->toDateString()])) }}">24H</a><a href="{{ route('admin.pulse.intelligence',array_merge($queryWithoutRange,['from'=>now()->subDays(6)->toDateString(),'to'=>today()->toDateString()])) }}">7D</a><a href="{{ route('admin.pulse.intelligence',array_merge($queryWithoutRange,['from'=>now()->subDays(29)->toDateString(),'to'=>today()->toDateString()])) }}">30D</a><a href="{{ route('admin.pulse.intelligence',array_merge($queryWithoutRange,['from'=>now()->subDays(89)->toDateString(),'to'=>today()->toDateString()])) }}">90D</a></nav></div>
    <form method="GET" class="strategy-filter-grid">
        <label>From date<input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></label>
        <label>To date<input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></label>
        <label>Strategy<select name="strategy"><option value="">All strategies</option>@foreach($strategyCatalog as $item)<option value="{{ $item->slug }}" @selected($filters['strategy']===$item->slug)>{{ $item->name }}</option>@endforeach</select></label>
        <label>Timeframe<select name="timeframe"><option value="">15 minute + 4 hour</option><option value="15m" @selected($filters['timeframe']==='15m')>15 minute</option><option value="4h" @selected($filters['timeframe']==='4h')>4 hour</option></select></label>
        <label>Direction<select name="direction"><option value="">Long + short</option><option value="LONG" @selected($filters['direction']==='LONG')>Long</option><option value="SHORT" @selected($filters['direction']==='SHORT')>Short</option></select></label>
        <div class="filter-actions"><button class="button button-primary">Apply Report</button><a class="button button-ghost" href="{{ route('admin.pulse.intelligence') }}">Reset</a></div>
    </form>
</section>

<section class="admin-purpose-card">
    <div><span class="purpose-label">HOW TO READ THIS PAGE</span><h2>Selected period explains recent behavior; all time protects the live model</h2><p>The date range shows recent results. “All time” provides the longer evidence baseline. “Current live influence” is the actual learned contribution used by Pulse; “period vs all time” is a comparison for management review and does not retroactively rewrite signals.</p></div>
    <div class="admin-purpose-list"><div><i>TP</i><span>Target reached after entry</span></div><div><i>SL</i><span>Risk limit reached after entry</span></div><div><i>?</i><span>Ambiguous: TP and SL order cannot be proven</span></div></div>
</section>

@if(!($marketHealth['schema_ready'] ?? true))
<section class="strategy-health-banner danger"><div><b>Signal Intelligence setup required</b><span>{{ $marketHealth['recovery'] ?? 'Run php artisan abs:repair --seed, then refresh central market data.' }}</span></div><strong>ACTION REQUIRED</strong></section>
@else
<section class="strategy-health-banner {{ ($marketHealth['last_run_status'] ?? '') === 'completed' ? 'good' : 'warn' }}"><div><b>Central Binance feed: {{ strtoupper((string)($marketHealth['last_run_status'] ?? 'WAITING')) }}</b><span>{{ number_format((int)($marketHealth['latest_price_symbols'] ?? 0)) }} markets · {{ (int)($marketHealth['target_price_refresh_seconds'] ?? 60) }}-second target · resolution and learning run after current prices are stored</span></div><strong>{{ $marketHealth['latest_price_observed_at'] ?? 'Awaiting first sync' }}</strong></section>
@endif

<section class="admin-report-kpis signal-kpi-grid" aria-label="Selected period strategy results">
    <article><span class="report-kpi-icon blue">Σ</span><div><small>Signals tracked</small><strong>{{ number_format($summary['signals']) }}</strong><em>{{ $selectedLabel }}</em></div></article>
    <article><span class="report-kpi-icon cyan">↗</span><div><small>Entries observed</small><strong>{{ number_format($summary['entries']) }}</strong><em>{{ $summary['entry_rate']===null?'—':number_format($summary['entry_rate'],1).'%' }} entry rate</em></div></article>
    <article><span class="report-kpi-icon green">TP</span><div><small>Target reached</small><strong>{{ number_format($summary['wins']) }}</strong><em>Favorable resolved outcomes</em></div></article>
    <article><span class="report-kpi-icon red">SL</span><div><small>Risk limit reached</small><strong>{{ number_format($summary['losses']) }}</strong><em>Unfavorable resolved outcomes</em></div></article>
    <article class="featured"><span class="report-kpi-icon gold">%</span><div><small>Decisive win rate</small><strong>{{ $summary['decisive_win_rate']===null?'—':number_format($summary['decisive_win_rate'],1).'%' }}</strong><em>TP ÷ (TP + SL)</em></div></article>
    <article class="{{ $summary['pending_validations']>0?'attention':'' }}"><span class="report-kpi-icon amber">◷</span><div><small>Pending validation</small><strong>{{ number_format($summary['pending_validations']) }}</strong><em>Checked by the one-minute job</em></div></article>
</section>

<script type="application/json" id="intelligence-trend">{!! json_encode([
    'ariaLabel'=>'Selected-period daily signal entries, take-profit, stop-loss and ambiguous results',
    'labels'=>$intelligenceTrend->pluck('label')->all(),
    'series'=>[
        ['name'=>'Signals','color'=>'#2788d1','fillColor'=>'rgba(39,136,209,.10)','values'=>$intelligenceTrend->pluck('signals')->all()],
        ['name'=>'Entries','color'=>'#25b8cb','fill'=>false,'values'=>$intelligenceTrend->pluck('entries')->all()],
        ['name'=>'TP','color'=>'#179b6b','fill'=>false,'values'=>$intelligenceTrend->pluck('wins')->all()],
        ['name'=>'SL','color'=>'#d54d5b','fill'=>false,'values'=>$intelligenceTrend->pluck('losses')->all()],
    ],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="intelligence-outcomes">{!! json_encode([
    'ariaLabel'=>'Selected-period strategy outcome distribution','centerLabel'=>'RESULTS',
    'labels'=>['Take profit','Stop loss','Ambiguous','Expired before entry','Expired after entry'],
    'values'=>[$summary['wins'],$summary['losses'],$summary['ambiguous'],$summary['expired_no_entry'],$summary['expired_after_entry']],
    'colors'=>['#179b6b','#d54d5b','#d68f24','#8292a8','#7457d9'],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>

<section class="admin-chart-grid">
    <article class="admin-chart-card"><div class="admin-chart-head"><div><h2>Strategy activity and outcomes over time</h2><p>Daily evidence for the selected filters. Exact values appear on hover or keyboard focus.</p></div><span class="report-period-chip">{{ strtoupper($selectedLabel) }}</span></div><div class="admin-chart" data-admin-chart data-chart-source="#intelligence-trend" data-chart-type="line"></div></article>
    <article class="admin-chart-card"><div class="admin-chart-head"><div><h2>Outcome composition</h2><p>Ambiguous and expired signals stay outside decisive win rate.</p></div></div><div class="admin-chart" data-admin-chart data-chart-source="#intelligence-outcomes" data-chart-type="donut"></div></article>
</section>

<section class="enterprise-surface strategy-score-explainer">
    <div><span class="strategy-eyebrow">LIVE CONFIDENCE MODEL</span><h2>75% technical score + 25% learned reliability</h2><p>Evidence can move final confidence by up to ±12.5 percentage points around a neutral 50% reliability baseline. Sparse evidence falls back to broader qualified context.</p></div>
    <div class="strategy-formula"><b>Final confidence</b><strong>0.75 × Technical</strong><i>+</i><strong>0.25 × Reliability</strong></div>
</section>

<section class="enterprise-surface no-pad strategy-results-surface">
    <div class="enterprise-section-head padded"><div><h2>Strategy performance: selected period vs all time</h2><p>All configured strategies remain visible, including strategies still collecting enough evidence.</p></div><span class="report-period-chip">{{ $summary['tracked_strategies'] }}/{{ $summary['catalog_strategies'] }} WITH PERIOD DATA</span></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table strategy-results-table"><thead><tr><th>Strategy</th><th>Selected signals / entries</th><th>Selected TP / SL / ambiguous</th><th>Selected win rate</th><th>All-time evidence</th><th>All-time TP / SL / ambiguous</th><th>All-time win rate</th><th>Current live influence</th><th>Period vs all time</th></tr></thead><tbody>
    @forelse($strategyRollups as $row)
        @php($delta = $row['range_confidence_delta'])
        <tr>
            <td><b>{{ $row['name'] }}</b><small>{{ $row['slug'] }} · v{{ $row['version'] }} · {{ $row['enabled']?'enabled':'disabled' }}</small></td>
            <td><div class="metric-compare"><b>{{ number_format($row['samples']) }} / {{ number_format($row['entries']) }}</b><small>{{ $row['entry_rate']===null?'No period data':number_format($row['entry_rate'],1).'% entry rate' }}</small></div></td>
            <td><b>{{ number_format($row['wins']) }} / {{ number_format($row['losses']) }} / {{ number_format($row['ambiguous']) }}</b></td>
            <td><b>{{ $row['win_rate']===null?'—':number_format($row['win_rate'],1).'%' }}</b><small>Ambiguous excluded</small></td>
            <td><b>{{ number_format($row['all_time_samples']) }} signals</b><small>{{ number_format($row['all_time_entries']) }} entries</small></td>
            <td><b>{{ number_format($row['all_time_wins']) }} / {{ number_format($row['all_time_losses']) }} / {{ number_format($row['all_time_ambiguous']) }}</b></td>
            <td><b>{{ $row['all_time_win_rate']===null?'—':number_format($row['all_time_win_rate'],1).'%' }}</b></td>
            <td><b>{{ $row['reliability']===null?'50.0% neutral':number_format($row['reliability'],1).'% reliability' }}</b><small>{{ strtoupper($row['evidence']) }} evidence</small><span class="strategy-impact {{ $row['confidence_impact']===null?'neutral':($row['confidence_impact']>=0?'positive':'negative') }}">{{ $row['confidence_impact']===null?'±0.00 pp':($row['confidence_impact']>=0?'+':'').number_format($row['confidence_impact'],2).' pp' }}</span></td>
            <td><span class="metric-delta {{ $delta===null?'':($delta>=0?'positive':'negative') }}">{{ $delta===null?'Not comparable':($delta>=0?'+':'').number_format($delta,2).' pp' }}</span><small>25% weighted performance difference</small></td>
        </tr>
    @empty<tr><td colspan="9"><div class="admin-empty-report"><h3>No strategies match this report</h3><p>Reset the filters to review the complete catalog.</p></div></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="enterprise-section-grid equal">
    <article class="enterprise-surface"><div class="enterprise-section-head"><div><h2>Timeframe and direction detail</h2><p>Selected-period evidence split by strategy version, timeframe and side.</p></div></div><div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Strategy</th><th>Timeframe</th><th>Side</th><th>Signals</th><th>Entries</th><th>TP / SL / Ambig.</th><th>Win rate</th></tr></thead><tbody>@forelse($strategies as $row)<tr><td><b>{{ ucwords(str_replace('-',' ',$row['slug'])) }}</b><small>v{{ $row['version'] }}</small></td><td>{{ strtoupper($row['timeframe']) }}</td><td>{{ ucfirst(strtolower($row['direction'])) }}</td><td>{{ $row['samples'] }}</td><td>{{ $row['entries'] }}</td><td>{{ $row['wins'] }} / {{ $row['losses'] }} / {{ $row['ambiguous'] }}</td><td>{{ $row['win_rate']===null?'—':number_format($row['win_rate'],1).'%' }}</td></tr>@empty<tr><td colspan="7">Segmented evidence appears after signal validations resolve.</td></tr>@endforelse</tbody></table></div></article>
    <article class="enterprise-surface"><div class="enterprise-section-head"><div><h2>Current learned reliability state</h2><p>This is the evidence-protected state currently available to the live confidence engine.</p></div></div><div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Strategy</th><th>TF</th><th>Side</th><th>Evidence</th><th>Samples</th><th>Reliability</th></tr></thead><tbody>@forelse($learningStates as $state)<tr><td><b>{{ ucwords(str_replace('-',' ',$state->strategy_slug)) }}</b><small>{{ $state->strategy_version }}</small></td><td>{{ strtoupper($state->timeframe) }}</td><td>{{ ucfirst(strtolower($state->direction)) }}</td><td><span class="admin-status {{ $state->evidence_level==='established'?'good':($state->evidence_level==='developing'?'warn':'muted') }}">{{ strtoupper($state->evidence_level) }}</span></td><td>{{ $state->sample_size }}</td><td><b>{{ number_format((float)$state->reliability_score,1) }}%</b></td></tr>@empty<tr><td colspan="6">Learning is waiting for validated samples.</td></tr>@endforelse</tbody></table></div></article>
</section>

<section class="enterprise-surface no-pad">
    <div class="enterprise-section-head padded"><div><h2>Recent validation evidence in this period</h2><p>Entry must occur before TP/SL. If TP and SL touch in the same later one-minute candle, Pulse records “ambiguous” and excludes it from decisive win rate.</p></div><span class="report-period-chip">LATEST 50</span></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Generated</th><th>User</th><th>Market</th><th>TF / Side</th><th>Lifecycle</th><th>Resolved outcome</th><th>Entry observed</th><th>Favorable / adverse excursion</th></tr></thead><tbody>@forelse($recentValidations as $v)<tr><td><b>{{ $v->generated_at?->format('d M Y · H:i') }}</b></td><td>#{{ $v->user_id ?: 'System' }}</td><td><b>{{ $v->symbol }}</b></td><td>{{ strtoupper($v->timeframe) }} · {{ ucfirst(strtolower($v->direction)) }}</td><td>{{ ucwords(str_replace('_',' ',$v->state)) }}</td><td><span class="admin-status {{ $v->outcome==='tp'?'good':($v->outcome==='sl'?'danger':($v->outcome==='ambiguous'?'warn':'muted')) }}">{{ $v->outcome?strtoupper(str_replace('_',' ',$v->outcome)):'PENDING' }}</span></td><td>{{ $v->entry_hit_at?->format('d M · H:i') ?? 'Not reached' }}</td><td>{{ $v->mfe_r===null?'—':number_format((float)$v->mfe_r,2).'R' }} / {{ $v->mae_r===null?'—':number_format((float)$v->mae_r,2).'R' }}</td></tr>@empty<tr><td colspan="8">No validation detail in the selected period.</td></tr>@endforelse</tbody></table></div>
</section>

<section class="enterprise-surface no-pad">
    <div class="enterprise-section-head padded"><div><h2>Central market-data runs</h2><p>Operational proof that the shared one-minute Binance price process is running and supplying validation data.</p></div><a href="{{ route('admin.market-data') }}">Full cron report →</a></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Started</th><th>Status</th><th>Prices updated</th><th>Scanner buffers</th><th>Validation markets</th><th>Completed</th><th>Error</th></tr></thead><tbody>@forelse($marketRuns as $run)<tr><td>{{ $run->started_at?->format('d M H:i:s') }}</td><td><span class="admin-status {{ $run->status==='completed'?'good':($run->status==='running'?'warn':'danger') }}">{{ strtoupper($run->status) }}</span></td><td>{{ $run->prices_updated }}</td><td>{{ $run->candle_symbols_updated }}</td><td>{{ $run->validation_symbols_updated }}</td><td>{{ $run->completed_at?->format('H:i:s') ?? 'Running' }}</td><td>{{ $run->error_message ?: '—' }}</td></tr>@empty<tr><td colspan="7">No central market-data run yet. Run the scheduler or abs:pulse-market-data.</td></tr>@endforelse</tbody></table></div>
</section>
@endsection
