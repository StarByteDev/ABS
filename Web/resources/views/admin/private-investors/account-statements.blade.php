@extends('admin.layout')
@section('title',$account->user->name.' — Statements')
@section('heading',$account->user->name)
@section('description','Automatic investor statements reconciled from the audited ledger, monthly agreement and profit payouts.')
@section('page-actions')<a class="pi-btn" href="{{ route('admin.private-investors.show',$account) }}">Account Summary</a>@endsection
@section('content')
<section class="pi-shell pi-v1574">
    @include('admin.private-investors._account-tabs')
    @php($currency=strtoupper((string)$account->currency))
    @php($isUsd=$currency==='USD')
    @php($profitPaid=(float)$account->transactions()->where('status','posted')->where('type','profit')->sum('amount'))

    <div class="pi71-two-column">
        <article class="pi71-card">
            <div class="pi71-card-head"><div><h3>Statement Reconciliation</h3><p>Statements are created automatically when a performance month reaches its agreed payout date. Use this only to re-reconcile a selected period from the posted ledger.</p></div></div>
            <form class="pi71-form" method="POST" action="{{ route('admin.private-investors.statements.store',$account) }}">
                @csrf
                <div class="pi71-form-grid">
                    <label><span>Performance month</span><input type="date" name="statement_month" value="{{ now()->subMonth()->startOfMonth()->toDateString() }}" required><small>Balances and profit are read from posted transactions; they are not typed manually.</small></label>
                    <label><span>Investor currency</span><div class="pi-readonly-value">{{ $currency }}</div><small>Statements remain in the investor's locked principal currency.</small></label>
                </div>
                <label><span>Investor-visible note</span><textarea name="notes" placeholder="Optional note for this monthly statement"></textarea></label>
                <button class="pi-btn primary">Reconcile Statement From Ledger</button>
            </form>
            <div class="pi71-notice"><b>Automatic monthly workflow</b><span>Daily performance accrues from the agreed monthly percentage. At the configured payout date ABS records one Profit Paid transaction, leaves principal unchanged, and publishes the monthly statement automatically.</span></div>
        </article>
        <aside class="pi71-card pi71-summary-card">
            <div class="pi71-card-head"><div><h3>Reporting Status</h3><p>{{ $statements->total() }} statement(s)</p></div></div>
            <div class="pi71-metric-row"><span>Investor principal</span><b>{{ $currency }} {{ number_format((float)$account->net_contributions,2) }}</b></div>
            <div class="pi71-metric-row"><span>Profit paid to date</span><b class="pi-positive">{{ $currency }} {{ number_format($profitPaid,2) }}</b></div>
            <div class="pi71-metric-row"><span>Current-month paid profit</span><b>{{ $currency }} {{ number_format((float)$account->monthly_profit,2) }}</b></div>
            <div class="pi71-divider"></div>
            <div class="pi71-metric-row"><span>Admin USD principal basis</span><b>USD {{ number_format((float)($account->net_contributions_usd ?? ($isUsd?$account->net_contributions:0)),2) }}</b></div>
            <div class="pi71-metric-row"><span>Admin profit paid</span><b>USD {{ number_format((float)($account->total_profit_usd ?? ($isUsd?$account->total_profit:0)),2) }}</b></div>
            <div class="pi71-metric-row"><span>Realized FX gain / loss</span><b class="{{ (float)($account->realized_fx_gain_loss_usd ?? 0)>=0?'pi-positive':'pi-negative' }}">{{ (float)($account->realized_fx_gain_loss_usd ?? 0)>=0?'+':'' }}USD {{ number_format((float)($account->realized_fx_gain_loss_usd ?? 0),2) }}</b></div>
        </aside>
    </div>

    <article class="pi71-card">
        <div class="pi71-card-head"><div><h3>Statement History</h3><p>Newest first · automatically reconciled</p></div></div>
        <div class="pi-table-wrap"><table class="pi-table pi-v1570-table"><thead><tr><th>Month</th><th>Opening</th><th>Added</th><th>Capital Withdrawn</th><th>P/L</th><th>Profit Paid</th><th>Closing Capital</th><th>Payment</th><th>USD Closing</th></tr></thead><tbody>
        @forelse($statements as $s)
            <tr><td><b>{{ $s->statement_month->format('F Y') }}</b></td><td>{{ $currency }} {{ number_format((float)$s->opening_balance,2) }}</td><td>{{ $currency }} {{ number_format((float)$s->contributions,2) }}</td><td>{{ $currency }} {{ number_format((float)$s->withdrawals,2) }}</td><td class="{{ $s->profit_loss>=0?'pi-positive':'pi-negative' }}">{{ $currency }} {{ number_format((float)$s->profit_loss,2) }}</td><td class="pi-positive">{{ $currency }} {{ number_format((float)$s->profit_paid,2) }}</td><td>{{ $currency }} {{ number_format((float)$s->closing_balance,2) }}</td><td>{{ $s->payment_date?->format('d M Y') ?: '—' }}</td><td>{{ $s->closing_balance_usd !== null ? 'USD '.number_format((float)$s->closing_balance_usd,2) : 'FX reconciliation pending' }}</td></tr>
        @empty
            <tr><td colspan="9">No completed statement period is available yet.</td></tr>
        @endforelse
        </tbody></table></div>
        {{ $statements->links() }}
    </article>
</section>
@endsection
