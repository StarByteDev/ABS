@extends('admin.layout')
@section('title','Strategy Intelligence — ABS Admin')
@section('heading','Strategy Intelligence')
@section('description','Clear strategy success, TP evidence, learning score and virtual $100 paper P/L.')
@section('content')
@include('admin.pulse.workflow.step-head',['step'=>7,'title'=>'Strategy Intelligence','subtitle'=>'Compare every strategy using the same $100 virtual position size, while keeping TP milestones, final outcomes and the learning score separate and auditable.'])
@php
    $money = static fn($value) => (($value ?? 0) >= 0 ? '+' : '-').'$'.number_format(abs((float)($value ?? 0)), 2);
    $pct = static fn($value) => $value === null ? '—' : (($value >= 0 ? '+' : '').number_format((float)$value, 2).'%');
@endphp
<div class="wf-range-tabs wf-top-tabs">
    <a class="{{ $range==='1d'?'active':'' }}" href="?range=1d">Today</a>
    <a class="{{ $range==='7d'?'active':'' }}" href="?range=7d">7D</a>
    <a class="{{ $range==='30d'?'active':'' }}" href="?range=30d">30D</a>
    <a class="{{ $range==='90d'?'active':'' }}" href="?range=90d">90D</a>
</div>

<section class="wf-result-hero wf-result-hero-six">
    <article><span>RESOLVED TRADES</span><strong>{{ number_format($portfolio['virtual_resolved_trades']) }}</strong><small>Final TP + SL only</small></article>
    <article><span>VIRTUAL CAPITAL TESTED</span><strong>${{ number_format($portfolio['virtual_tested_notional_usd'], 2) }}</strong><small>${{ number_format($portfolio['virtual_notional_usd'],0) }} per resolved position</small></article>
    <article class="good"><span>GROSS PROFIT</span><strong>+${{ number_format($portfolio['virtual_gross_profit_usd'], 2) }}</strong><small>Winning resolved trades</small></article>
    <article class="bad"><span>GROSS LOSS</span><strong>-${{ number_format($portfolio['virtual_gross_loss_usd'], 2) }}</strong><small>Losing resolved trades</small></article>
    <article class="{{ $portfolio['virtual_net_pnl_usd']>=0?'good':'bad' }}"><span>NET VIRTUAL P/L</span><strong>{{ $money($portfolio['virtual_net_pnl_usd']) }}</strong><small>Unique trades — do not sum strategy rows</small></article>
    <article class="{{ ($portfolio['virtual_roi_pct']??0)>=0?'good':'bad' }}"><span>VIRTUAL ROI</span><strong>{{ $pct($portfolio['virtual_roi_pct']) }}</strong><small>Net P/L ÷ total tested notional</small></article>
</section>

<section class="wf-mini-kpis">
    <article><span>Strategies tracked</span><b>{{ number_format($rows->count()) }}</b></article>
    <article><span>Positive virtual P/L</span><b class="good">{{ number_format($positive) }}</b></article>
    <article><span>Needs research</span><b class="{{ $review?'bad':'' }}">{{ number_format($review) }}</b></article>
    <article><span>Best current P/L evidence</span><b>{{ $top['name'] ?? 'Collecting' }}</b></article>
</section>

<section class="wf-inline-note">
    <span class="wf-note-icon">$</span>
    <div><b>How the $100 model works</b><small>Every final TP or SL is replayed as an unleveraged $100 position using the actual Entry → final TP/SL price move. Open, expired and ambiguous trades are not treated as realized profit/loss. No leverage, fees, funding, slippage or compounding is included.</small></div>
    <a href="{{ route('admin.pulse.trade-results',['range'=>$range]) }}">View every trade →</a>
