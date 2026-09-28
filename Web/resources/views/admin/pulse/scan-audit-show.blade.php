@extends('admin.layout')
@section('title','Scan #'.$scannerRun->id.' Audit')
@section('heading','Scan #'.$scannerRun->id.' · Full Trace')
@section('description','Review the exact markets, qualified signals, paper-entry status and outcomes from this scan.')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/admin-scan-audit-v1521.css') }}?v={{ @filemtime(public_path('assets/css/admin-scan-audit-v1521.css')) ?: '15.2.1' }}">
@endpush
@section('page-actions')
<div class="scan-page-actions"><a class="button button-ghost" href="{{ route('admin.pulse.scan-audit') }}">← Audit & History</a><a class="button button-ghost" href="{{ route('admin.pulse.scan-signals') }}">Scan & Signals</a></div>
@endsection
@section('content')
@php
    $price = static function ($value): string { if ($value===null) return '—'; $n=abs((float)$value); $p=$n>=1000?2:($n>=1?4:($n>=.01?6:8)); return rtrim(rtrim(number_format((float)$value,$p,'.',','),'0'),'.'); };
    $durationSeconds=$row['duration_seconds'];
    $duration=$durationSeconds===null?'Running':($durationSeconds<60?$durationSeconds.'s':intdiv($durationSeconds,60).'m '.str_pad((string)($durationSeconds%60),2,'0',STR_PAD_LEFT).'s');
    $enteredSignals=$scanSignals->filter(fn($r)=>$r['model']->validation?->entry_hit_at);
    $resolvedSignals=$scanSignals->filter(fn($r)=>$r['model']->validation?->resolved_at);
    $waitingSignals=$scanSignals->filter(fn($r)=>!$r['model']->validation?->entry_hit_at && !$r['model']->validation?->resolved_at);
    $allTrades=$scanSignals->flatMap(fn($r)=>$r['model']->trades ?? collect())->unique('id')->values();
@endphp

<section class="scan-detail-hero enterprise-surface">
    <div><span class="scan-eyebrow">SCAN EVIDENCE</span><h2>{{ $row['source'] }} · {{ $scannerRun->started_at?->format('d M Y · H:i:s') ?? 'Unknown start time' }}</h2><p>Everything below belongs to scanner run <b>#{{ $scannerRun->id }}</b>.</p></div>
    <div class="scan-detail-status"><span class="scan-state {{ $scannerRun->status==='completed'?'good':($scannerRun->status==='failed'?'danger':'warn') }}">{{ strtoupper($scannerRun->status) }}</span><b>{{ $duration }}</b><small>{{ $scannerRun->completed_at ? 'completed '.$scannerRun->completed_at->format('H:i:s') : 'still running' }}</small></div>
</section>

<section class="scan-detail-kpis">
    <article><small>Markets</small><strong>{{ number_format($row['markets']) }}</strong><em>{{ number_format($row['evaluations']) }} evaluations</em></article>
    <article><small>Qualified</small><strong>{{ number_format($row['qualified_candidates']) }}</strong><em>setups above threshold</em></article>
    <article><small>Signals in scan</small><strong>{{ number_format($scanSignals->count()) }}</strong><em>{{ number_format((int)$scannerRun->signals_created) }} new records</em></article>
    <article><small>Waiting entry</small><strong>{{ number_format($waitingSignals->count()) }}</strong><em>not a paper trade yet</em></article>
    <article><small>Paper entries</small><strong>{{ number_format($enteredSignals->count()) }}</strong><em>entry observed later</em></article>
    <article><small>Resolved</small><strong>{{ number_format($resolvedSignals->count()) }}</strong><em>TP / SL / void / expired</em></article>
    <article><small>Exchange trades</small><strong>{{ number_format($allTrades->count()) }}</strong><em>real execution only</em></article>
    <article class="{{ $allTrades->where('status','closed')->sum('realized_pnl')>=0?'positive':'negative' }}"><small>Realized P&amp;L</small><strong>{{ number_format((float)$allTrades->where('status','closed')->sum('realized_pnl'),6) }}</strong><em>exchange records only</em></article>
</section>

