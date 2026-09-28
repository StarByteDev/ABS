@extends('pulse.layout')
@section('title','Investor Requests · ABS Pulse')
@section('heading','Investor Requests')
@section('content')
<section class="pi-shell pi-v1573">
    <header class="pi-head">
        <div>
            <span class="pi-kicker">Private Investor</span>
            <h1>Investment & Withdrawal Requests</h1>
            <p>Submit a request to add capital, request a withdrawal, or ask for a portfolio review. Requests are reviewed before any account change is recorded.</p>
        </div>
        <a class="pi-btn cyan" href="{{ route('pulse.support.index') }}">Message Support</a>
    </header>
    @include('private._tabs')

    @if($account)
        <article class="pi-card pi73-currency-card">
            <div class="pi-card-head">
                <div>
                    <h2>Principal Currency</h2>
                    <span>Your capital is returned in the same principal currency. ABS handles USD conversion internally.</span>
                </div>
                <span class="pi71-currency-badge">{{ $account->currency }}</span>
            </div>

            @if($currencyEditable)
                <form method="POST" action="{{ route('private.currency.update') }}" class="pi-form pi73-currency-form">
                    @csrf @method('PATCH')
                    <label>
                        <span>Choose principal currency</span>
                        <select name="currency" required>
                            @foreach($supportedCurrencies as $code)
                                <option value="{{ $code }}" @selected(old('currency',$account->currency)===$code)>{{ $code }}</option>
                            @endforeach
                        </select>
                        <small>You can change this while the portfolio has no financial activity. It locks after the first transaction/history is recorded.</small>
                    </label>
                    <button class="pi-btn primary" type="submit">Save Currency</button>
                </form>
            @else
                <div class="pi73-currency-lock">
                    <b>Currency locked: {{ $account->currency }}</b>
                    <span>{{ $currencyLockMessage }}</span>
                </div>
            @endif
        </article>

        <article class="pi-card">
            <div class="pi-card-head"><h2>New Request</h2><span>Current principal currency: {{ $account->currency }}</span></div>
            <form method="POST" action="{{ route('private.requests.store') }}" class="pi-form" id="investorRequestForm">@csrf
                <div class="pi-request-grid">
                    <div class="pi-request-option"><input id="req-add" type="radio" name="type" value="add_investment" @checked(old('type','add_investment')==='add_investment')><label for="req-add"><b>Add Investment</b><span>Request to add capital. Before financial activity starts you can choose the principal currency for the portfolio.</span></label></div>
                    <div class="pi-request-option"><input id="req-withdraw" type="radio" name="type" value="withdrawal" @checked(old('type')==='withdrawal')><label for="req-withdraw"><b>Capital Withdrawal</b><span>Request return of invested principal in the same {{ $account->currency }} currency. FX movements do not change your principal amount.</span></label></div>
                    <div class="pi-request-option"><input id="req-review" type="radio" name="type" value="portfolio_review" @checked(old('type')==='portfolio_review')><label for="req-review"><b>Portfolio Review</b><span>Ask the ABS team to contact you about your account.</span></label></div>
                </div>

                <div class="pi-form-row">
                    <label><span id="requestAmountLabel">Amount in {{ $account->currency }}</span><input type="number" min="0.01" step="0.01" name="amount" id="requestAmount" value="{{ old('amount') }}" placeholder="Amount when applicable"></label>
                    <label><span>Request currency</span>
                        <select name="currency" id="requestCurrency" @disabled(!$currencyEditable)>
                            @foreach($supportedCurrencies as $code)
                                <option value="{{ $code }}" @selected(old('currency',$account->currency)===$code)>{{ $code }}</option>
                            @endforeach
                        </select>
                        @if(!$currencyEditable)<input type="hidden" name="currency" value="{{ $account->currency }}">@endif
                        <small id="requestCurrencyHelp">{{ $currencyEditable ? 'For your first/add-investment request, choose the currency you will actually provide.' : 'Your principal currency is already locked by financial history.' }}</small>
                    </label>
                </div>

                <div class="pi-note" id="principalProtectionNote"><strong>Principal protection:</strong> If you invest {{ $account->currency }} 1,000, a full capital withdrawal returns {{ $account->currency }} 1,000 principal regardless of later USD exchange-rate movement. ABS manages any resulting FX gain/loss internally.</div>
                <label>Message <textarea name="message" placeholder="Add any details you want the portfolio team to review.">{{ old('message') }}</textarea></label>
                <div><button class="pi-btn primary" type="submit">Submit Request</button></div>
            </form>
        </article>

        <article class="pi-card"><div class="pi-card-head"><h2>Request History</h2><span>Latest first</span></div><div class="pi-table-wrap"><table class="pi-table"><thead><tr><th>Date</th><th>Request</th><th>Amount</th><th>Status</th><th>Admin Note</th><th></th></tr></thead><tbody>@forelse($requests as $r)<tr><td>{{ $r->created_at->format('d M Y · H:i') }}</td><td>{{ ucwords(str_replace('_',' ',$r->type)) }}</td><td>{{ $r->amount ? $r->currency.' '.number_format($r->amount,2) : '—' }}</td><td><span class="pi-status {{ $r->status }}">{{ str_replace('_',' ',$r->status) }}</span></td><td>{{ $r->admin_note ?: '—' }}</td><td>@if($r->status==='submitted')<form method="POST" action="{{ route('private.requests.cancel',$r) }}">@csrf @method('PATCH')<button class="pi-btn" type="submit">Cancel</button></form>@endif</td></tr>@empty<tr><td colspan="6">No requests submitted.</td></tr>@endforelse</tbody></table></div></article>
        {{ $requests->links() }}
    @else
        <div class="pi-empty">Your investor portfolio must be configured before requests can be submitted.</div>
    @endif