</section>
<section class="wf-inline-note">
    <span class="wf-note-icon">◎</span>
    <div><b>Strategy attribution is diagnostic</b><small>One signal can contain several contributing strategies. Each strategy receives that trade's virtual result for attribution, so strategy P/L rows can overlap. The overall Net Virtual P/L above is calculated once per unique trade and is the figure to use for total system performance.</small></div>
</section>
<section class="wf-inline-note wf-adaptive-note">
    <span class="wf-note-icon">↯</span>
    <div><b>Adaptive loss control is active for member signals</b><small>Pulse keeps raw system research running, but mature contributors with weak expectancy are automatically throttled. The live/member scanner can tune the final R:R within a conservative range and requires directional confluence before a setup can qualify. This is an evidence-control mechanism, not a profit guarantee.</small></div>
</section>
<section class="wf-inline-note"><span class="wf-note-icon">⚖</span><div><b>Automatic learning guardrails</b><small>Resolved TP/SL updates learned evidence on the next validation cycle. Mature strategies with negative outcomes are downweighted; research-only contributors cannot increase the member signal score. Recent 14-day losses reduce, never boost, signal confidence. Changes are evidence-based heuristics, not a trained AI model or profitability guarantee.</small></div></section>

@php
    $backfillActive = in_array((string)($backfillState['status'] ?? 'idle'), ['queued','running'], true);
    $backfillStatus = strtoupper(str_replace('_',' ',(string)($backfillState['status'] ?? 'idle')));
    $backfillPct = (float)($backfillState['progress_pct'] ?? 0);
@endphp
<section class="wf-panel wf-maintenance-panel" id="analytics-backfill-card" data-active="{{ $backfillActive ? '1' : '0' }}" data-status-url="{{ route('admin.pulse.strategy-performance.backfill.status') }}">
    <div class="wf-panel-head">
        <div><span class="wf-kicker">SHARED-HOSTING MAINTENANCE</span><h2>Strategy analytics backfill</h2></div>
        <span id="backfill-status-pill" class="wf-pill {{ $backfillActive ? 'is-warn' : (($backfillState['status'] ?? '')==='completed' ? 'is-good' : (($backfillState['status'] ?? '')==='failed' ? 'is-bad' : 'is-neutral')) }}">{{ $backfillStatus }}</span>
    </div>
    <div class="wf-maintenance-grid">
        <div class="wf-maintenance-copy">
            <b>No SSH or cron changes required</b>
            <small>Choose a retained history window and queue the rebuild here. ABS processes a few dates at a time through the existing <code>schedule:run</code> maintenance cycle, then refreshes learning and adaptive R:R evidence once at the end.</small>
            <div class="wf-progress-track" aria-label="Backfill progress"><span id="backfill-progress-bar" style="width:{{ max(0,min(100,$backfillPct)) }}%"></span></div>
            <div class="wf-progress-meta">
                <span id="backfill-message">{{ $backfillState['message'] ?? 'No analytics backfill is currently queued.' }}</span>
                <b id="backfill-progress-text">{{ number_format($backfillPct,1) }}% · {{ number_format((int)($backfillState['processed_dates'] ?? 0)) }}/{{ number_format((int)($backfillState['total_dates'] ?? 0)) }} dates</b>
            </div>
            @if(!empty($backfillState['current_date']))
                <small id="backfill-current">Currently rebuilding: {{ $backfillState['current_date'] }}</small>
            @else
                <small id="backfill-current"></small>
            @endif
            @if(!empty($backfillState['last_error']))
                <div class="wf-maintenance-error" id="backfill-error">{{ $backfillState['last_error'] }}</div>
            @else
                <div class="wf-maintenance-error" id="backfill-error" hidden></div>
            @endif
        </div>
        <div class="wf-maintenance-actions">
            <form method="POST" action="{{ route('admin.pulse.strategy-performance.backfill') }}">
                @csrf
                <label for="analytics-backfill-days">History window</label>
                <select id="analytics-backfill-days" name="days" {{ $backfillActive ? 'disabled' : '' }}>
                    <option value="7" {{ (int)($backfillState['days'] ?? 30)===7?'selected':'' }}>Last 7 days</option>
                    <option value="30" {{ (int)($backfillState['days'] ?? 30)===30?'selected':'' }}>Last 30 days</option>
                    <option value="90" {{ (int)($backfillState['days'] ?? 30)===90?'selected':'' }}>Last 90 days</option>
                </select>
                <button type="submit" class="wf-primary-action" {{ $backfillActive ? 'disabled' : '' }}>Queue Analytics Backfill</button>
            </form>
            @if($backfillActive)
                <form method="POST" action="{{ route('admin.pulse.strategy-performance.backfill.cancel') }}">
                    @csrf
                    <button type="submit" class="wf-secondary-action">Cancel queued work</button>
                </form>
            @endif
            <small>Recommended after a strategy-engine upgrade: <b>30 days</b>. Historical trades are never deleted or rewritten.</small>
        </div>
    </div>
