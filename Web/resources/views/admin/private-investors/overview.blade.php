@extends('admin.layout')
@section('title','Private Investors — ABS Admin')
@section('heading','Private Investor Portfolio Overview')
@section('description','Consolidated Private Investor reporting in USD, with investor-level accounts retained in their assigned currencies.')
@section('page-actions')
<a class="pi-btn" href="{{ route('admin.private-investors.reports') }}">Portfolio Reports</a>
<a class="pi-btn" href="{{ route('admin.private-investors.activity') }}">Activity & Corrections</a>
<a class="pi-btn primary" href="{{ route('admin.users.create',['role'=>'private_investor']) }}">Add Private Investor</a>
@endsection
@section('content')
<section class="pi-shell pi-admin-command-v1567 pi-v1570 pi-v1571">
    <div class="pi-grid pi-admin-kpis-v1567">
        <article class="pi-stat pi-stat-primary"><small>Total Investor Funds</small><strong>USD {{ number_format($summary['net_contributions'],2) }}</strong><em>{{ number_format($summary['investors']) }} active investor accounts</em></article>
        <article class="pi-stat"><small>Reported Capital Value</small><strong>USD {{ number_format($summary['portfolio_value'],2) }}</strong><em>Ledger-based value after balance-affecting entries</em></article>
        <article class="pi-stat"><small>Profit Paid</small><strong class="pi-positive">USD {{ number_format($summary['profit_paid'],2) }}</strong><em>{{ number_format($summary['return_percent'],2) }}% cumulative paid/net performance</em></article>
        <article class="pi-stat"><small>Realized FX Gain / Loss</small><strong class="{{ $summary['realized_fx_gain_loss']>=0?'pi-positive':'pi-negative' }}">{{ $summary['realized_fx_gain_loss']>=0?'+':'' }}USD {{ number_format($summary['realized_fx_gain_loss'],2) }}</strong><em>Admin only · investor principal unaffected</em></article>
        <article class="pi-stat"><small>Current Month Target</small><strong>USD {{ number_format($summary['monthly_target'],2) }}</strong><em>Configured provisional target</em></article>
        <article class="pi-stat"><small>MTD Provisional</small><strong class="{{ $summary['mtd_provisional']>=0?'pi-positive':'pi-negative' }}">USD {{ number_format($summary['mtd_provisional'],2) }}</strong><em>Posted across current plans</em></article>
        <article class="pi-stat"><small>Action Queue</small><strong>{{ number_format($summary['open_requests'] + $summary['draft_entries'] + $summary['setup_required']) }}</strong><em>{{ $summary['setup_required'] }} setup · {{ $summary['open_requests'] }} requests · {{ $summary['draft_entries'] }} drafts · {{ $summary['fx_pending'] ?? 0 }} FX review</em></article>
    </div>

    <div class="pi-report-grid pi-report-grid-main">
        <article class="pi-card pi-card-large">
            <div class="pi-card-head"><div><h2>Managed Portfolio Trend</h2><span>Combined automatically reconciled month-end capital values in USD</span></div><a class="pi-link" href="{{ route('admin.private-investors.reports') }}">Open full report →</a></div>
            @if($performance->count())
                <div class="pi-chart pi-chart-premium"><svg viewBox="0 0 100 90" preserveAspectRatio="none" role="img" aria-label="Combined investor portfolio trend"><defs><linearGradient id="piAdminArea1567" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#78c9ee" stop-opacity=".30"/><stop offset="1" stop-color="#78c9ee" stop-opacity="0"/></linearGradient></defs><line class="axis" x1="4" y1="20" x2="96" y2="20"/><line class="axis" x1="4" y1="50" x2="96" y2="50"/><line class="axis" x1="4" y1="84" x2="96" y2="84"/><polyline class="line" points="{{ $performanceChart['points'] }}"/></svg><div class="pi-chart-labels">@foreach($performanceChart['labels'] as $label)<span>{{ $label }}</span>@endforeach</div></div>
                <div class="pi-report-mini-row"><span><small>Latest combined value</small><b>USD {{ number_format((float)($performance->last()->closing_balance ?? 0),2) }}</b></span><span><small>Latest profit / loss</small><b class="{{ (float)($performance->last()->profit_loss ?? 0)>=0?'pi-positive':'pi-negative' }}">USD {{ number_format((float)($performance->last()->profit_loss ?? 0),2) }}</b></span><span><small>Published statements</small><b>{{ number_format($summary['published_statements']) }}</b></span></div>
            @else<div class="pi-empty">Portfolio trend builds automatically as completed months are reconciled.</div>@endif
        </article>

        <article class="pi-card">
            <div class="pi-card-head"><div><h2>Portfolio Allocation</h2><span>Largest reported investor values</span></div><a class="pi-link" href="{{ route('admin.private-investors.investors') }}">All investors →</a></div>
            <div class="pi-allocation-list">
                @forelse($allocation as $row)
                    <div class="pi-allocation-row"><div><b>{{ $row['name'] }}</b><small>{{ $row['currency'] }} {{ number_format($row['local_principal'],2) }} investor principal · {{ number_format($row['share'],1) }}% of reported USD value</small></div><strong>USD {{ number_format($row['usd_principal'],2) }}</strong><div class="pi-bar"><i style="width:{{ min(100,max(1,$row['share'])) }}%"></i></div></div>
                @empty<div class="pi-empty compact">No active investor portfolios yet.</div>@endforelse
            </div>
        </article>
    </div>

    @php
        $flowMax=max(1,max(array_merge($capitalFlow['deposits'] ?: [0],$capitalFlow['withdrawals'] ?: [0],array_map('abs',$capitalFlow['performance'] ?: [0]))));
        $bandTotal=max(1,array_sum($returnBands));
        $positivePct=($returnBands['positive']/$bandTotal)*100;
        $flatPct=($returnBands['flat']/$bandTotal)*100;
    @endphp
    <div class="pi-report-grid">
        <article class="pi-card">
            <div class="pi-card-head"><div><h2>Capital & Performance Flow</h2><span>Last 12 months of posted activity converted using each entry’s locked USD rate</span></div></div>
            <div class="pi-flow-chart">
                @foreach($capitalFlow['labels'] as $i=>$label)
                    <div class="pi-flow-column" title="{{ $label }}"><div class="pi-flow-bars"><i class="deposit" style="height:{{ max(2,($capitalFlow['deposits'][$i]/$flowMax)*100) }}%"></i><i class="withdrawal" style="height:{{ max(2,($capitalFlow['withdrawals'][$i]/$flowMax)*100) }}%"></i><i class="performance {{ $capitalFlow['performance'][$i]<0?'negative':'' }}" style="height:{{ max(2,(abs($capitalFlow['performance'][$i])/$flowMax)*100) }}%"></i></div><small>{{ explode(' ',$label)[0] }}</small></div>
                @endforeach
            </div>
            <div class="pi-legend"><span><i class="deposit"></i>Investment</span><span><i class="withdrawal"></i>Capital withdrawn</span><span><i class="performance"></i>Paid / net performance</span></div>
        </article>

        <article class="pi-card">
            <div class="pi-card-head"><div><h2>Investor Performance Status</h2><span>Published cumulative P/L by account</span></div></div>
            <div class="pi-donut-wrap"><div class="pi-donut" style="--positive:{{ $positivePct }};--flat:{{ $positivePct+$flatPct }}"><div><strong>{{ $returnBands['positive'] }}</strong><span>positive</span></div></div><div class="pi-donut-key"><p><i class="good"></i><span>Positive P/L</span><b>{{ $returnBands['positive'] }}</b></p><p><i class="flat"></i><span>Flat</span><b>{{ $returnBands['flat'] }}</b></p><p><i class="bad"></i><span>Negative P/L</span><b>{{ $returnBands['negative'] }}</b></p><p><i class="warn"></i><span>Voided corrections</span><b>{{ $summary['voided_entries'] }}</b></p></div></div>
        </article>
    </div>

    <div class="pi-report-grid">
        <article class="pi-card"><div class="pi-card-head"><div><h2>Recent Portfolio Activity</h2><span>Posted, draft and corrected entries</span></div><a class="pi-btn" href="{{ route('admin.private-investors.activity') }}">Open Ledger</a></div>
            <div class="pi-activity-list">@forelse($recentTransactions as $tx)<a href="{{ route('admin.private-investors.show',$tx->account) }}" class="pi-activity-row"><div><b>{{ $tx->account?->user?->name ?? 'Investor' }}</b><small>{{ match($tx->type){'deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit Paid','loss'=>'Loss','fee'=>'Fee','adjustment'=>'Adjustment',default=>ucfirst($tx->type)} }} · {{ $tx->transaction_date?->format('d M Y') }}</small></div><strong class="{{ in_array($tx->type,['profit','deposit'])?'pi-positive':(in_array($tx->type,['withdrawal','loss','fee'])?'pi-negative':'') }}">{{ $tx->account?->currency }} {{ number_format((float)$tx->amount,2) }}</strong><span class="pi-status {{ $tx->status }}">{{ $tx->status }}</span></a>@empty<div class="pi-empty compact">No portfolio activity recorded.</div>@endforelse</div>
        </article>
        <article class="pi-card"><div class="pi-card-head"><div><h2>Investor Requests</h2><span>Capital and service requests</span></div><a class="pi-btn" href="{{ route('admin.private-investors.requests') }}">Review Queue</a></div>
            @forelse($recentRequests as $r)<div class="pi-activity-row"><div><b>{{ $r->user?->name }}</b><small>{{ ucwords(str_replace('_',' ',$r->type)) }} · {{ $r->created_at->format('d M · H:i') }}</small></div><strong>{{ $r->amount ? $r->currency.' '.number_format($r->amount,2) : 'Review' }}</strong><span class="pi-status {{ $r->status }}">{{ str_replace('_',' ',$r->status) }}</span></div>@empty<div class="pi-empty compact">No investor requests.</div>@endforelse
        </article>
    </div>

    <div class="pi-sensitive-note"><b>Private investor administration</b><span>Investor-facing balances stay in each assigned account currency. Consolidated Admin totals preserve historical USD principal basis. Capital is returned in the investor's original currency amount; any settlement-rate difference is Admin-only realized FX gain/loss.</span></div>
</section>
@endsection
