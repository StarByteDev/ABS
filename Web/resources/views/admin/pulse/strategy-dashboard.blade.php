@extends('admin.layout')
@section('title','Pulse Strategy Lab — ABS Admin')
@section('heading','Pulse Strategy Lab')
@section('description','Run the engine, follow each stage, and validate which strategies are winning or losing.')
@section('page-actions')
<div class="lab-page-actions">
    <form method="POST" action="{{ route('admin.market-data.refresh') }}">@csrf<button class="button button-ghost" type="submit">Refresh Prices</button></form>
    <form method="POST" action="{{ route('admin.pulse.strategy-dashboard.run-now') }}">@csrf<button class="button button-primary" type="submit">Run Full Cycle</button></form>
</div>
@endsection
@section('content')
@php
    $fmtPct = fn($v) => $v === null ? '—' : number_format((float)$v,1).'%';
    $fmtR = fn($v) => (($v ?? 0) > 0 ? '+' : '').number_format((float)($v ?? 0),2).'R';
    $feedStatus = strtolower((string)($marketHealth['feed_status'] ?? 'offline'));
    $engineStatus = strtolower((string)($research['last_status'] ?? 'never'));
    $lastCycleStatus = strtolower((string)($lastCycle['status'] ?? 'never'));
    $outcomeLabel = fn($v) => match((string)$v) {
        'tp'=>'TP / WIN','sl'=>'SL / LOSS','ambiguous'=>'AMBIGUOUS','expired_no_entry'=>'EXPIRED · NO ENTRY',
        'expired_after_entry'=>'EXPIRED · AFTER ENTRY',default=>strtoupper($v ?: 'OPEN')
    };
    $outcomeClass = fn($v) => $v==='tp'?'good':($v==='sl'?'bad':($v==='ambiguous'?'warn':'neutral'));
@endphp

@include('admin.pulse.partials.strategy-lab-nav')

<section class="lab-control-card enterprise-surface">
    <div class="lab-control-head">
        <div>
            <span class="lab-eyebrow">ENGINE CONTROL</span>
            <h2>Choose how ABS runs the research cycle</h2>
        </div>
        <div class="lab-live-state {{ $research['enabled']?'is-on':'is-off' }}"><i></i>{{ $research['enabled']?'ENGINE ON':'ENGINE PAUSED' }}</div>
    </div>
    <form method="POST" action="{{ route('admin.pulse.strategy-dashboard.engine') }}" class="lab-engine-form" data-lab-engine-form>
        @csrf @method('PUT')
        <input type="hidden" name="enabled" value="{{ $research['enabled']?'true':'false' }}" data-lab-enabled>
        <div class="lab-mode-grid">
            <label class="lab-mode-option {{ $cadence['mode']==='cron'?'selected':'' }}">
                <input type="radio" name="mode" value="cron" @checked($cadence['mode']==='cron')>
                <span class="lab-mode-icon">C</span><b>Production Cron</b><small>HostGator / live · 1 min+</small>
            </label>
            <label class="lab-mode-option {{ $cadence['mode']==='internal'?'selected':'' }}">
                <input type="radio" name="mode" value="internal" @checked($cadence['mode']==='internal')>
                <span class="lab-mode-icon">A</span><b>ABS Scheduler</b><small>Local / VPS · 5 sec+</small>
            </label>
            <label class="lab-mode-option {{ $cadence['mode']==='manual'?'selected':'' }}">
                <input type="radio" name="mode" value="manual" @checked($cadence['mode']==='manual')>
                <span class="lab-mode-icon">M</span><b>Manual Only</b><small>Run when you choose</small>
            </label>
        </div>
        <div class="lab-engine-row">
            <label class="lab-field"><span>Cycle interval</span><select name="interval_seconds" data-lab-interval>
                <option value="5" @selected($cadence['configured_seconds']===5)>5 seconds</option>
                <option value="30" @selected($cadence['configured_seconds']===30)>30 seconds</option>
                <option value="60" @selected($cadence['configured_seconds']===60)>1 minute</option>
                <option value="120" @selected($cadence['configured_seconds']===120)>2 minutes</option>
                <option value="300" @selected($cadence['configured_seconds']===300)>5 minutes</option>
            </select></label>
            <label class="lab-toggle"><input type="checkbox" data-lab-enable-toggle @checked($research['enabled'])><span></span><b>Research engine</b></label>
            <div class="lab-effective"><span>Effective</span><b>{{ $cadence['mode_label'] }} · {{ $cadence['mode']==='manual'?'On demand':$cadence['effective_seconds'].' sec' }}</b></div>
            <button class="button button-primary" type="submit">Save</button>
        </div>
    </form>
    @if($cadence['mode']==='internal' && $cadence['hostgator_shared'])
        <div class="lab-note warn"><b>Shared-hosting protection:</b> 5s/30s selections are stored, but HostGator runs no faster than 60 seconds.</div>
    @elseif($cadence['mode']==='internal')
        <div class="lab-note"><b>Local/VPS:</b> keep <code>php artisan schedule:work</code> running. The ABS launcher already starts it locally.</div>
    @elseif($cadence['mode']==='manual')
        <div class="lab-note"><b>Manual mode:</b> scheduled research is paused. “Run Full Cycle” refreshes prices → scans → checks paper outcomes.</div>
    @else
        <div class="lab-note"><b>Production mode:</b> your server cron triggers the pipeline. On HostGator use the existing once-per-minute schedule.</div>
    @endif