</section>
@if($account)
<script>
(() => {
    const form = document.getElementById('investorRequestForm');
    const currencySelect = document.getElementById('requestCurrency');
    const amount = document.getElementById('requestAmount');
    const amountLabel = document.getElementById('requestAmountLabel');
    const help = document.getElementById('requestCurrencyHelp');
    const note = document.getElementById('principalProtectionNote');
    const accountCurrency = @json($account->currency);
    const currencyEditable = @json((bool)$currencyEditable);

    if (!form || !currencySelect) return;

    function selectedType(){ return form.querySelector('input[name="type"]:checked')?.value || 'add_investment'; }
    function refresh(){
        const type = selectedType();
        if (type === 'withdrawal' || type === 'portfolio_review' || !currencyEditable) {
            currencySelect.value = accountCurrency;
            currencySelect.disabled = true;
        } else {
            currencySelect.disabled = false;
        }
        const code = type === 'add_investment' && currencyEditable ? currencySelect.value : accountCurrency;
        amount.disabled = type === 'portfolio_review';
        amount.required = type !== 'portfolio_review';
        amountLabel.textContent = type === 'portfolio_review' ? 'Amount not required' : 'Amount in ' + code;
        help.textContent = type === 'add_investment' && currencyEditable
            ? 'Choose the currency you will actually provide. Submitting this first investment request also sets your principal currency.'
            : type === 'withdrawal'
                ? 'Capital withdrawal is always requested in your locked principal currency: ' + accountCurrency + '.'
                : type === 'portfolio_review'
                    ? 'Currency is not changed by a portfolio review request.'
                    : 'Your principal currency is already locked by financial history.';
        note.innerHTML = '<strong>Principal protection:</strong> If you invest ' + code + ' 1,000, a full capital withdrawal returns ' + code + ' 1,000 principal regardless of later USD exchange-rate movement. ABS manages any resulting FX gain/loss internally.';
    }

    form.querySelectorAll('input[name="type"]').forEach(el => el.addEventListener('change', refresh));
    currencySelect.addEventListener('change', refresh);
    refresh();
})();
</script>
@endif
@endsection
