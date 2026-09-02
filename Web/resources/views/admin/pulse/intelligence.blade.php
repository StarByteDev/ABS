@extends('admin.layout')
@section('title','Pulse Signal Intelligence')
@section('heading','Pulse Signal Intelligence')
@section('content')
<section class="enterprise-command-bar compact">
    <div><h2>Signal quality, validation and learning</h2><p>Platform-level signal reporting is deduplicated by frozen market setup. Detailed validation is short-retention; daily intelligence and strategy learning remain permanent.</p></div>
    <form method="GET" class="admin-actions">
        <label>From <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></label>
        <label>To <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></label>
        <button class="button button-primary">Apply Period</button>
    </form>
</section>

@if(!($marketHealth['schema_ready'] ?? true))
<section class="enterprise-surface"><div class="enterprise-section-head"><div><h2>Signal Intelligence setup required</h2><p>{{ $marketHealth['recovery'] ?? 'Run php artisan abs:repair --seed, then refresh central market data.' }}</p></div><span>ACTION REQUIRED</span></div></section>
@endif

<section class="enterprise-kpi-grid six">
    <article class="enterprise-kpi"><small>Validated Signals</small><strong>{{ number_format($summary['signals']) }}</strong></article>
    <article class="enterprise-kpi"><small>Entry Rate</small><strong>{{ $summary['entry_rate'] === null ? '—' : number_format($summary['entry_rate'],1).'%' }}</strong></article>
    <article class="enterprise-kpi"><small>Decisive Win Rate</small><strong>{{ $summary['decisive_win_rate'] === null ? '—' : number_format($summary['decisive_win_rate'],1).'%' }}</strong></article>
    <article class="enterprise-kpi {{ $summary['ambiguous']>0?'attention':'' }}"><small>Ambiguous Outcomes</small><strong>{{ number_format($summary['ambiguous']) }}</strong></article>
    <article class="enterprise-kpi"><small>Average MFE</small><strong>{{ $summary['avg_mfe_r'] === null ? '—' : number_format($summary['avg_mfe_r'],2).'R' }}</strong></article>
    <article class="enterprise-kpi"><small>Average MAE</small><strong>{{ $summary['avg_mae_r'] === null ? '—' : number_format($summary['avg_mae_r'],2).'R' }}</strong></article>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Central Market Data Health</h2><p>One scheduled market-data source feeds web, scanner, validation and mobile API.</p></div><span>{{ strtoupper((string)($marketHealth['last_run_status'] ?? 'WAITING')) }}</span></div>
    <div class="enterprise-kpi-grid six">
        <article class="enterprise-kpi"><small>Architecture</small><strong style="font-size:16px">{{ $marketHealth['architecture'] ?? '—' }}</strong></article>
        <article class="enterprise-kpi"><small>Price Markets</small><strong>{{ number_format((int)($marketHealth['latest_price_symbols'] ?? 0)) }}</strong></article>
        <article class="enterprise-kpi"><small>Target Refresh</small><strong>{{ (int)($marketHealth['target_price_refresh_seconds'] ?? 60) }}s</strong></article>
        <article class="enterprise-kpi"><small>Scanner TF</small><strong style="font-size:18px">{{ strtoupper(implode(' · ',(array)($marketHealth['scanner_timeframes'] ?? []))) }}</strong></article>
        <article class="enterprise-kpi"><small>Latest Price</small><strong style="font-size:14px">{{ $marketHealth['latest_price_observed_at'] ?? 'Awaiting sync' }}</strong></article>
        <article class="enterprise-kpi"><small>Validation Detail</small><strong>{{ config('pulse.validation.detailed_retention_days',7) }}d</strong></article>
    </div>
</section>