<section class="enterprise-surface no-pad scan-register-card">
    <div class="scan-panel-head padded"><div><span class="scan-eyebrow">QUALIFIED SIGNALS</span><h2>Every signal found in this scan</h2><p>The top-ranked signal is highlighted, but all qualified setups are retained for Admin research and paper validation.</p></div><span class="scan-period-chip">{{ number_format($scanSignals->count()) }} SIGNALS</span></div>
    <div class="scan-table-wrap"><table class="scan-market-table"><thead><tr><th>Rank</th><th>Signal</th><th>TF</th><th>Side</th><th>Score</th><th>Confidence</th><th>Entry</th><th>SL</th><th>TP</th><th>Paper status</th><th>Record</th></tr></thead><tbody>
    @forelse($scanSignals as $signalRow)
        @php($s=$signalRow['model'])
        @php($v=$s->validation)
        @php($paper=$v?->outcome ?: ($v?->entry_hit_at ? 'OPEN' : 'WAITING ENTRY'))
        <tr class="{{ $signalRow['rank']===1?'is-best':'' }}">
            <td><b>{{ $signalRow['rank'] ?: '—' }}</b>@if($signalRow['rank']===1)<small class="best-marker">TOP RANKED</small>@endif</td>
            <td><b>#{{ $s->id }} · {{ $s->symbol }}</b><small>{{ $signalRow['persistence']==='created'?'NEW SIGNAL':'REUSED OPEN SETUP' }}</small></td>
            <td>{{ strtoupper($s->timeframe) }}</td>
            <td><span class="scan-side {{ strtolower($s->direction) }}">{{ $s->direction }}</span></td>
            <td>{{ number_format((float)$s->score,1) }}</td>
            <td>{{ number_format((float)($s->confidence_score ?? $s->score),1) }}%</td>
            <td>{{ $price($s->entry_price) }}</td><td class="scan-loss">{{ $price($s->stop_loss) }}</td><td class="scan-profit">{{ $price($s->take_profit) }}</td>
            <td><span class="scan-outcome {{ $paper==='tp'?'good':($paper==='sl'?'danger':($paper==='OPEN'?'info':'muted')) }}">{{ strtoupper(str_replace('_',' ',$paper)) }}</span><small>{{ $v?->entry_hit_at ? 'entry '.$v->entry_hit_at->format('d M H:i:s') : 'entry not observed' }}</small></td>
            <td>{{ strtoupper($signalRow['persistence']) }}</td>
        </tr>
    @empty<tr><td colspan="11"><div class="scan-empty"><b>No qualified signal records</b><span>This scan completed without a setup crossing the qualification threshold.</span></div></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="enterprise-surface no-pad scan-register-card">
    <div class="scan-panel-head padded"><div><span class="scan-eyebrow">MARKET-BY-MARKET EVALUATION</span><h2>What the strategy engine evaluated</h2><p>Use this section only when you need to inspect why a market did or did not qualify.</p></div><span class="scan-period-chip">{{ number_format($marketRows->count()) }} ROWS</span></div>
    <div class="scan-table-wrap"><table class="scan-market-table"><thead><tr><th>Market</th><th>TF</th><th>Direction</th><th>Score</th><th>Qualification</th><th>Last / entry</th><th>SL / TP</th><th>Top strategies</th><th>Data</th></tr></thead><tbody>
    @forelse($marketRows as $market)
        <tr class="{{ $market['is_best']?'is-best':'' }}"><td><b>{{ $market['symbol'] }}</b>@if($market['is_best'])<small class="best-marker">TOP RANKED</small>@endif</td><td>{{ $market['timeframe'] }}</td><td><span class="scan-side {{ strtolower($market['direction']) }}">{{ $market['direction'] }}</span></td><td><b>{{ $market['score']===null?'—':number_format($market['score'],1) }}</b></td><td>@if($market['qualified'])<span class="scan-outcome good">QUALIFIED</span>@elseif($market['score']===null)<span class="scan-outcome muted">NOT RECORDED</span>@else<span class="scan-outcome muted">BELOW THRESHOLD</span>@endif</td><td><b>{{ $price($market['last_price']) }}</b><small>entry {{ $price($market['entry_price']) }}</small></td><td><b class="scan-loss">SL {{ $price($market['stop_loss']) }}</b><small class="scan-profit">TP {{ $price($market['take_profit']) }}</small></td><td>{{ $market['strategies'] ? implode(' · ',$market['strategies']) : '—' }}</td><td>@if($market['error']!=='')<span class="scan-outcome danger">UNAVAILABLE</span><small>{{ $market['error'] }}</small>@else<span class="scan-outcome good">EVALUATED</span>@endif</td></tr>
    @empty<tr><td colspan="9"><div class="scan-empty"><b>No detailed evaluation rows stored</b><span>This can occur for older or interrupted scans.</span></div></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="enterprise-surface no-pad scan-register-card">
    <div class="scan-panel-head padded"><div><span class="scan-eyebrow">EXCHANGE EXECUTION</span><h2>Real trades linked to signals from this scan</h2><p>Paper validation is kept separate from actual exchange execution.</p></div><span class="scan-period-chip">{{ number_format($allTrades->count()) }} TRADES</span></div>
    <div class="scan-table-wrap"><table class="scan-trade-table"><thead><tr><th>Trade</th><th>Signal</th><th>User</th><th>Mode</th><th>Status</th><th>Opened</th><th>Closed</th><th>Result</th><th>Realized P&amp;L</th><th>Fees</th></tr></thead><tbody>
    @forelse($allTrades as $trade)<tr><td><b>#{{ $trade->id }}</b><small>{{ $trade->symbol }} · {{ $trade->side }}</small></td><td>#{{ $trade->signal_id }}</td><td>{{ $trade->user?->email ?? 'System' }}</td><td>{{ strtoupper($trade->environment) }}</td><td>{{ strtoupper(str_replace('_',' ',$trade->status)) }}</td><td>{{ $trade->opened_at?->format('d M · H:i:s') ?? '—' }}</td><td>{{ $trade->closed_at?->format('d M · H:i:s') ?? '—' }}</td><td>{{ $trade->close_reason ? strtoupper(str_replace('_',' ',$trade->close_reason)) : '—' }}</td><td class="{{ (float)$trade->realized_pnl>=0?'scan-profit':'scan-loss' }}">{{ number_format((float)$trade->realized_pnl,6) }}</td><td>{{ number_format((float)$trade->fees,6) }}</td></tr>
    @empty<tr><td colspan="10"><div class="scan-empty"><b>No exchange trades linked</b><span>This is expected while the validation engine is running in research/paper mode.</span></div></td></tr>@endforelse
    </tbody></table></div>
