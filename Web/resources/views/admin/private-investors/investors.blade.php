@extends('admin.layout')
@section('title','Investor Accounts — ABS Admin')
@section('heading','Investor Accounts')
@section('description','Open each Private Investor account, set the investor currency, and manage the investment agreement, progress and financial activity.')
@section('page-actions')<a class="pi-btn primary" href="{{ route('admin.users.create',['role'=>'private_investor']) }}">Create Investor Login</a>@endsection
@section('content')
<section class="pi-shell pi-v1571">
    @if($unconfigured->count())
        <article class="pi71-card">
            <div class="pi71-card-head"><div><h3>New Portfolio Setup</h3><p>{{ $unconfigured->count() }} Private Investor account(s) still need a portfolio shell.</p></div></div>
            <form class="pi71-quick-grid" method="POST" action="{{ route('admin.private-investors.accounts.store') }}">
                @csrf
                <label><span>Investor</span><select name="user_id" required>@foreach($unconfigured as $member)<option value="{{ $member->id }}">{{ $member->name }} — {{ $member->email }}</option>@endforeach</select></label>
                <label><span>Investor currency</span><select name="currency" required>@foreach(config('private_investor.supported_currencies',['USD']) as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select><small>The investor sees all portfolio values and performance in this currency.</small></label>
                <input type="hidden" name="quick_setup" value="1">
                <input type="hidden" name="account_name" value="Private Investor Portfolio">
                <input type="hidden" name="opening_value" value="0"><input type="hidden" name="current_value" value="0"><input type="hidden" name="net_contributions" value="0"><input type="hidden" name="total_profit" value="0"><input type="hidden" name="monthly_profit" value="0"><input type="hidden" name="valuation_date" value="{{ today()->toDateString() }}">
                <button class="pi-btn primary">Create Empty Portfolio</button>
            </form>
            <div class="pi71-notice"><b>Recommended flow</b><span>Create the empty portfolio first, then open <b>Transactions</b> to record the first investment. This ensures the original amount, currency, locked USD conversion and monthly agreement are all audited together.</span></div>
        </article>
    @endif

    <article class="pi71-card"><form method="GET" class="pi71-search"><label><span>Find investor</span><input name="q" value="{{ request('q') }}" placeholder="Name or email"></label><button class="pi-btn primary">Search</button>@if(request('q'))<a class="pi-btn" href="{{ route('admin.private-investors.investors') }}">Clear</a>@endif</form></article>

    <article class="pi71-card">
        <div class="pi71-card-head"><div><h3>Private Investor Accounts</h3><p>{{ $investors->total() }} account(s)</p></div></div>
        <div class="pi-table-wrap"><table class="pi-table pi-v1570-table"><thead><tr><th>Investor</th><th>Investor Currency</th><th>Investor Principal</th><th>Admin USD Principal Basis</th><th>Monthly Agreement</th><th>Status</th><th>Manage</th></tr></thead><tbody>
        @forelse($investors as $user)
            @php($account=$user->portfolioAccount)
            <tr>
                <td><b>{{ $user->name }}</b><small>{{ $user->email }}</small></td>
                <td>{{ $account?->currency ?: '—' }}</td>
                <td>{{ $account ? $account->currency.' '.number_format((float)$account->net_contributions,2) : 'Not configured' }}</td>
                <td>@if($account)<b>USD {{ number_format((float)($account->net_contributions_usd ?? (strtoupper((string)$account->currency)==='USD'?$account->net_contributions:0)),2) }}</b>@if(strtoupper((string)$account->currency)!=='USD' && $account->net_contributions_usd===null)<small class="pi-negative">FX reconciliation required</small>@endif @else—@endif</td>
                <td>@if($account?->investmentTerm)<b>{{ number_format((float)$account->investmentTerm->monthly_target_rate,2) }}%</b><small>From {{ $account->investmentTerm->effective_from?->format('d M Y') }}</small>@else<span class="pi-status draft">Setup required</span>@endif</td>
                <td>@if($account?->investmentTerm)<span class="pi-status {{ $account->investmentTerm->status==='active'?'approved':'draft' }}">{{ $account->investmentTerm->status }}</span>@else—@endif</td>
                <td>@if($account)<div class="pi-row-actions"><a class="pi-mini-btn" href="{{ route('admin.private-investors.show',$account) }}">Summary</a><a class="pi-mini-btn good" href="{{ route('admin.private-investors.account-transactions',$account) }}">Transactions</a><a class="pi-mini-btn" href="{{ route('admin.private-investors.account-performance',$account) }}">Progress</a></div>@else<a class="pi-mini-btn" href="{{ route('admin.users.show',$user) }}">Set Up</a>@endif</td>
            </tr>
        @empty<tr><td colspan="7">No Private Investor accounts found.</td></tr>@endforelse
        </tbody></table></div>
    </article>
    {{ $investors->links() }}
</section>
@endsection
