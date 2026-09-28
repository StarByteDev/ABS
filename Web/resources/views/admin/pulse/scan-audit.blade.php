@extends('admin.layout')
@section('title','Audit & History — ABS Admin')
@section('heading','Audit & History')
@section('description','Deep traceability for price-to-signal-to-result validation when you need it.')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/admin-scan-audit-v1521.css') }}?v={{ @filemtime(public_path('assets/css/admin-scan-audit-v1521.css')) ?: '15.2.1' }}">
@endpush
@section('page-actions')
<div class="scan-page-actions"><a class="button button-ghost" href="{{ route('admin.pulse.strategy-dashboard') }}">Strategy Overview</a><form method="POST" action="{{ route('admin.pulse.strategy-dashboard.run-now') }}">@csrf<button class="button button-primary" type="submit">Run Full Cycle</button></form></div>
@endsection
@section('content')
@include('admin.pulse.partials.strategy-lab-nav')
@php
    $outcomeLabels = [
        'tp'=>'TP reached','sl'=>'SL reached','ambiguous'=>'Ambiguous','expired_no_entry'=>'Expired before entry',
        'expired_after_entry'=>'Expired after entry','entry_open'=>'Entry reached · open','waiting_entry'=>'Waiting for entry',
        'validation_pending'=>'Validation pending','no_signal'=>'No qualified signal',
    ];
    $statusClass = fn($status) => $status==='completed'?'good':($status==='failed'?'danger':'warn');
    $outcomeClass = fn($outcome) => $outcome==='tp'?'good':($outcome==='sl'?'danger':($outcome==='ambiguous'?'warn':(str_contains((string)$outcome,'expired')?'muted':'info')));
    $duration = static function ($seconds): string {
        if ($seconds === null) return 'Running';
        $seconds=(int)$seconds;
        if ($seconds < 60) return $seconds.'s';
        $minutes=intdiv($seconds,60); $remain=$seconds%60;
        return $minutes.'m '.str_pad((string)$remain,2,'0',STR_PAD_LEFT).'s';
    };
@endphp

<section class="scan-trace-hero enterprise-surface">
    <div>
        <span class="scan-eyebrow">DEEP TRACE</span>
        <h2>Audit any strategy cycle</h2>
        <p>Open a scan only when you need the detailed evidence behind a result.</p>
    </div>
    <div class="scan-flow" aria-label="Pulse scan lifecycle">
        <span>SCAN</span><i>→</i><span>MARKETS</span><i>→</i><span>QUALIFY</span><i>→</i><span>SIGNALS</span><i>→</i><span>ENTRY</span><i>→</i><span>TP / SL / VOID</span><i>→</i><span>EXECUTION</span>
    </div>
</section>

<section class="scan-audit-kpis" aria-label="Scan audit summary">
    <article><small>Scans performed</small><strong>{{ number_format($summary['runs']) }}</strong><em>{{ number_format($summary['completed']) }} completed · {{ number_format($summary['failed']) }} failed</em></article>
    <article><small>Pairs scanned</small><strong>{{ number_format($summary['pairs_scanned']) }}</strong><em>sum of pair-universe size across scans</em></article>
    <article><small>Qualified candidates</small><strong>{{ number_format($summary['qualified_candidates']) }}</strong><em>setups above the scan threshold</em></article>
    <article><small>Research signals</small><strong>{{ number_format($summary['signals_published']) }}</strong><em>{{ number_format($summary['new_signals_created']) }} newly created records</em></article>
    <article class="positive"><small>Research TP</small><strong>{{ number_format($summary['tp']) }}</strong><em>{{ number_format($summary['entries']) }} entries observed</em></article>
    <article class="negative"><small>Research SL</small><strong>{{ number_format($summary['sl']) }}</strong><em>{{ number_format($summary['ambiguous']) }} ambiguous · {{ number_format($summary['expired']) }} expired</em></article>
    <article><small>Backend trades</small><strong>{{ number_format($summary['trades']) }}</strong><em>{{ number_format($summary['closed_trades']) }} closed · TP {{ number_format($summary['trade_tp']) }} / SL {{ number_format($summary['trade_sl']) }}</em></article>
    <article class="{{ $summary['realized_pnl'] >= 0 ? 'positive' : 'negative' }}"><small>Actual realized P&amp;L</small><strong>{{ number_format((float)$summary['realized_pnl'],6) }}</strong><em>exchange records only — never paper results</em></article>
</section>

<section class="enterprise-surface scan-filter-surface">
    <div class="scan-panel-head"><div><span class="scan-eyebrow">FILTER THE EVIDENCE</span><h2>Scan register</h2><p>Default view is the latest seven days. Use the outcome filter to isolate TP, SL, ambiguity, expiry, open setups or scans where no qualified signal was found.</p></div><span class="scan-period-chip">{{ $from->format('d M Y') }} → {{ $to->format('d M Y') }}</span></div>
    <form method="GET" class="scan-filter-grid">
        <label><span>From</span><input type="date" name="from" value="{{ $from->toDateString() }}"></label>
        <label><span>To</span><input type="date" name="to" value="{{ $to->toDateString() }}"></label>
        <label><span>Run status</span><select name="status"><option value="">All statuses</option><option value="completed" @selected($filters['status']==='completed')>Completed</option><option value="failed" @selected($filters['status']==='failed')>Failed</option><option value="running" @selected($filters['status']==='running')>Running</option></select></label>
        <label><span>Scan source</span><select name="source"><option value="">All sources</option><option value="system" @selected($filters['source']==='system')>System / research</option><option value="member" @selected($filters['source']==='member')>Member scan</option></select></label>
        <label><span>Signal outcome</span><select name="outcome"><option value="">All outcomes</option><option value="tp" @selected($filters['outcome']==='tp')>TP reached</option><option value="sl" @selected($filters['outcome']==='sl')>SL reached</option><option value="ambiguous" @selected($filters['outcome']==='ambiguous')>Ambiguous</option><option value="expired_no_entry" @selected($filters['outcome']==='expired_no_entry')>Expired before entry</option><option value="expired_after_entry" @selected($filters['outcome']==='expired_after_entry')>Expired after entry</option><option value="open" @selected($filters['outcome']==='open')>Still open</option><option value="no_signal" @selected($filters['outcome']==='no_signal')>No qualified signal</option></select></label>
        <div class="scan-filter-actions"><button class="button button-primary" type="submit">Apply Audit</button><a class="button button-ghost" href="{{ route('admin.pulse.scan-audit') }}">Reset</a></div>
    </form>
