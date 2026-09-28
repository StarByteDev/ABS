@extends('admin.layout')
@section('title','Scan & Signals — ABS Admin')
@section('heading','Scan & Signals')
@section('description','Review each strategy scan and every qualified signal found in that run.')
@section('content')
@include('admin.pulse.workflow.step-head',['step'=>4,'title'=>'Scan & Signals','subtitle'=>'Each scan can produce multiple qualified signals. Paper entry begins only after a later market cycle confirms the entry level.'])

<section class="wf-mini-kpis wf-mini-kpis-five">
    <article><span>System scans</span><b>{{ number_format($summary['total']) }}</b></article>
    <article><span>Completed</span><b class="good">{{ number_format($summary['completed']) }}</b></article>
    <article><span>Research signals</span><b>{{ number_format($summary['signals']) }}</b></article>
    <article><span>Waiting entry</span><b class="warn">{{ number_format($summary['waiting']) }}</b></article>
    <article><span>Paper entries</span><b class="good">{{ number_format($summary['entered']) }}</b></article>
</section>

<section class="wf-inline-note">
    <span class="wf-note-icon">↳</span>
    <div><b>Signal → Paper Trade</b><small>A signal is queued first. It becomes a paper trade when a later synchronized market cycle observes its entry price. TP/SL monitoring starts after entry.</small></div>
    <div class="wf-note-actions"><form method="POST" action="{{ route('admin.pulse.strategy-dashboard.run-now') }}">@csrf<button type="submit">Run Next Cycle</button></form><a href="{{ route('admin.pulse.paper-trades') }}">Paper trades →</a></div>
</section>

<section class="wf-panel wf-table-panel">
    <div class="wf-panel-head">
        <div><span class="wf-kicker">SCAN REGISTER</span><h2>Strategy-engine runs</h2></div>
        <form class="wf-filter" method="GET">
            <select name="status"><option value="">All status</option>@foreach(['completed','failed','running'] as $s)<option value="{{ $s }}" {{ $status===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach</select>
            <select name="signal"><option value="">All results</option><option value="yes" {{ $signal==='yes'?'selected':'' }}>Signal found</option><option value="no" {{ $signal==='no'?'selected':'' }}>No signal</option></select>
            <button>Apply</button>
        </form>
    </div>
    <div class="wf-table-wrap"><table class="wf-table wf-scan-table">
        <thead><tr><th>Scan</th><th>Date & time</th><th>Markets</th><th>Qualified</th><th>Signals in this scan</th><th>Paper status</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($runs as $row)
            @php($run=$row['run'])
            <tr>
                <td><b>#{{ $run->id }}</b><small>{{ $row['duration']===null?'—':$row['duration'].' sec' }}</small></td>
                <td>{{ $run->started_at?->format('d M Y · H:i:s') ?? '—' }}</td>
                <td><b>{{ number_format($row['markets']) }}</b><small>{{ number_format($row['evaluations']) }} evaluations</small></td>
                <td><b>{{ number_format($row['qualified']) }}</b><small>{{ number_format($row['new_signals']) }} new · {{ number_format($row['reused_signals']) }} reused</small></td>
                <td class="wf-signals-cell">
                    @if($row['signals']->isEmpty())
                        <span class="wf-pill is-neutral">NO QUALIFIED SIGNAL</span>
                    @else
                        <div class="wf-signal-stack">
                        @foreach($row['signals']->take(4) as $signalRow)
                            @php($s=$signalRow['model'])
                            <span class="wf-signal-chip {{ $signalRow['rank']===1?'is-top':'' }}"><b>{{ $s->symbol }}</b><em>{{ $s->direction }} · {{ strtoupper($s->timeframe) }}</em>@if($signalRow['rank']===1)<i>TOP</i>@endif</span>
                        @endforeach
                        @if($row['signals']->count()>4)<span class="wf-more-chip">+{{ $row['signals']->count()-4 }} more</span>@endif
                        </div>
                        @if($row['legacy_unstored']>0)<small class="wf-legacy-note">Older scan: {{ $row['legacy_unstored'] }} additional qualified candidate{{ $row['legacy_unstored']===1?' was':'s were' }} not stored as separate signal records.</small>@endif
                    @endif
                </td>
                <td>
                    <div class="wf-state-line"><span>Waiting</span><b>{{ $row['waiting'] }}</b></div>
                    <div class="wf-state-line"><span>Entered</span><b class="good">{{ $row['entered'] }}</b></div>
                    <div class="wf-state-line"><span>Resolved</span><b>{{ $row['resolved'] }}</b></div>
                </td>
                <td><span class="wf-pill is-{{ $run->status==='completed'?'good':($run->status==='failed'?'bad':'neutral') }}">{{ strtoupper($run->status) }}</span></td>
                <td><a class="wf-row-link" href="{{ route('admin.pulse.scan-audit.show',$run) }}">Open scan →</a></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="wf-empty">No system scans recorded yet.</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="wf-pagination">{{ $runs->links('vendor.pagination.abs-admin') }}</div>
</section>
@endsection
