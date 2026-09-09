@extends('admin.layout')
@section('title','Pulse Signal Oversight')
@section('heading','Pulse Signal Oversight')
@section('description','A readable register of every generated signal, customer unlock, market level and independently validated outcome.')
@section('content')
@php
    $price = static function ($value): string {
        if ($value === null) return '—';
        $number = abs((float) $value);
        $precision = $number >= 1000 ? 2 : ($number >= 1 ? 4 : ($number >= .01 ? 6 : 8));
        return rtrim(rtrim(number_format((float)$value, $precision, '.', ','), '0'), '.');
    };
    $queryWithoutRange = request()->except(['page','from','to']);
    $outcomeLabels = ['tp'=>'Target reached','sl'=>'Risk limit reached','ambiguous'=>'Ambiguous','expired_no_entry'=>'Expired before entry','expired_after_entry'=>'Expired after entry'];
    $selectedLabel = $from->format('d M Y').' — '.$to->format('d M Y');
@endphp

<section class="enterprise-command-bar compact admin-report-command">
    <div><span class="admin-report-eyebrow">SIGNAL GOVERNANCE · CUSTOMER AND MARKET EVIDENCE</span><h2>Understand the complete lifecycle of every Pulse signal</h2><p>This page answers what Pulse generated, whether the market reached entry, what result was proven, which customer unlocked it and whether an administrator changed its lifecycle status.</p></div>
    <div class="enterprise-command-actions"><a class="button button-primary" href="{{ route('admin.pulse.intelligence',['from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}">Strategy Intelligence</a><a class="button button-ghost" href="{{ route('admin.market-data') }}">Feed & Cron Health</a></div>
</section>