</section>

<section class="enterprise-surface no-pad scan-register-card">
    <div class="scan-panel-head padded"><div><span class="scan-eyebrow">CHRONOLOGICAL REGISTER</span><h2>{{ number_format($runs->total()) }} matching scans</h2><p>Newest first. Open any row for its market-by-market evaluation, qualified signals, paper validation evidence, and exchange execution records.</p></div><span class="scan-period-chip">AUDITABLE</span></div>
    <div class="scan-table-wrap">
        <table class="scan-audit-table">
            <thead><tr><th>Scan / time</th><th>Source</th><th>Coverage</th><th>Qualification</th><th>Signals</th><th>Research outcome</th><th>Backend execution</th><th></th></tr></thead>
            <tbody>
            @forelse($runs as $row)
                @php($run=$row['model'])
                @php($signal=$row['signal'])
                @php($trade=$row['latest_trade'])
                <tr>
                    <td><b>#{{ $run->id }}</b><small>{{ $run->started_at?->format('d M Y · H:i:s') ?? '—' }}</small><small>{{ $duration($row['duration_seconds']) }} · <span class="scan-state {{ $statusClass($run->status) }}">{{ strtoupper($run->status) }}</span></small></td>
                    <td><span class="scan-source is-{{ $row['source_key'] }}">{{ $row['source'] }}</span>@if($run->user)<small>{{ $run->user->email }}</small>@else<small>user-independent engine</small>@endif</td>
                    <td><b>{{ number_format($row['markets']) }} markets</b><small>{{ number_format($row['evaluations']) }} timeframe evaluations</small>@if($row['unavailable']>0)<small class="scan-warn">{{ number_format($row['unavailable']) }} unavailable</small>@else<small>15M + 4H evidence</small>@endif</td>
                    <td><b>{{ number_format($row['qualified_candidates']) }} candidates</b><small>{{ $row['threshold']===null?'threshold not recorded':'minimum score '.number_format($row['threshold'],1) }}</small><small>{{ $row['signal_count'] > 0 ? number_format($row['signal_count']).' signal'.($row['signal_count']===1?'':'s').' found' : 'no signal found' }}</small></td>
                    <td>@if($signal)<b>{{ number_format($row['signal_count']) }} signal{{ $row['signal_count']===1?'':'s' }}</b><small><span class="scan-market">{{ $signal->symbol }}</span> · {{ $signal->direction }} · {{ strtoupper($signal->timeframe) }} top ranked</small><small>Open trace to review every signal</small>@else<span class="scan-empty-label">NO SIGNAL</span><small>No qualified setup in this scan</small>@endif</td>
                    <td><span class="scan-outcome {{ $outcomeClass($row['outcome']) }}">{{ strtoupper($outcomeLabels[$row['outcome']] ?? str_replace('_',' ',$row['outcome'])) }}</span>@if($row['validation']?->entry_hit_at)<small>Entry {{ $row['validation']->entry_hit_at->format('d M · H:i:s') }}</small>@endif @if($row['validation']?->resolved_at)<small>Resolved {{ $row['validation']->resolved_at->format('d M · H:i:s') }}</small>@endif</td>
                    <td>@if($row['trade_count']>0)<b>{{ number_format($row['trade_count']) }} trade record{{ $row['trade_count']===1?'':'s' }}</b><small>{{ strtoupper(str_replace('_',' ',(string)$trade?->status)) }} · {{ $trade?->close_reason ? str_replace('_',' ',$trade->close_reason) : 'no close reason' }}</small><small class="{{ $row['realized_pnl']>=0?'scan-profit':'scan-loss' }}">Realized {{ number_format($row['realized_pnl'],6) }}</small>@else<span class="scan-empty-label">NO TRADE</span><small>No exchange execution linked</small>@endif</td>
                    <td><a class="scan-open-button" href="{{ route('admin.pulse.scan-audit.show',$run) }}">Open trace →</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="scan-empty"><b>No scans match these filters</b><span>Reset the filters or run a new research cycle from the Strategy Dashboard.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="enterprise-pagination">{{ $runs->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
</section>

<section class="scan-definition-grid">
    <article class="enterprise-surface"><span class="scan-eyebrow">IMPORTANT DISTINCTION</span><h3>One scan can produce multiple signals</h3><p>System research keeps every qualified market/timeframe for paper validation while still marking the strongest setup as top ranked. Member-facing scans keep the simpler Best Signal experience.</p></article>
    <article class="enterprise-surface"><span class="scan-eyebrow">IMPORTANT DISTINCTION</span><h3>Research outcome ≠ exchange trade</h3><p>TP, SL, ambiguous and expired are independent market-validation results. Backend execution appears only when an actual <code>PulseTrade</code> record exists. This keeps future Binance automation reporting honest.</p></article>
</section>
@endsection
