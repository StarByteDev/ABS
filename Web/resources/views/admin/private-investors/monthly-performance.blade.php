@extends('admin.layout')
@section('title','Monthly Progress — ABS Admin')
@section('heading','Monthly Progress')
@section('description','See every Private Investor monthly agreement, current target and month-to-date progress in one place.')
@section('content')
<section class="pi-shell pi-v1570 pi-v1571">
    <div class="pi-v1570-hero compact">
        <div><span class="pi-kicker">Private Investors</span><h2>Monthly Progress</h2><p>Quickly identify accounts with active agreements, missing setup or unusual progress before opening an individual investor.</p></div>
    </div>

    <div class="pi-v1570-kpis">
        <article><span>Investor Accounts</span><strong>{{ $summary['investors'] }}</strong><small>Active portfolios</small></article>
        <article><span>Active Monthly Plans</span><strong>{{ $summary['active_plans'] }}</strong><small>Current month</small></article>
        <article><span>Combined Month Target</span><strong>USD {{ number_format($summary['target'],2) }}</strong><small>Locked FX reporting basis</small></article>
        <article><span>Posted MTD</span><strong class="pi-positive">USD {{ number_format($summary['mtd'],2) }}</strong><small>Converted using each investor's latest locked rate</small></article>
    </div>

    <article class="pi-v1570-panel">
        <form class="pi-filter-row" method="GET"><label>Find investor<input name="q" value="{{ request('q') }}" placeholder="Name or email"></label><button class="pi-btn primary">Search</button>@if(request('q'))<a class="pi-btn" href="{{ route('admin.private-investors.monthly-performance') }}">Clear</a>@endif</form>
    </article>

    <article class="pi-v1570-panel">
        <div class="pi-v1570-panel-head"><div><h3>Current Month</h3><p>Agreement terms and daily progress across all managed accounts.</p></div></div>
        <div class="pi-table-wrap"><table class="pi-table pi-v1570-table"><thead><tr><th>Investor</th><th>Net Capital</th><th>USD Capital</th><th>Agreement</th><th>Month Target</th><th>Posted MTD</th><th>Progress</th><th>Performance To Date</th><th>Indicative Value</th><th></th></tr></thead><tbody>
        @forelse($rows as $row)
            @php($account=$row['account'])
            <tr>
                <td><b>{{ $account->user?->name }}</b><small>{{ $account->user?->email }}</small></td>
                <td>{{ $account->currency }} {{ number_format((float)$account->net_contributions,2) }}</td>
                <td>USD {{ number_format((float)($account->net_contributions_usd ?? (strtoupper((string)$account->currency)==='USD'?$account->net_contributions:0)),2) }}</td>
                <td>@if($row['term'])<b>{{ number_format((float)$row['term']->monthly_target_rate,2) }}%</b><small>From {{ $row['term']->effective_from?->format('d M Y') }}</small>@else<span class="pi-status draft">Setup required</span>@endif</td>
                <td>{{ $account->currency }} {{ number_format($row['target'],2) }}@if($row['target_usd']!==null)<small>USD {{ number_format($row['target_usd'],2) }}</small>@endif</td>
                <td class="{{ $row['mtd']>=0?'pi-positive':'pi-negative' }}">{{ $account->currency }} {{ number_format($row['mtd'],2) }}@if($row['mtd_usd']!==null)<small>USD {{ number_format($row['mtd_usd'],2) }}</small>@endif</td>
                <td><div class="pi-progress compact"><i style="width:{{ min(100,max(0,$row['progress'])) }}%"></i></div><small>{{ number_format($row['progress'],1) }}% · {{ $row['days_posted'] }}/{{ $row['days_total'] }} days</small></td>
                <td class="pi-positive">{{ $account->currency }} {{ number_format($row['performance_to_date'],2) }}</td>
                <td>{{ $account->currency }} {{ number_format($row['indicative_value'],2) }}</td>
                <td><div class="pi-row-actions"><a class="pi-mini-btn good" href="{{ route('admin.private-investors.investment-setup',$account) }}">Setup</a><a class="pi-mini-btn" href="{{ route('admin.private-investors.account-performance',$account) }}">Progress</a></div></td>
            </tr>
        @empty
            <tr><td colspan="10">No Private Investor accounts found.</td></tr>
        @endforelse
        </tbody></table></div>
    </article>
</section>
@endsection
