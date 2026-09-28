@extends('admin.layout')
@section('title',$account->user->name.' — Transactions')
@section('heading',$account->user->name)
@section('description','Keep investor principal fixed in its original currency while ABS carries the investment and realized FX result internally in USD.')
@section('page-actions')<a class="pi-btn" href="{{ route('admin.private-investors.show',$account) }}">Account Summary</a>@endsection
@section('content')
<section class="pi-shell pi-v1571 pi-v1573">
    @include('admin.private-investors._account-tabs')
    @php
        $term = $account->investmentTerm;
        $currency = strtoupper((string)$account->currency);
        $isUsd = $currency === 'USD';
        $emailReady = ($mailHealth['status'] ?? 'setup_required') === 'ready';
    @endphp

    <div class="pi71-header-card">
        <div>
            <span class="pi-kicker">Investor Ledger</span>
            <h2>Transactions</h2>
            <p>The investor principal is permanently denominated in <b>{{ $currency }}</b>. FX changes never increase or reduce the investor principal; they are recorded as Admin-only USD FX gain/loss when capital is returned.</p>
        </div>
        <div class="pi71-status-stack">
            <span class="pi-v1570-status {{ $term && $term->status==='active'?'good':'warn' }}"><i></i>{{ $term && $term->status==='active' ? number_format((float)$term->monthly_target_rate,2).'% monthly agreement' : 'Monthly agreement not set' }}</span>
            <span class="pi-v1570-status {{ $emailReady?'good':'warn' }}"><i></i>{{ $emailReady ? 'Investor email delivery ready' : 'Email delivery setup required' }}</span>
        </div>
    </div>

    @if(!$emailReady)
        <div class="pi71-notice warning"><b>Investor email is not currently deliverable.</b><span>The financial entry and in-app Pulse alert will still be recorded. Configure SMTP/sendmail from <a href="{{ route('admin.enterprise.emails') }}">Alerts & Emails</a> before relying on inbox delivery.</span></div>
    @endif

    <div class="pi71-two-column">
        <article class="pi71-card">
            <div class="pi71-card-head"><div><h3>Record Portfolio Activity</h3><p>Record capital movements and exceptional corrections. Monthly profit is accrued and paid automatically from the investment agreement.</p></div></div>
            <form class="pi71-form" method="POST" action="{{ route('admin.private-investors.transactions.store',$account) }}" id="portfolioTransactionForm">
                @csrf
                <div class="pi71-form-grid">
                    <label><span>Activity type</span><select name="type" id="transactionType" required>@foreach(['deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit Payout Correction','loss'=>'Loss Correction','fee'=>'Fee','adjustment'=>'Valuation Adjustment'] as $type=>$label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label>
                    <label><span>Principal currency</span>
                        <select name="currency" id="transactionCurrency" @disabled(!$currencyEditable) required>@foreach($supportedCurrencies as $code)<option value="{{ $code }}" @selected(old('currency',$currency)===$code)>{{ $code }}</option>@endforeach</select>
                        @if(!$currencyEditable)<input type="hidden" name="currency" value="{{ $currency }}">@endif
                        <small>{{ $currencyEditable ? 'Change this now if the first investment will be received in another currency.' : $currencyLockMessage }}</small>
                    </label>
                    <label><span id="transactionAmountLabel">Amount in {{ $currency }}</span><div class="pi-input-prefix"><b id="transactionCurrencyPrefix">{{ $currency }}</b><input type="number" step="0.01" name="amount" id="transactionAmount" required placeholder="0.00"></div></label>
                    <label><span>Effective date</span><input type="date" name="transaction_date" id="transactionDate" value="{{ today()->toDateString() }}" required></label>
                    <label><span>Reference</span><input name="reference" placeholder="Bank transfer or internal reference"></label>
                </div>

                <div class="pi71-fx-box" id="transactionFxBox" @if($isUsd) hidden @endif>
                    <div class="pi71-card-head"><div><h4>USD Conversion / Settlement Rate</h4><p>Investment: locks the original USD carrying basis. Capital withdrawal: records the USD cost of returning the exact investor-currency principal. The difference becomes Admin-only FX gain/loss.</p></div><button type="button" class="pi-mini-btn" id="fetchFxRate">Get current reference</button></div>
                    <div class="pi71-form-grid">
                        <label><span id="fxRateLabel">1 {{ $currency }} equals USD</span><input type="number" step="0.00000001" min="0.00000001" name="fx_rate_to_usd" id="fxRateToUsd" value="{{ old('fx_rate_to_usd',$isUsd ? 1 : '') }}" placeholder="0.00000000"></label>
                        <label><span id="usdEquivalentLabel">USD equivalent</span><div class="pi-readonly-value" id="usdEquivalent">USD 0.00</div></label>
                    </div>
                    <div class="pi-v1570-inline-preview" id="fxAccountingPreview">Investor principal stays {{ $currency }} {{ number_format((float)$account->net_contributions,2) }} until a capital withdrawal is posted.</div><small class="pi71-source">Reference quotes can be loaded for convenience. For backdated investments, enter the actual historical conversion used for the transfer; for withdrawals, enter the actual settlement conversion. <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener">Rates by ExchangeRate-API</a>.</small>
                </div>

                <div class="pi-v1570-agreement-inline" id="investmentAgreementFields">
                    <div class="pi-v1570-inline-head"><div><b>Monthly Performance Agreement</b><span>For investments, define the agreed monthly percentage and the date performance begins.</span></div><a href="{{ route('admin.private-investors.investment-setup',$account) }}">Full setup</a></div>
                    <div class="pi71-form-grid">
                        <label><span>Agreed monthly profit %</span><div class="pi-input-suffix"><input type="number" name="monthly_target_rate" id="transactionRate" step="0.01" min="0" max="50" value="{{ $term?->monthly_target_rate }}" placeholder="e.g. 5.00"><b>%</b></div><small>Used by the automatic daily accrual and monthly payout schedule.</small></label>
                        <label><span>Performance starts</span><input type="date" name="performance_start_date" id="performanceStart" value="{{ $term?->effective_from?->toDateString() ?: today()->toDateString() }}"><small>For a mid-month investment, the first month is prorated automatically.</small></label>
                    </div>
                    <div class="pi-v1570-inline-preview" id="investmentPreview">Enter amount and monthly % to preview the target.</div>
                </div>

                <label><span>Investor-visible description</span><textarea name="description" placeholder="Investor-visible description of this financial entry"></textarea></label>
                <div class="pi-v1570-actions"><button class="pi-btn" name="save_mode" value="draft">Save Draft</button><button class="pi-btn primary" name="save_mode" value="post">Post & Notify Investor</button></div>
            </form>
        </article>

        <aside class="pi71-card pi71-summary-card">
            <div class="pi71-card-head"><div><h3>Account Snapshot</h3><p>Investor view stays in {{ $currency }}. Admin consolidation is maintained in USD.</p></div></div>
            <div class="pi71-metric-row"><span>Reported value</span><b>{{ $currency }} {{ number_format((float)$account->current_value,2) }}</b></div>
            <div class="pi71-metric-row"><span>Investor principal</span><b>{{ $currency }} {{ number_format((float)$account->net_contributions,2) }}</b></div>
            <div class="pi71-metric-row"><span>Profit Paid / Net P/L</span><b class="{{ $account->total_profit>=0?'pi-positive':'pi-negative' }}">{{ $currency }} {{ number_format((float)$account->total_profit,2) }}</b></div>
            <div class="pi71-divider"></div>
            <div class="pi71-metric-row"><span>Admin reported capital</span><b>USD {{ number_format((float)($account->current_value_usd ?? ($isUsd?$account->current_value:0)),2) }}</b></div>
            <div class="pi71-metric-row"><span>Admin USD principal basis</span><b>USD {{ number_format((float)($account->net_contributions_usd ?? ($isUsd?$account->net_contributions:0)),2) }}</b></div>
            <div class="pi71-metric-row"><span>Realized FX gain / loss</span><b class="{{ (float)($account->realized_fx_gain_loss_usd ?? 0)>=0?'pi-positive':'pi-negative' }}">{{ (float)($account->realized_fx_gain_loss_usd ?? 0)>=0?'+':'' }}USD {{ number_format((float)($account->realized_fx_gain_loss_usd ?? 0),2) }}</b></div>
            <div class="pi71-metric-row"><span>Monthly agreement</span><b>{{ $term ? number_format((float)$term->monthly_target_rate,2).'%' : 'Not set' }}</b></div>
            <a class="pi-btn cyan full" href="{{ route('admin.private-investors.investment-setup',$account) }}">Investment Setup</a>
        </aside>
    </div>

    <article class="pi71-card">
        <div class="pi71-card-head"><div><h3>Transaction Ledger</h3><p>{{ $transactions->total() }} recorded entr{{ $transactions->total()===1?'y':'ies' }}. Investor principal stays in {{ $currency }}; USD settlement, historical principal basis and realized FX remain Admin-only.</p></div></div>
        <div class="pi-table-wrap">
            <table class="pi-table pi-v1570-table pi71-ledger">
                <thead><tr><th>Date</th><th>Activity</th><th>Investor Amount</th><th>Admin USD / FX</th><th>Status</th><th>Reference</th><th>Manage</th></tr></thead>
                <tbody>
                @forelse($transactions as $tx)
                    <tr class="{{ $tx->status === 'voided' ? 'is-voided' : '' }}">
                        <td>{{ optional($tx->transaction_date)->format('d M Y') }}</td>
                        <td><b>{{ match($tx->type) {'deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit Paid','loss'=>'Loss','fee'=>'Fee',default=>'Adjustment'} }}</b>@if($tx->description)<small>{{ $tx->description }}</small>@endif</td>
                        <td class="{{ in_array($tx->type,['deposit','profit']) ? 'pi-positive' : (in_array($tx->type,['withdrawal','loss','fee']) ? 'pi-negative' : '') }}">{{ $currency }} {{ number_format((float)$tx->amount,2) }}</td>
                        <td>
                            @if($tx->usd_amount !== null)
                                @if($tx->type === 'withdrawal' && !$isUsd)
                                    <b>Settlement USD {{ number_format((float)($tx->settlement_usd_amount ?? $tx->usd_amount),2) }}</b>
                                    <small>Principal basis USD {{ number_format((float)($tx->principal_usd_basis ?? abs((float)$tx->usd_net_contributions_effect)),2) }}</small>
                                    <small class="{{ (float)($tx->fx_gain_loss_usd ?? 0)>=0?'pi-positive':'pi-negative' }}">FX {{ (float)($tx->fx_gain_loss_usd ?? 0)>=0?'gain +':'loss ' }}USD {{ number_format(abs((float)($tx->fx_gain_loss_usd ?? 0)),2) }}</small>
                                @else
                                    <b>USD {{ number_format((float)$tx->usd_amount,2) }}</b>
                                    @if(!$isUsd)<small>1 {{ $currency }} = USD {{ number_format((float)$tx->fx_rate_to_usd,6) }}</small>@endif
                                @endif
                            @else<span class="pi-status draft">FX required</span>@endif
                        </td>
                        <td><span class="pi-status {{ $tx->status }}">{{ $tx->status === 'voided' ? 'corrected' : $tx->status }}</span></td>
                        <td>{{ $tx->reference ?: '—' }}</td>
                        <td class="pi-entry-manage">
                            @if($tx->status !== 'voided')
                                <details class="pi-entry-editor"><summary class="pi-mini-btn">Edit</summary>
                                    <form method="POST" action="{{ route('admin.private-investors.transactions.update',$tx) }}" class="pi-entry-edit-form">@csrf @method('PATCH')
                                        <div class="pi-entry-edit-grid">
                                            <label>Type<select name="type" required>@foreach(['deposit'=>'Investment','withdrawal'=>'Capital Withdrawal','profit'=>'Profit Paid','loss'=>'Loss','fee'=>'Fee','adjustment'=>'Adjustment'] as $value=>$label)<option value="{{ $value }}" @selected($tx->type === $value)>{{ $label }}</option>@endforeach</select></label>
                                            <label>Amount {{ $currency }}<input type="number" step="0.01" name="amount" value="{{ $tx->amount }}" required></label>
                                            <label>Date<input type="date" name="transaction_date" value="{{ optional($tx->transaction_date)->toDateString() }}" required></label>
                                            @if(!$isUsd)<label>USD conversion / settlement rate<input type="number" step="0.00000001" min="0.00000001" name="fx_rate_to_usd" value="{{ $tx->fx_rate_to_usd }}" required></label>@else<input type="hidden" name="fx_rate_to_usd" value="1">@endif
                                            <label>Reference<input name="reference" value="{{ $tx->reference }}"></label>
                                        </div><label>Description<textarea name="description">{{ $tx->description }}</textarea></label><button class="pi-mini-btn good">Save Changes</button>
                                    </form>
                                </details>
                            @endif
                            @if($tx->status === 'draft')<form method="POST" action="{{ route('admin.private-investors.transactions.post',$tx) }}">@csrf<button class="pi-mini-btn good">Post</button></form>@endif
                            <form method="POST" action="{{ route('admin.private-investors.transactions.delete',$tx) }}" onsubmit="return confirm('Delete this entry? Posted balance and USD reporting effects will be reversed first.')">@csrf @method('DELETE')<button class="pi-mini-btn danger">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="pi-empty compact">No transactions recorded.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </article>

    <article class="pi71-card">
        <div class="pi71-card-head"><div><h3>Investor Notification Log</h3><p>Latest portfolio email attempts for this investor.</p></div><a href="{{ route('admin.enterprise.emails') }}">Email settings</a></div>
        <div class="pi71-email-list">
            @forelse($recentInvestorEmails as $email)
                <div><span><b>{{ $email->subject }}</b><small>{{ $email->created_at?->format('d M Y · H:i') }} · {{ $email->recipient_email }}</small></span><em class="{{ $email->status }}">{{ str_replace('_',' ',$email->status) }}</em></div>
            @empty
                <div class="pi-empty compact">No investor email attempts recorded yet.</div>
            @endforelse
        </div>
    </article>
</section>
<script>
(() => {
    const type = document.getElementById('transactionType');
    const agreement = document.getElementById('investmentAgreementFields');
    const amount = document.getElementById('transactionAmount');
    const amountLabel = document.getElementById('transactionAmountLabel');
    const currencyPrefix = document.getElementById('transactionCurrencyPrefix');
    const currencySelect = document.getElementById('transactionCurrency');
    const rate = document.getElementById('transactionRate');
    const preview = document.getElementById('investmentPreview');
    const start = document.getElementById('performanceStart');
    const fxBox = document.getElementById('transactionFxBox');
    const fxInput = document.getElementById('fxRateToUsd');
    const fxRateLabel = document.getElementById('fxRateLabel');
    const usdEquivalent = document.getElementById('usdEquivalent');
    const usdEquivalentLabel = document.getElementById('usdEquivalentLabel');
    const fxAccountingPreview = document.getElementById('fxAccountingPreview');
    const localPrincipal = {{ json_encode((float)$account->net_contributions) }};
    const usdPrincipal = {{ json_encode((float)($account->net_contributions_usd ?? ($isUsd?$account->net_contributions:0))) }};
    const fxButton = document.getElementById('fetchFxRate');
    let previousCurrency = (currencySelect?.value || @json($currency)).toUpperCase();

    function currency(){ return (currencySelect?.value || @json($currency)).toUpperCase(); }
    function money(v, code=currency()){ return code+' '+Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function currencyUi(){
        const code=currency();
        amountLabel.textContent='Amount in '+code;
        currencyPrefix.textContent=code;
        fxRateLabel.textContent='1 '+code+' equals USD';
        const usd=code==='USD';
        fxBox.hidden=usd;
        fxInput.required=!usd;
        if(usd) fxInput.value='1';
        else if(fxInput.value==='1' && code!==@json($currency)) fxInput.value='';
        agreementPreview();
        fxPreview();
    }
    function agreementPreview(){
        agreement.hidden = type.value !== 'deposit';
        if(type.value !== 'deposit') return;
        const a=Number(amount.value||0), r=Number(rate.value||0);
        if(!a || !r){ preview.textContent='Enter amount and monthly % to preview the target.'; return; }
        const full=a*r/100;
        let text='Full-month target: '+money(full);
        if(start.value){ const d=new Date(start.value+'T00:00:00'); const days=new Date(d.getFullYear(),d.getMonth()+1,0).getDate(); const active=days-d.getDate()+1; text+=' · First month estimate: '+money(full*active/days)+' ('+active+'/'+days+' days)'; }
        preview.innerHTML='<b>'+text+'</b>';
    }
    function fxPreview(){
        const code=currency();
        if(code==='USD'){
            if(usdEquivalent) usdEquivalent.textContent='USD '+Number(amount.value||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
            return;
        }
        if(!fxInput || !usdEquivalent) return;
        const a=Number(amount.value||0), r=Number(fxInput.value||0), settlement=a*r;
        usdEquivalent.textContent='USD '+settlement.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
        if(type.value==='withdrawal'){
            usdEquivalentLabel.textContent='USD settlement cost';
            if(a>localPrincipal){ fxAccountingPreview.innerHTML='<b>Capital withdrawal exceeds remaining principal of '+money(localPrincipal)+'.</b>'; return; }
            const basis=localPrincipal>0 ? (Math.abs(a-localPrincipal)<0.005 ? usdPrincipal : a*(usdPrincipal/localPrincipal)) : 0;
            const fx=basis-settlement;
            fxAccountingPreview.innerHTML='<b>Investor receives '+money(a)+' principal.</b> Admin basis: USD '+basis.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+' · Settlement: USD '+settlement.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+' · Estimated FX '+(fx>=0?'gain +':'loss -')+'USD '+Math.abs(fx).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
        } else {
            usdEquivalentLabel.textContent='USD equivalent';
            fxAccountingPreview.textContent= type.value==='deposit' ? 'This USD amount becomes the historical Admin principal basis. The investor principal remains '+money(a)+'.' : 'USD equivalent is locked for Admin reporting. Investor-facing amounts remain in '+code+'.';
        }
    }
    type.addEventListener('change',()=>{agreementPreview();fxPreview();});
    amount.addEventListener('input',()=>{agreementPreview();fxPreview();});
    currencySelect?.addEventListener('change',()=>{ const next=currency(); if(next!==previousCurrency && next!=='USD') fxInput.value=''; previousCurrency=next; currencyUi(); });
    rate?.addEventListener('input',agreementPreview);
    start?.addEventListener('change',agreementPreview);
    fxInput?.addEventListener('input',fxPreview);
    fxButton?.addEventListener('click',async()=>{
        const code=currency();
        if(code==='USD'){ fxInput.value='1'; fxPreview(); return; }
        fxButton.disabled=true; fxButton.textContent='Loading…';
        try{ const res=await fetch(@json(route('admin.private-investors.fx-quote'))+'?currency='+encodeURIComponent(code),{headers:{'Accept':'application/json'}}); const json=await res.json(); if(!res.ok) throw new Error(json.message||'Rate unavailable'); fxInput.value=Number(json.data.rate).toFixed(8); fxPreview(); } catch(e){ alert(e.message); } finally { fxButton.disabled=false; fxButton.textContent='Get current reference'; }
    });
    currencyUi();
})();
</script>
@endsection