</section>

<section class="wf-panel wf-table-panel">
    <div class="wf-panel-head">
        <div><span class="wf-kicker">STRATEGY PERFORMANCE REGISTER</span><h2>Success rate + TP progress + money result</h2></div>
        <div class="wf-report-actions"><a href="{{ route('admin.pulse.strategy-performance.export',['range'=>$range]) }}">Export P/L CSV ↓</a> <a href="{{ route('admin.pulse.strategies') }}">Configure strategies & R:R →</a></div>
    </div>
    <div class="wf-table-wrap">
        <table class="wf-table">
            <thead><tr><th>#</th><th>Strategy / Role</th><th>Signals / Entries</th><th>TP milestones</th><th>Final TP / SL</th><th>Success</th><th>$100 virtual P/L</th><th>Virtual ROI</th><th>Net R</th><th>Adaptive R:R</th><th>Adaptive Weight</th><th>Learning</th><th>State</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $adaptiveState = $row['adaptive_status'] ?? 'collecting';
                    $assessment = $adaptiveState==='research_only' ? 'Research only' : ($adaptiveState==='throttled' ? 'Throttled' : ($row['decisive']<20 ? 'Collecting' : ($row['virtual_net_pnl_usd']>0 ? 'Positive' : 'Research')));
                    $stateClass = in_array($assessment,['Positive'],true) ? 'good' : (in_array($assessment,['Research','Throttled','Research only'],true) ? 'warn' : 'neutral');
                @endphp
                <tr>
                    <td><span class="wf-rank">{{ $loop->iteration }}</span></td>
                    <td><b>{{ $row['name'] }}</b><small>{{ $row['category'] ?? 'Strategy' }} · {{ $row['enabled']?'Enabled':'Disabled' }} · {{ number_format($row['samples']) }} learned samples</small></td>
                    <td><b>{{ number_format($row['signals']) }} / {{ number_format($row['entries']) }}</b><small>{{ number_format($row['decisive']) }} resolved TP/SL</small></td>
                    <td><b class="good">{{ number_format($row['tp1']) }} / {{ number_format($row['tp2']) }} / {{ number_format($row['tp3']) }}</b><small>TP1 / TP2 / TP3+</small></td>
                    <td><span class="good">{{ number_format($row['tp']) }}</span> / <span class="bad">{{ number_format($row['sl']) }}</span></td>
                    <td><b>{{ $row['win_rate']===null?'—':number_format($row['win_rate'],1).'%' }}</b><small>Final TP ÷ (TP + SL)</small></td>
                    <td><b class="{{ $row['virtual_net_pnl_usd']>=0?'good':'bad' }}">{{ $money($row['virtual_net_pnl_usd']) }}</b><small>Profit +${{ number_format($row['virtual_gross_profit_usd'],2) }} · Loss -${{ number_format($row['virtual_gross_loss_usd'],2) }}</small></td>
                    <td><b class="{{ ($row['virtual_roi_pct']??0)>=0?'good':'bad' }}">{{ $pct($row['virtual_roi_pct']) }}</b><small>${{ number_format($row['virtual_tested_notional_usd'],0) }} tested</small></td>
                    <td><b class="{{ $row['net_r']>=0?'good':'bad' }}">{{ ($row['net_r']>=0?'+':'').number_format($row['net_r'],2) }}R</b></td>
                    <td><b>{{ number_format((float)($row['adaptive_rr'] ?? $row['configured_rr'] ?? 2),2) }}R</b><small>Configured {{ number_format((float)($row['configured_rr'] ?? 2),2) }}R@if($row['adaptive_expected_r']!==null) · tested exp {{ ($row['adaptive_expected_r']>=0?'+':'').number_format((float)$row['adaptive_expected_r'],2) }}R@endif</small><small>Observed exp: {{ data_get($row,'adaptive.observed_expectancy_r')===null?'Insufficient data':number_format((float)data_get($row,'adaptive.observed_expectancy_r'),2).'R' }}</small></td>
                    <td><b>{{ number_format(((float)($row['adaptive_weight_multiplier'] ?? 1))*100,0) }}%</b><small>{{ ($row['member_eligible'] ?? true) ? 'Eligible for member scoring' : 'Research-only evidence' }}</small><small>Recent 14D expectancy: {{ data_get($row,'adaptive.recent_expectancy_r')===null ? 'Insufficient data' : number_format((float)data_get($row,'adaptive.recent_expectancy_r'),2).'R' }}</small></td>
                    <td><b>{{ $row['reliability']===null?'50.0%':number_format($row['reliability'],1).'%' }}</b><small>25% of future confidence</small></td>
                    <td><span class="wf-pill is-{{ $stateClass }}">{{ strtoupper($assessment) }}</span></td>
                    <td><a class="wf-row-link" href="{{ route('admin.pulse.trade-results',['range'=>$range,'strategy'=>$row['slug']]) }}">Trades →</a></td>
                </tr>
            @empty
                <tr><td colspan="14"><div class="wf-empty">No strategy evidence yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@push('scripts')