<div class="enterprise-section-grid equal">
<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Strategy Performance</h2><p>Strategy + version + timeframe + direction. Win rate excludes ambiguous outcomes.</p></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Strategy</th><th>Version</th><th>TF</th><th>Side</th><th>Samples</th><th>Entries</th><th>Win Rate</th><th>Ambiguous</th></tr></thead><tbody>
    @forelse($strategies as $row)<tr><td>{{ ucwords(str_replace('-',' ',$row['slug'])) }}</td><td>{{ $row['version'] }}</td><td>{{ strtoupper($row['timeframe']) }}</td><td>{{ $row['direction'] }}</td><td>{{ $row['samples'] }}</td><td>{{ $row['entries'] }}</td><td>{{ $row['win_rate'] === null ? '—' : number_format($row['win_rate'],1).'%' }}</td><td>{{ $row['ambiguous'] }}</td></tr>
    @empty<tr><td colspan="8">Strategy metrics will appear after signal validations resolve.</td></tr>@endforelse
    </tbody></table></div>
</section>
<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Learning Evidence</h2><p>Evidence-protected reliability; sparse context falls back to broader qualified evidence.</p></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Strategy</th><th>TF</th><th>Side</th><th>Evidence</th><th>Samples</th><th>Reliability</th></tr></thead><tbody>
    @forelse($learningStates as $state)<tr><td>{{ ucwords(str_replace('-',' ',$state->strategy_slug)) }}<br><small>{{ $state->strategy_version }}</small></td><td>{{ strtoupper($state->timeframe) }}</td><td>{{ $state->direction }}</td><td>{{ strtoupper($state->evidence_level) }}</td><td>{{ $state->sample_size }}</td><td>{{ number_format((float)$state->reliability_score,1) }}%</td></tr>
    @empty<tr><td colspan="6">Learning state is waiting for validated samples.</td></tr>@endforelse
    </tbody></table></div>
</section>
</div>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Recent Validation Detail</h2><p>Entry must occur before TP/SL validation. TP and SL touched in the same later 1-minute candle is recorded as ambiguous.</p></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Generated</th><th>User</th><th>Pair</th><th>TF</th><th>Side</th><th>State</th><th>Outcome</th><th>Entry</th><th>MFE</th><th>MAE</th></tr></thead><tbody>
    @forelse($recentValidations as $v)<tr><td>{{ $v->generated_at?->format('d M H:i') }}</td><td>#{{ $v->user_id ?: 'System' }}</td><td>{{ $v->symbol }}</td><td>{{ strtoupper($v->timeframe) }}</td><td>{{ $v->direction }}</td><td>{{ strtoupper(str_replace('_',' ',$v->state)) }}</td><td>{{ $v->outcome ? strtoupper(str_replace('_',' ',$v->outcome)) : 'PENDING' }}</td><td>{{ $v->entry_hit_at?->format('d M H:i') ?? 'Waiting' }}</td><td>{{ $v->mfe_r === null ? '—' : number_format((float)$v->mfe_r,2).'R' }}</td><td>{{ $v->mae_r === null ? '—' : number_format((float)$v->mae_r,2).'R' }}</td></tr>
    @empty<tr><td colspan="10">No recent validation detail.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Central Market Data Runs</h2><p>Use this table to verify scheduler health and identify upstream failures.</p></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Started</th><th>Status</th><th>Prices</th><th>Scanner Buffers</th><th>Validation Markets</th><th>Completed</th><th>Error</th></tr></thead><tbody>
    @forelse($marketRuns as $run)<tr><td>{{ $run->started_at?->format('d M H:i:s') }}</td><td>{{ strtoupper($run->status) }}</td><td>{{ $run->prices_updated }}</td><td>{{ $run->candle_symbols_updated }}</td><td>{{ $run->validation_symbols_updated }}</td><td>{{ $run->completed_at?->format('H:i:s') ?? 'Running' }}</td><td>{{ $run->error_message ?: '—' }}</td></tr>
    @empty<tr><td colspan="7">No central market-data run yet. Run the scheduler or abs:pulse-market-data.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
