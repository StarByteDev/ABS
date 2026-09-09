@extends('admin.layout')
@section('title','Pulse Execution & Risk Register')
@section('heading','Pulse Execution & Risk Register')
@section('description','Customer-requested execution records, practice/live separation, protection confirmation and exchange-reported financial outcomes.')
@section('content')
@php
    $price = static function ($value): string {
        if ($value === null) return '—';
        $number=abs((float)$value); $precision=$number>=1000?2:($number>=1?4:($number>=.01?6:8));
        return rtrim(rtrim(number_format((float)$value,$precision,'.',','),'0'),'.');
    };
    $queryWithoutRange=request()->except(['page','from','to']);
    $selectedLabel=$from->format('d M Y').' — '.$to->format('d M Y');
@endphp

<section class="enterprise-command-bar compact admin-report-command">
    <div><span class="admin-report-eyebrow">EXECUTION OVERSIGHT · RISK PROTECTION</span><h2>See what customers requested, what Binance reported and whether protection is confirmed</h2><p>Practice and live execution stay visibly separated. Financial fields are exchange-recorded values in each trade’s commission context; they are not presented as audited portfolio returns.</p></div>
    <div class="enterprise-command-actions"><a class="button button-primary" href="{{ route('admin.pulse.signals') }}">Signal Oversight</a><a class="button button-ghost" href="{{ route('admin.market-data') }}">Feed & Cron Health</a></div>
</section>