<script>
(() => {
    const card = document.getElementById('analytics-backfill-card');
    if (!card || card.dataset.active !== '1') return;
    const url = card.dataset.statusUrl;
    const bar = document.getElementById('backfill-progress-bar');
    const text = document.getElementById('backfill-progress-text');
    const message = document.getElementById('backfill-message');
    const current = document.getElementById('backfill-current');
    const pill = document.getElementById('backfill-status-pill');
    const error = document.getElementById('backfill-error');
    let lastStatus = null;

    const poll = async () => {
        try {
            const response = await fetch(url, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
            if (!response.ok) return;
            const payload = await response.json();
            const job = payload.job || {};
            const pct = Number(job.progress_pct || 0);
            if (bar) bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
            if (text) text.textContent = pct.toFixed(1) + '% · ' + Number(job.processed_dates || 0).toLocaleString() + '/' + Number(job.total_dates || 0).toLocaleString() + ' dates';
            if (message) message.textContent = job.message || '';
            if (current) current.textContent = job.current_date ? ('Currently rebuilding: ' + job.current_date) : '';
            if (pill) pill.textContent = String(job.status || 'idle').replaceAll('_',' ').toUpperCase();
            if (error) {
                error.hidden = !job.last_error;
                error.textContent = job.last_error || '';
            }
            const status = String(job.status || 'idle');
            if (lastStatus && ['completed','failed','cancelled'].includes(status) && status !== lastStatus) {
                window.setTimeout(() => window.location.reload(), 1200);
                return;
            }
            lastStatus = status;
            if (['queued','running'].includes(status)) window.setTimeout(poll, 12000);
        } catch (_) {
            window.setTimeout(poll, 20000);
        }
    };
    poll();
})();
</script>
@endpush
@endsection