</section>

<section class="lab-stage-flow" aria-label="Pulse strategy validation workflow">
@foreach($stages as $stage)
    <a href="{{ $stage['href'] }}" class="lab-stage is-{{ $stage['status'] }}">
        <span class="lab-stage-no">{{ str_pad((string)$stage['number'],2,'0',STR_PAD_LEFT) }}</span>
        <div><small>{{ $stage['subtitle'] }}</small><h3>{{ $stage['title'] }}</h3><b>{{ $stage['primary'] }}</b><em>{{ $stage['secondary'] }}</em></div>
        <i>›</i>
    </a>
@endforeach
</section>

<section class="lab-kpi-grid" aria-label="Strategy validation summary">
    <article><small>Cycles</small><strong>{{ number_format($systemScans) }}</strong><span>{{ number_format($completedScans) }} completed</span></article>
    <article><small>Paper trades entered</small><strong>{{ number_format($outcomes['entries']) }}</strong><span>{{ number_format($outcomes['open']) }} still unresolved</span></article>
    <article class="good"><small>Wins · TP</small><strong>{{ number_format($outcomes['tp']) }}</strong><span>resolved winners</span></article>
    <article class="bad"><small>Losses · SL</small><strong>{{ number_format($outcomes['sl']) }}</strong><span>resolved losses</span></article>
    <article><small>Win rate</small><strong>{{ $fmtPct($outcomes['win_rate']) }}</strong><span>TP ÷ (TP + SL)</span></article>
    <article class="{{ ($simulation['model_return_pct'] ?? 0)>=0?'good':'bad' }}"><small>Paper model return</small><strong>{{ (($simulation['model_return_pct'] ?? 0) >= 0 ? '+' : '').number_format((float)($simulation['model_return_pct'] ?? 0),2) }}%</strong><span>Net {{ $fmtR($outcomes['net_r']) }} · {{ number_format($outcomes['void']) }} void</span></article>
</section>