<section class="enterprise-filter-surface report-toolbar">
    <div class="filter-surface-head"><div><h2>Date range and execution filters</h2><p>Every KPI, chart and register row below uses these exact filters.</p></div><nav class="admin-range-tabs" aria-label="Quick date ranges"><a href="{{ route('admin.pulse.trades',array_merge($queryWithoutRange,['from'=>now()->subDay()->toDateString(),'to'=>today()->toDateString()])) }}">24H</a><a href="{{ route('admin.pulse.trades',array_merge($queryWithoutRange,['from'=>now()->subDays(6)->toDateString(),'to'=>today()->toDateString()])) }}">7D</a><a href="{{ route('admin.pulse.trades',array_merge($queryWithoutRange,['from'=>now()->subDays(29)->toDateString(),'to'=>today()->toDateString()])) }}">30D</a><a href="{{ route('admin.pulse.trades',array_merge($queryWithoutRange,['from'=>now()->subDays(89)->toDateString(),'to'=>today()->toDateString()])) }}">90D</a></nav></div>
    <form method="GET" class="enterprise-filter-grid trade-filter-grid"><label>From date<input type="date" name="from" value="{{ $from->toDateString() }}"></label><label>To date<input type="date" name="to" value="{{ $to->toDateString() }}"></label><label>Customer<input name="user" value="{{ $filters['user'] }}" placeholder="Name or email"></label><label>Market<input name="symbol" value="{{ $filters['symbol'] }}" placeholder="BTCUSDT"></label><label>Lifecycle<select name="status"><option value="">All lifecycle states</option>@foreach(['submitting','pending','open','protection_failed','closing','closed','failed','cancelled'] as $status)<option value="{{ $status }}" @selected($filters['status']===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label><label>Execution environment<select name="environment"><option value="">Practice + live</option><option value="testnet" @selected($filters['environment']==='testnet')>Practice (testnet)</option><option value="live" @selected($filters['environment']==='live')>Live</option></select></label><div class="filter-actions full"><button class="button button-primary">Apply Report</button><a class="button button-ghost" href="{{ route('admin.pulse.trades') }}">Reset to 30 Days</a></div></form>
</section>

<section class="admin-purpose-card">
    <div><span class="purpose-label">WHAT THIS REPORT PROVES</span><h2>Execution and signal performance are different controls</h2><p>Signal Oversight proves market-intelligence outcomes. This register proves customer-authorized execution state, order references, synchronization and TP/SL protection. A profitable signal is not counted here unless a trade record exists.</p></div>
    <div class="admin-purpose-list"><div><i>1</i><span>Separate practice from live activity</span></div><div><i>2</i><span>Escalate missing or failed TP/SL protection</span></div><div><i>3</i><span>Trace every exchange order reference</span></div></div>
</section>

<section class="admin-report-kpis trade-kpis" aria-label="Filtered execution indicators">
    <article><span class="report-kpi-icon blue">Σ</span><div><small>Execution records</small><strong>{{ number_format($summary['total']) }}</strong><em>{{ $selectedLabel }}</em></div></article>
    <article><span class="report-kpi-icon cyan">↗</span><div><small>Open activity</small><strong>{{ number_format($summary['open']) }}</strong><em>Submitting, pending, open or closing</em></div></article>
    <article class="{{ $summary['protection_review']?'attention':'' }}"><span class="report-kpi-icon red">!</span><div><small>Protection attention</small><strong>{{ number_format($summary['protection_review']) }}</strong><em>{{ $summary['protection_rate']===null?'No open protection scope':number_format($summary['protection_rate'],1).'% confirmed' }}</em></div></article>
    <article><span class="report-kpi-icon green">%</span><div><small>Profitable close rate</small><strong>{{ $summary['profitable_rate']===null?'—':number_format($summary['profitable_rate'],1).'%' }}</strong><em>{{ number_format($summary['closed']) }} closed records</em></div></article>
    <article class="featured"><span class="report-kpi-icon gold">R</span><div><small>Recorded realized P&amp;L</small><strong>{{ number_format($summary['realized_pnl'],2) }}</strong><em>Fees recorded separately: {{ number_format($summary['fees'],2) }}</em></div></article>
    <article><span class="report-kpi-icon violet">≈</span><div><small>Open unrealized P&amp;L</small><strong>{{ number_format($summary['unrealized_pnl'],2) }}</strong><em>Latest stored exchange snapshots</em></div></article>
</section>

<script type="application/json" id="trade-trend">{!! json_encode([
    'ariaLabel'=>'Daily execution records, closed records and profitable closes for selected filters','labels'=>$tradeTrend->pluck('label')->all(),
    'series'=>[
        ['name'=>'Execution records','color'=>'#2788d1','values'=>$tradeTrend->pluck('trades')->all()],
        ['name'=>'Closed','color'=>'#25b8cb','values'=>$tradeTrend->pluck('closed')->all()],
        ['name'=>'Profitable closes','color'=>'#179b6b','values'=>$tradeTrend->pluck('profitable')->all()],
    ],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="trade-outcomes">{!! json_encode([
    'ariaLabel'=>'Execution outcome composition for selected filters','centerLabel'=>'TRADES',
    'labels'=>['Profitable close','Losing close','Flat close','Open / processing'],
    'values'=>[$summary['profitable'],$summary['losing'],$summary['flat'],$summary['open']],
    'colors'=>['#179b6b','#d54d5b','#8292a8','#2788d1'],
], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>

<section class="admin-chart-grid">
    <article class="admin-chart-card"><div class="admin-chart-head"><div><h2>Execution activity by day</h2><p>Daily records, completed records and profitable closes for the selected report.</p></div><span class="report-period-chip">{{ strtoupper($selectedLabel) }}</span></div><div class="admin-chart" data-admin-chart data-chart-source="#trade-trend" data-chart-type="bar"></div></article>
    <article class="admin-chart-card"><div class="admin-chart-head"><div><h2>Closed and open composition</h2><p>Profitability uses closed records only; open processing stays separate.</p></div></div><div class="admin-chart" data-admin-chart data-chart-source="#trade-outcomes" data-chart-type="donut"></div></article>
</section>

<section class="enterprise-surface admin-insight-band">
    <div><span class="insight-dot info"></span><p><b>{{ number_format($environmentMix['practice']) }} practice records</b><small>Binance testnet activity has no live-capital implication.</small></p></div>
    <div><span class="insight-dot {{ $environmentMix['live']?'warn':'good' }}"></span><p><b>{{ number_format($environmentMix['live']) }} live records</b><small>Live execution remains subject to platform, package and user gates.</small></p></div>
    <div><span class="insight-dot {{ $summary['protection_review']?'warn':'good' }}"></span><p><b>{{ $summary['protection_review']?'Protection review required':'No protection exception in view' }}</b><small>Open trades should have exchange-confirmed take-profit and stop-loss protection.</small></p></div>
</section>

<section class="enterprise-surface no-pad" id="trade-register">
    <div class="enterprise-section-head padded"><div><h2>Execution register</h2><p>{{ number_format($trades->total()) }} matching customer execution records · newest first</p></div><div class="enterprise-command-actions"><button type="button" class="button button-ghost button-small" data-table-density="#trade-register" aria-pressed="false">Compact rows</button><span class="report-period-chip">{{ $from->format('d M') }} — {{ $to->format('d M Y') }}</span></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table admin-trade-table"><thead><tr><th>Created / customer</th><th>Market / instruction</th><th>Environment</th><th>Execution lifecycle</th><th>Protection</th><th>Exchange-recorded result</th><th>Synchronization / evidence</th></tr></thead><tbody>
    @forelse($trades as $trade)
        <tr>
            <td><b>{{ $trade->created_at?->format('d M Y · H:i') }}</b>@if($trade->user)<a class="table-user-link" href="{{ route('admin.users.show',$trade->user) }}">{{ $trade->user->name }}</a><small>{{ $trade->user->email }}</small>@endif</td>
            <td><b class="market-symbol">{{ $trade->symbol }}</b><span class="signal-direction {{ strtolower($trade->side) }}">{{ ucfirst(strtolower($trade->side)) }}</span><small>{{ $trade->leverage }}× leverage · {{ ucwords(str_replace('_',' ',$trade->order_type)) }}</small><small>Entry {{ $price($trade->entry_price) }} · quantity {{ $price($trade->quantity) }}</small></td>
            <td><span class="admin-status {{ $trade->environment==='live'?'warn':'info' }}">{{ $trade->environment==='live'?'LIVE':'PRACTICE' }}</span><small>{{ $trade->environment==='live'?'Real exchange environment':'Binance testnet environment' }}</small></td>
            <td><span class="admin-status {{ $trade->status==='closed'?'good':(in_array($trade->status,['failed','cancelled'])?'danger':'info') }}">{{ ucwords(str_replace('_',' ',$trade->status)) }}</span><small>{{ $trade->close_reason?ucwords(str_replace('_',' ',$trade->close_reason)):'No close reason recorded' }}</small><small>{{ $trade->closed_at?'Closed '.$trade->closed_at->format('d M · H:i'):($trade->opened_at?'Opened '.$trade->opened_at->format('d M · H:i'):'Not opened') }}</small></td>
            <td><span class="admin-status {{ $trade->protection_status==='confirmed'?'good':(in_array($trade->status,['open','protection_failed'])?'danger':'muted') }}">{{ ucwords(str_replace('_',' ',$trade->protection_status)) }}</span><div class="signal-levels"><span class="tp"><small>TAKE PROFIT</small><b>{{ $price($trade->take_profit) }}</b></span><span class="sl"><small>STOP LOSS</small><b>{{ $price($trade->stop_loss) }}</b></span></div></td>
            <td><b class="{{ (float)$trade->realized_pnl>=0?'positive-text':'danger-text' }}">{{ number_format((float)$trade->realized_pnl,4) }} realized</b><small>{{ number_format((float)$trade->unrealized_pnl,4) }} unrealized</small><small>{{ number_format((float)$trade->fees,4) }} fees · {{ $trade->commission_asset ?: 'asset not recorded' }}</small></td>
            <td><b>{{ $trade->last_synced_at?->format('d M · H:i:s') ?? 'Never synchronized' }}</b><small>{{ $trade->last_synced_at?->diffForHumans() ?? 'No exchange snapshot' }}</small><details class="admin-reference-list"><summary>Exchange references</summary><small>Entry {{ $trade->exchange_order_id ?: '—' }}<br>TP {{ $trade->exchange_tp_order_id ?: '—' }}<br>SL {{ $trade->exchange_sl_order_id ?: '—' }}<br>Close {{ $trade->exchange_close_order_id ?: '—' }}</small></details></td>
        </tr>
    @empty<tr><td colspan="7"><div class="admin-empty-report"><span>⇄</span><h3>No execution records match this report</h3><p>Change the date range or reset the filters.</p></div></td></tr>@endforelse
    </tbody></table></div>
    <div class="enterprise-pagination">{{ $trades->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
</section>
@endsection