</section>

<div class="scan-detail-two-col bottom">
    <section class="enterprise-surface scan-evidence-card"><span class="scan-eyebrow">SCAN SUMMARY</span><h3>Recorded engine context</h3><dl><div><dt>Pairs scanned</dt><dd>{{ number_format((int)($context['pairs_scanned'] ?? $scannerRun->pairs_scanned)) }}</dd></div><div><dt>Evaluations</dt><dd>{{ number_format((int)($context['market_timeframes_evaluated'] ?? $row['evaluations'])) }}</dd></div><div><dt>Unavailable</dt><dd>{{ number_format((int)($context['market_timeframes_unavailable'] ?? $row['unavailable'])) }}</dd></div><div><dt>Qualified</dt><dd>{{ number_format((int)($context['qualified_candidates'] ?? $row['qualified_candidates'])) }}</dd></div><div><dt>New signals</dt><dd>{{ number_format((int)$scannerRun->signals_created) }}</dd></div><div><dt>Minimum score</dt><dd>{{ $threshold===null?'—':number_format($threshold,1) }}</dd></div></dl></section>
    <section class="enterprise-surface scan-evidence-card"><span class="scan-eyebrow">RAW TRACE</span><h3>Machine-readable evidence</h3><p>Use only when investigating a discrepancy.</p><details class="scan-raw"><summary>Show scanner audit JSON</summary><pre>{{ json_encode($audit?->context ?? [],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>@if($scannerRun->error_message)<div class="scan-error-box"><b>Run error</b><span>{{ $scannerRun->error_message }}</span></div>@endif</section>
</div>
@endsection