<section class="lab-overview-grid">
    <article class="enterprise-surface lab-trend-card">
        <div class="lab-section-head"><div><span class="lab-eyebrow">PERFORMANCE</span><h2>Research result over time</h2></div><div class="lab-window-tabs"><a class="{{ $range==='1d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'1d']) }}">Today</a><a class="{{ $range==='7d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'7d']) }}">7D</a><a class="{{ $range==='30d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'30d']) }}">30D</a><a class="{{ $range==='90d'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard',['range'=>'90d']) }}">90D</a></div></div>
        <div class="lab-chart-wrap"><canvas id="strategyTrendChart" aria-label="Cumulative research performance"></canvas><div id="strategyTrendEmpty" class="lab-empty" hidden>No resolved paper trades yet.</div></div>
        <div class="lab-chart-foot"><span>TP <b>{{ $outcomes['tp'] }}</b></span><span>SL <b>{{ $outcomes['sl'] }}</b></span><span>Win rate <b>{{ $fmtPct($outcomes['win_rate']) }}</b></span><span>Net <b>{{ $fmtR($outcomes['net_r']) }}</b></span></div>
    </article>

    <article class="enterprise-surface lab-health-card">
        <div class="lab-section-head"><div><span class="lab-eyebrow">CURRENT STATUS</span><h2>Is the engine healthy?</h2></div><span class="lab-status-dot is-{{ $feedStatus==='healthy'&&in_array($engineStatus,['completed','running'])?'good':'warn' }}"></span></div>
        <div class="lab-health-list">
            <div><span>Market feed</span><b class="is-{{ $feedStatus==='healthy'?'good':'warn' }}">{{ strtoupper($feedStatus) }}</b><small>{{ isset($marketHealth['price_age_seconds']) ? $marketHealth['price_age_seconds'].'s old' : 'no prices' }}</small></div>
            <div><span>Last strategy scan</span><b>{{ strtoupper($engineStatus) }}</b><small>{{ $research['last_run_at'] ? \Illuminate\Support\Carbon::parse($research['last_run_at'])->diffForHumans() : 'never' }}</small></div>
            <div><span>Last full cycle</span><b>{{ strtoupper($lastCycleStatus) }}</b><small>{{ !empty($lastCycle['completed_at']) ? \Illuminate\Support\Carbon::parse($lastCycle['completed_at'])->diffForHumans() : 'not run manually yet' }}</small></div>
            <div><span>Live exchange layer</span><b class="is-lock">LOCKED</b><small>paper validation remains separate</small></div>
        </div>
        <a class="lab-text-link" href="{{ route('admin.market-data') }}">Open market-data report →</a>
    </article>
</section>

