@extends('admin.layout')
@section('title',$account->user->name.' — Account Controls')
@section('heading',$account->user->name)
@section('description','Reconcile or correct stored account balances when identified incorrect values need cleanup.')
@section('page-actions')<a class="pi-btn" href="{{ route('admin.private-investors.show',$account) }}">Account Summary</a>@endsection
@section('content')
<section class="pi-shell pi-v1570 pi-v1571 pi-v1573">
    @include('admin.private-investors._account-tabs')

    <div class="pi-v1570-hero compact">
        <div><span class="pi-kicker">Maintenance</span><h2>Account Controls</h2><p>Use this page only for corrections. Day-to-day investor management happens in Investment Setup, Monthly Progress and Transactions.</p></div>
    </div>

    <article class="pi71-card pi73-currency-card">
        <div class="pi71-card-head"><div><h3>Principal Currency Control</h3><p>Use this only before financial activity starts. Once history exists, the currency is locked to protect original-currency principal.</p></div><span class="pi71-currency-badge">{{ $account->currency }}</span></div>
        @if($currencyEditable)
            <form method="POST" action="{{ route('admin.private-investors.accounts.currency.update',$account) }}" class="pi73-currency-form">@csrf @method('PATCH')
                <label><span>Principal currency</span><select name="currency" required>@foreach($supportedCurrencies as $code)<option value="{{ $code }}" @selected($account->currency===$code)>{{ $code }}</option>@endforeach</select><small>Changing an empty account is safe because no investor principal has been recorded yet.</small></label>
                <button class="pi-btn primary">Update Currency</button>
            </form>
        @else
            <div class="pi73-currency-lock"><b>Currency locked: {{ $account->currency }}</b><span>{{ $currencyLockMessage }}</span></div>
        @endif
    </article>

    <div class="pi-v1570-kpis">
        <article><span>Reported Value</span><strong>{{ $account->currency }} {{ number_format((float)$account->current_value,2) }}</strong><small>Visible account value</small></article>
        <article><span>Net Investment</span><strong>{{ $account->currency }} {{ number_format((float)$account->net_contributions,2) }}</strong><small>Stored capital</small></article>
        <article><span>Profit Paid / Net P/L</span><strong class="{{ $account->total_profit>=0?'pi-positive':'pi-negative' }}">{{ $account->currency }} {{ number_format((float)$account->total_profit,2) }}</strong><small>Official posted P/L</small></article>
        <article><span>Ledger Entries</span><strong>{{ number_format($ledger['posted_count']) }}</strong><small>Posted transactions</small></article>
    </div>

    <div class="pi-v1570-control-grid">
        <article class="pi-v1570-action-card recommended">
            <span class="badge">Recommended</span><h3>Recalculate From Ledger</h3>
            <p>Use after deleting or editing incorrect transactions. ABS rebuilds reported value, net investment and P/L from the opening balance plus remaining posted entries.</p>
            <div class="pi-v1570-reconcile-values">
                <div><span>Ledger value</span><b>{{ $account->currency }} {{ number_format($ledger['recalculated_current_value'],2) }}</b></div>
                <div><span>Ledger net investment</span><b>{{ $account->currency }} {{ number_format($ledger['recalculated_net_investment'],2) }}</b></div>
                <div><span>Ledger P/L</span><b class="{{ $ledger['recalculated_profit']>=0?'pi-positive':'pi-negative' }}">{{ $account->currency }} {{ number_format($ledger['recalculated_profit'],2) }}</b></div>
            </div>
            <form method="POST" action="{{ route('admin.private-investors.portfolio-values.recalculate',$account) }}" onsubmit="return confirm('Recalculate stored totals from the remaining posted transactions?')">@csrf<input type="hidden" name="confirm_recalculate" value="1"><button class="pi-btn primary">Recalculate Now</button></form>
        </article>

        <article class="pi-v1570-action-card">
            <h3>Manual Balance Correction</h3><p>Only use when you intentionally need to set a specific reported value or published P/L outside the transaction ledger.</p>
            <details class="pi-v1570-advanced"><summary>Open manual values</summary>
                <form class="pi-v1570-form" method="POST" action="{{ route('admin.private-investors.accounts.update',$account) }}">@csrf @method('PUT')
                    <input type="hidden" name="account_name" value="{{ $account->account_name }}"><input type="hidden" name="currency" value="{{ $account->currency }}"><input type="hidden" name="notes" value="{{ $account->notes }}"><input type="hidden" name="is_active" value="{{ $account->is_active?1:0 }}">
                    <div class="pi-v1570-form-grid">
                        <label><span>Opening investment</span><input type="number" min="0" step="0.01" name="opening_value" value="{{ $account->opening_value }}" required></label>
                        <label><span>Reported value</span><input type="number" min="0" step="0.01" name="current_value" value="{{ $account->current_value }}" required></label>
                        <label><span>Net investment</span><input type="number" min="0" step="0.01" name="net_contributions" value="{{ $account->net_contributions }}" required></label>
                        <label><span>Profit Paid / Net P/L</span><input type="number" step="0.01" name="total_profit" value="{{ $account->total_profit }}" required></label>
                        <label><span>Latest month P/L</span><input type="number" step="0.01" name="monthly_profit" value="{{ $account->monthly_profit }}" required></label>
                        <label><span>Valuation date</span><input type="date" name="valuation_date" value="{{ optional($account->valuation_date)->toDateString() ?: today()->toDateString() }}" required></label>
                    </div><button class="pi-btn">Save Manual Values</button>
                </form>
            </details>
        </article>

        <article class="pi-v1570-action-card danger">
            <h3>Reset Account Rollup</h3><p>Administrative recovery control for an identified account-rollup issue. It does not delete the investor login, transactions, statements or requests.</p>
            <form method="POST" action="{{ route('admin.private-investors.portfolio-values.reset',$account) }}" onsubmit="return confirm('Reset current account-level portfolio values to zero?')">@csrf<label class="pi-check"><input type="checkbox" name="confirm_reset" value="1" required> Confirm reset</label><button class="pi-btn danger">Reset Values</button></form>
        </article>
    </div>
</section>
@endsection