<section class="enterprise-filter-surface report-toolbar">
    <div class="filter-surface-head"><div><h2>Date range and report filters</h2><p>The selected dates apply to every number, chart and row on this page.</p></div><nav class="admin-range-tabs" aria-label="Quick date ranges"><a href="{{ route('admin.pulse.signals',array_merge($queryWithoutRange,['from'=>now()->subDay()->toDateString(),'to'=>today()->toDateString()])) }}">24H</a><a href="{{ route('admin.pulse.signals',array_merge($queryWithoutRange,['from'=>now()->subDays(6)->toDateString(),'to'=>today()->toDateString()])) }}">7D</a><a href="{{ route('admin.pulse.signals',array_merge($queryWithoutRange,['from'=>now()->subDays(29)->toDateString(),'to'=>today()->toDateString()])) }}">30D</a><a href="{{ route('admin.pulse.signals',array_merge($queryWithoutRange,['from'=>now()->subDays(89)->toDateString(),'to'=>today()->toDateString()])) }}">90D</a></nav></div>
    <form method="GET" class="enterprise-filter-grid signal-report-filters">
        <label>From date<input type="date" name="from" value="{{ $from->toDateString() }}"></label><label>To date<input type="date" name="to" value="{{ $to->toDateString() }}"></label><label>Customer<input name="user" value="{{ $filters['user'] }}" placeholder="Name or email"></label><label>Market<input name="symbol" value="{{ $filters['symbol'] }}" placeholder="BTCUSDT"></label>
        <label>Timeframe<select name="timeframe"><option value="">All timeframes</option><option value="15m" @selected($filters['timeframe']==='15m')>15 minute</option><option value="4h" @selected($filters['timeframe']==='4h')>4 hour</option></select></label><label>Direction<select name="direction"><option value="">All directions</option>@foreach(['LONG','SHORT','NEUTRAL'] as $direction)<option value="{{ $direction }}" @selected($filters['direction']===$direction)>{{ ucfirst(strtolower($direction)) }}</option>@endforeach</select></label><label>Lifecycle<select name="status"><option value="">All states</option>@foreach(['active','executed','expired','rejected'] as $status)<option value="{{ $status }}" @selected($filters['status']===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><label>Validated result<select name="outcome"><option value="">All results</option><option value="pending" @selected($filters['outcome']==='pending')>Pending validation</option>@foreach($outcomeLabels as $value=>$label)<option value="{{ $value }}" @selected($filters['outcome']===$value)>{{ $label }}</option>@endforeach</select></label>
        <div class="filter-actions full"><button class="button button-primary">Apply Report</button><a class="button button-ghost" href="{{ route('admin.pulse.signals') }}">Reset to 30 Days</a></div>
    </form>
</section>

<section class="admin-purpose-card">
    <div><span class="purpose-label">PURPOSE OF SIGNAL OVERSIGHT</span><h2>One auditable place from opportunity to final result</h2><p>Use Signal Oversight to verify customer delivery and the integrity of Pulse reporting. It is not the strategy comparison page: open Strategy Intelligence when you need performance and confidence changes grouped by strategy.</p></div>
    <div class="admin-purpose-list"><div><i>1</i><span>Confirm a signal was generated and unlocked</span></div><div><i>2</i><span>Verify entry occurred before a result</span></div><div><i>3</i><span>Review exceptions without changing evidence</span></div></div>
</section>

<section class="admin-report-kpis signal-kpi-grid" aria-label="Signal results for selected period">
    <article><span class="report-kpi-icon blue">Σ</span><div><small>Signals generated</small><strong>{{ number_format($summary['total']) }}</strong><em>{{ number_format($summary['markets']) }} markets · {{ $selectedLabel }}</em></div></article>
    <article><span class="report-kpi-icon cyan">↗</span><div><small>Entries observed</small><strong>{{ number_format($summary['entries']) }}</strong><em>{{ $summary['entry_rate']===null?'—':number_format($summary['entry_rate'],1).'%' }} of generated signals</em></div></article>
    <article><span class="report-kpi-icon green">TP</span><div><small>Target reached</small><strong>{{ number_format($summary['wins']) }}</strong><em>Validated after entry</em></div></article>
    <article><span class="report-kpi-icon red">SL</span><div><small>Risk limit reached</small><strong>{{ number_format($summary['losses']) }}</strong><em>Validated after entry</em></div></article>
    <article class="featured"><span class="report-kpi-icon gold">%</span><div><small>Decisive win rate</small><strong>{{ $summary['win_rate']===null?'—':number_format($summary['win_rate'],1).'%' }}</strong><em>TP ÷ (TP + SL)</em></div></article>
    <article class="{{ $summary['ambiguous']>0?'attention':'' }}"><span class="report-kpi-icon amber">?</span><div><small>Ambiguous results</small><strong>{{ number_format($summary['ambiguous']) }}</strong><em>Excluded from win rate</em></div></article>
</section>

<script type="application/json" id="signal-trend">{!! json_encode([
    'ariaLabel'=>'Daily signal generation, entries, target and risk outcomes for selected filters',
    'labels'=>$signalTrend->pluck('label')->all(),
    'series'=>[
        ['name'=>'Signals','color'=>'#2788d1','fillColor'=>'rgba(39,136,209,.10)','values'=>$signalTrend->pluck('signals')->all()],
        ['name'=>'Entries','color'=>'#25b8cb','fill'=>false,'values'=>$signalTrend->pluck('entries')->all()],
        ['name'=>'TP','color'=>'#179b6b','fill'=>false,'values'=>$signalTrend->pluck('wins')->all()],
        ['name'=>'SL','color'=>'#d54d5b','fill'=>false,'values'=>$signalTrend->pluck('losses')->all()],
    ],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="signal-outcomes">{!! json_encode([
    'ariaLabel'=>'Signal outcome distribution for selected filters','centerLabel'=>'SIGNALS',
    'labels'=>['Target reached','Risk limit reached','Ambiguous','Expired before entry','Expired after entry','Pending'],
    'values'=>[$summary['wins'],$summary['losses'],$summary['ambiguous'],$summary['expired_no_entry'],$summary['expired_after_entry'],$summary['pending']],
    'colors'=>['#179b6b','#d54d5b','#d68f24','#8292a8','#7457d9','#b9c4d0'],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>

<section class="admin-chart-grid">
    <article class="admin-chart-card"><div class="admin-chart-head"><div><h2>Signal activity and outcome trend</h2><p>The chart uses the same customer, market, date and status filters shown above.</p></div><span class="report-period-chip">{{ strtoupper($selectedLabel) }}</span></div><div class="admin-chart" data-admin-chart data-chart-source="#signal-trend" data-chart-type="line"></div></article>
    <article class="admin-chart-card"><div class="admin-chart-head"><div><h2>Lifecycle outcome mix</h2><p>Pending and ambiguous results remain visible instead of being counted as wins.</p></div></div><div class="admin-chart" data-admin-chart data-chart-source="#signal-outcomes" data-chart-type="donut"></div></article>
</section>

<section class="enterprise-surface admin-insight-band">
    <div><span class="insight-dot info"></span><p><b>Average confidence {{ $summary['average_confidence']===null?'—':number_format($summary['average_confidence'],1).'%' }}</b><small>Final confidence combines 75% technical score and 25% learned reliability.</small></p></div>
    <div><span class="insight-dot {{ $summary['pending']?'warn':'good' }}"></span><p><b>{{ number_format($summary['pending']) }} pending validation</b><small>{{ number_format($summary['active']) }} active signals are monitored by the shared one-minute process.</small></p></div>
    <div><span class="insight-dot good"></span><p><b>{{ number_format($summary['unlocked']) }} signals available</b><small>Best Signal access is included with active paid packages; public rewarded signals are tracked separately.</small></p></div>
</section>

<section class="enterprise-surface no-pad signal-report-table" id="signal-register">
    <div class="enterprise-section-head padded"><div><h2>Signal register</h2><p>{{ number_format($signals->total()) }} matching records · newest first · price precision adapted to the market</p></div><div class="enterprise-command-actions"><button type="button" class="button button-ghost button-small" data-table-density="#signal-register" aria-pressed="false">Compact rows</button><span class="report-period-chip">{{ $from->format('d M') }} — {{ $to->format('d M Y') }}</span></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table admin-signal-table"><thead><tr><th>Generated / customer</th><th>Market / direction</th><th>Confidence</th><th>Lifecycle</th><th>Validated result</th><th>Trade levels</th><th>Admin review</th></tr></thead><tbody>
    @forelse($signals as $signal)
        @php
            $validation=$signal->validation; $confidence=(float)($signal->confidence_score ?? $signal->score ?? 0); $outcome=$validation?->outcome;
            $outcomeClass=$outcome==='tp'?'good':($outcome==='sl'?'danger':($outcome==='ambiguous'?'warn':'muted'));
            $result=$outcome?($outcomeLabels[$outcome]??ucwords(str_replace('_',' ',$outcome))):($validation?->entry_hit_at?'Entry reached; result pending':'Waiting for entry');
        @endphp
        <tr>
            <td><b>{{ $signal->generated_at?->format('d M Y · H:i') ?? '—' }}</b>@if($signal->user)<a class="table-user-link" href="{{ route('admin.users.show',$signal->user) }}">{{ $signal->user->name }}</a><small>{{ $signal->user->email }}</small>@else<small>System / legacy signal</small>@endif<small>{{ $signal->unlocked_at?'Available to eligible access':'Generated by Pulse engine' }}</small></td>
            <td><b class="market-symbol">{{ $signal->symbol }}</b><span class="signal-direction {{ strtolower($signal->direction) }}">{{ ucfirst(strtolower($signal->direction)) }}</span><span class="admin-status neutral">{{ strtoupper($signal->timeframe) }}</span><small>Signal #{{ $signal->id }}</small></td>
            <td><div class="confidence-cell"><strong>{{ number_format($confidence,1) }}%</strong><span><i style="width:{{ min(100,max(0,$confidence)) }}%"></i></span><small>Technical {{ $signal->technical_score===null?'—':number_format((float)$signal->technical_score,1) }} · learned {{ $signal->reliability_score===null?'—':number_format((float)$signal->reliability_score,1) }}</small></div></td>
            <td><span class="admin-status {{ $signal->status==='active'?'good':($signal->status==='rejected'?'danger':'muted') }}">{{ ucfirst($signal->status) }}</span><small>Expires {{ $signal->expires_at?->format('d M · H:i') ?? 'not set' }}</small><small>{{ $signal->share_count }} shares</small></td>
            <td><span class="admin-status {{ $outcomeClass }}">{{ $result }}</span><small>{{ $validation?->resolved_at?->format('d M Y · H:i') ?? ($validation?->last_checked_at?'Checked '.$validation->last_checked_at->diffForHumans():'No validation record yet') }}</small>@if($validation?->entry_hit_at)<small>Entry observed {{ $validation->entry_hit_at->format('d M · H:i') }}</small>@endif</td>
            <td><div class="signal-levels"><span><small>ENTRY</small><b>{{ $price($signal->entry_price) }}</b></span><span class="tp"><small>TAKE PROFIT</small><b>{{ $price($signal->take_profit) }}</b></span><span class="sl"><small>STOP LOSS</small><b>{{ $price($signal->stop_loss) }}</b></span></div></td>
            <td><details class="admin-row-control"><summary>Review signal</summary><form method="POST" action="{{ route('admin.pulse.signals.update',$signal) }}">@csrf @method('PUT')<div class="admin-callout"><b>Administrative control only.</b> Validation evidence and outcome remain unchanged.</div><label>Lifecycle status<select name="status">@foreach(['active','executed','expired','rejected'] as $status)<option value="{{ $status }}" @selected($signal->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><label>Expiry time<input type="datetime-local" name="expires_at" value="{{ $signal->expires_at?->format('Y-m-d\TH:i') }}"></label><button class="button button-primary button-small">Save Lifecycle</button></form></details></td>
        </tr>
    @empty<tr><td colspan="7"><div class="admin-empty-report"><span>◎</span><h3>No signals match this report</h3><p>Change the date range or reset the filters to review the latest 30 days.</p></div></td></tr>@endforelse
    </tbody></table></div>
    <div class="enterprise-pagination">{{ $signals->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
</section>
@endsection