<section class="enterprise-surface lab-table-card" id="strategy-performance">
    <div class="lab-section-head lab-table-title"><div><span class="lab-eyebrow">STRATEGY PERFORMANCE</span><h2>Which strategies are winning?</h2><p>Ranked from current paper-trade evidence. “Collecting” means there are not enough decisive results yet.</p></div><a class="lab-text-link" href="{{ route('admin.pulse.strategies') }}">Engine setup →</a></div>
    <div class="lab-table-wrap"><table class="lab-table strategy-leaderboard"><thead><tr><th>#</th><th>Strategy</th><th>Win / Loss</th><th>Win rate</th><th>Net R</th><th>Model return</th><th>Reliability</th><th>Assessment</th></tr></thead><tbody>
    @forelse($strategyRows as $row)
        <tr>
            <td><span class="lab-rank">{{ $loop->iteration }}</span></td>
            <td><b>{{ $row['name'] }}</b><small>{{ number_format($row['decisive_trades']) }} decisive trades · {{ number_format($row['signals']) }} signals · {{ $row['evidence_level'] }}</small></td>
            <td><span class="lab-win">{{ $row['tp'] }}</span> / <span class="lab-loss">{{ $row['sl'] }}</span></td>
            <td><b>{{ $fmtPct($row['win_rate']) }}</b></td>
            <td><b class="{{ $row['net_r']>=0?'lab-win':'lab-loss' }}">{{ $fmtR($row['net_r']) }}</b></td>
            <td><b class="{{ ($row['model_return_pct'] ?? 0)>=0?'lab-win':'lab-loss' }}">{{ $row['model_return_pct']===null?'—':(($row['model_return_pct']>=0?'+':'').number_format($row['model_return_pct'],2).'%') }}</b></td>
            <td>{{ $row['reliability']===null?'—':number_format($row['reliability'],1).'%' }}</td>
            <td><span class="lab-verdict is-{{ $row['verdict_key'] }}">{{ $row['verdict'] }}</span><small><a class="lab-row-link" href="{{ route('admin.pulse.intelligence',['strategy'=>$row['slug'],'from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}">Details →</a></small></td>
        </tr>
    @empty<tr><td colspan="8"><div class="lab-empty-row">No strategy evidence yet. Run the engine and allow paper trades to resolve.</div></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="enterprise-surface lab-table-card" id="cycle-report">
    <div class="lab-section-head lab-table-title"><div><span class="lab-eyebrow">CYCLE REPORT</span><h2>What happened each time ABS ran?</h2><p>One row follows the cycle from fresh prices to scan result and paper outcome.</p></div><a class="lab-text-link" href="{{ route('admin.pulse.scan-audit') }}">Full audit →</a></div>
    <div class="lab-table-wrap"><table class="lab-table"><thead><tr><th>Cycle</th><th>Price update</th><th>Markets scanned</th><th>Signal</th><th>Paper result</th><th></th></tr></thead><tbody>
    @forelse($recentCycles as $row)
        @php($run=$row['model']) @php($signal=$row['signal'])
        <tr>
            <td><b>#{{ $run->id }}</b><small>{{ $run->started_at?->format('d M · H:i:s') ?? '—' }}</small></td>
            <td>@if($row['market_run_id'])<b>#{{ $row['market_run_id'] }}</b><small>{{ number_format($row['market_prices_updated']) }} prices</small>@else<b>—</b><small>older trace</small>@endif</td>
            <td><b>{{ number_format($row['markets']) }}</b><small>{{ number_format($row['evaluations']) }} evaluations</small></td>
            <td>@if($signal)<b>{{ $signal->symbol }}</b><small>{{ $signal->direction }} · {{ strtoupper($signal->timeframe) }}</small>@else<span class="lab-verdict is-collecting">No signal</span>@endif</td>
            <td><span class="lab-result is-{{ $outcomeClass($row['outcome']) }}">{{ $outcomeLabel($row['outcome']) }}</span></td>
            <td><a class="lab-row-link" href="{{ route('admin.pulse.scan-audit.show',$run) }}">Trace →</a></td>
        </tr>
    @empty<tr><td colspan="6"><div class="lab-empty-row">No system cycles recorded yet.</div></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="enterprise-surface lab-table-card" id="paper-trades">
    <div class="lab-section-head lab-table-title"><div><span class="lab-eyebrow">PAPER TRADE REGISTER</span><h2>Every research signal and its result</h2><p>These are market-validation results only. No real order or real-money P&amp;L is implied.</p></div><span class="lab-period-chip">{{ $from->format('d M') }} — {{ $to->format('d M') }}</span></div>
    <div class="lab-table-wrap"><table class="lab-table"><thead><tr><th>Generated</th><th>Market</th><th>Side</th><th>Entry</th><th>Result</th><th>Resolved</th><th></th></tr></thead><tbody>
    @forelse($recentPaperTrades as $trade)
        <tr>
            <td>{{ $trade->generated_at?->format('d M · H:i:s') ?? '—' }}</td>
            <td><b>{{ $trade->symbol }}</b><small>{{ strtoupper($trade->timeframe) }}</small></td>
            <td><span class="lab-side is-{{ strtolower($trade->direction) }}">{{ $trade->direction }}</span></td>
            <td>{{ $trade->entry_hit_at ? 'Observed' : 'Waiting' }}<small>{{ $trade->entry_hit_at?->format('d M · H:i:s') ?? '—' }}</small></td>
            <td><span class="lab-result is-{{ $outcomeClass($trade->outcome) }}">{{ $outcomeLabel($trade->outcome) }}</span></td>
            <td>{{ $trade->resolved_at?->format('d M · H:i:s') ?? 'Open' }}</td>
            <td>@if($trade->signal?->scanner_run_id)<a class="lab-row-link" href="{{ route('admin.pulse.scan-audit.show',$trade->signal->scanner_run_id) }}">Trace →</a>@endif</td>
        </tr>
    @empty<tr><td colspan="7"><div class="lab-empty-row">No paper signals in this period yet.</div></td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="lab-future-banner">
    <div><span class="lab-eyebrow">FUTURE LIVE EXECUTION</span><b>Binance automation stays separate until the paper evidence is proven.</b><small>{{ number_format((int)($actual['closed_trades'] ?? 0)) }} real exchange trades currently recorded · {{ number_format((float)($actual['realized_pnl'] ?? 0),4) }} realized P&amp;L</small></div>
    <a href="{{ route('admin.pulse.settings') }}">Safety controls →</a>
</section>
@endsection

@push('scripts')
<script>window.ABS_STRATEGY_TREND = @json($dailyTrend->values());</script>
<script src="{{ asset('assets/js/admin-strategy-lab-v1530.js') }}?v={{ @filemtime(public_path('assets/js/admin-strategy-lab-v1530.js')) ?: '15.3.0' }}" defer></script>
@endpush
