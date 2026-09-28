@extends('admin.layout')
@section('title','Investor Statements — ABS Admin')
@section('heading','Investor Statements')
@section('description','Published month-end records in each investor currency with consolidated USD reporting values for administration.')
@section('content')
<section class="pi-shell pi-v1571">
    <article class="pi71-card"><form class="pi-filter-row" method="GET"><label>Investor<select name="user"><option value="">All investors</option>@foreach($members as $m)<option value="{{ $m->id }}" @selected((int)request('user')===$m->id)>{{ $m->name }} — {{ $m->email }}</option>@endforeach</select></label><button class="pi-btn primary">Filter</button>@if(request('user'))<a class="pi-btn" href="{{ route('admin.private-investors.statements') }}">Clear</a>@endif</form></article>
    <article class="pi71-card">
        <div class="pi71-card-head"><div><h3>Published Statements</h3><p>Investor figures remain in the assigned currency. USD columns use the conversion locked when the statement was published.</p></div></div>
        <div class="pi-table-wrap"><table class="pi-table pi-v1570-table"><thead><tr><th>Investor</th><th>Month</th><th>Currency</th><th>Closing Balance</th><th>Profit / Loss</th><th>Return</th><th>Admin USD Closing</th><th>Admin USD P/L</th><th>Published</th></tr></thead><tbody>
        @forelse($statements as $s)
            @php($ret=(float)$s->opening_balance>0?((float)$s->profit_loss/(float)$s->opening_balance)*100:0)
            @php($currency=strtoupper((string)($s->currency ?: $s->account?->currency ?: 'USD')))
            <tr>
                <td>@if($s->account)<a href="{{ route('admin.private-investors.show',$s->account) }}"><b>{{ $s->account?->user?->name }}</b></a><small>{{ $s->account?->user?->email }}</small>@endif</td>
                <td>{{ $s->statement_month->format('F Y') }}</td><td><span class="pi71-currency-badge">{{ $currency }}</span></td>
                <td><b>{{ $currency }} {{ number_format((float)$s->closing_balance,2) }}</b><small>Opening {{ number_format((float)$s->opening_balance,2) }}</small></td>
                <td class="{{ $s->profit_loss>=0?'pi-positive':'pi-negative' }}">{{ $currency }} {{ number_format((float)$s->profit_loss,2) }}</td>
                <td class="{{ $ret>=0?'pi-positive':'pi-negative' }}">{{ number_format($ret,2) }}%</td>
                <td>@if($s->closing_balance_usd!==null)<b>USD {{ number_format((float)$s->closing_balance_usd,2) }}</b>@else<span class="pi-status draft">FX required</span>@endif</td>
                <td>@if($s->profit_loss_usd!==null)<span class="{{ $s->profit_loss_usd>=0?'pi-positive':'pi-negative' }}">USD {{ number_format((float)$s->profit_loss_usd,2) }}</span>@else—@endif</td>
                <td>{{ $s->published_at?->format('d M Y H:i') ?: '—' }}</td>
            </tr>
        @empty<tr><td colspan="9">No statements published.</td></tr>@endforelse
        </tbody></table></div>
    </article>
    {{ $statements->links() }}
</section>
@endsection
