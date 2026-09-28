@extends('admin.layout')
@section('title',$account->user->name.' — Investment Setup')
@section('heading',$account->user->name)
@section('description','Set the investor capital agreement once. ABS then builds the monthly and daily performance schedule automatically.')
@section('page-actions')<a class="pi-btn" href="{{ route('admin.private-investors.show',$account) }}">Account Summary</a>@endsection
@section('content')
<section class="pi-shell pi-v1570 pi-v1571 pi-v1573">
    @include('admin.private-investors._account-tabs')
    @php
        $activeTerm = $term && $term->status === 'active';
        $rate = (float)($term?->monthly_target_rate ?? 0);
        $effective = $term?->effective_from?->toDateString() ?? $firstInvestment?->transaction_date?->toDateString() ?? today()->toDateString();
        $fullTarget = round((float)$account->net_contributions * ($rate/100),2);
        $agreementPosted = (float)($agreement['posted_total'] ?? 0);
    @endphp

    <div class="pi-v1570-hero">
        <div>
            <span class="pi-kicker">Private Investor</span>
            <h2>Investment Agreement</h2>
            <p>Capital, start date and the agreed monthly performance rate are controlled here. You should not need to build every month manually.</p>
        </div>
        <div class="pi-v1570-status {{ $activeTerm?'good':'warn' }}"><i></i>{{ $activeTerm?'Agreement Active':'Setup Required' }}</div>
    </div>

    <article class="pi71-card pi73-currency-card">
        <div class="pi71-card-head"><div><h3>Principal Currency</h3><p>The investor receives principal back in this currency. Admin USD conversion is separate.</p></div><span class="pi71-currency-badge">{{ $account->currency }}</span></div>
        @if($currencyEditable)
            <form method="POST" action="{{ route('admin.private-investors.accounts.currency.update',$account) }}" class="pi73-currency-form">@csrf @method('PATCH')
                <label><span>Investor principal currency</span><select name="currency" required>@foreach($supportedCurrencies as $code)<option value="{{ $code }}" @selected($account->currency===$code)>{{ $code }}</option>@endforeach</select><small>Change before the first financial entry. The currency locks when financial history begins.</small></label>
                <button class="pi-btn primary">Save Currency</button>
            </form>
        @else
            <div class="pi73-currency-lock"><b>Currency locked: {{ $account->currency }}</b><span>{{ $currencyLockMessage }}</span></div>
        @endif
    </article>

    <div class="pi-v1570-kpis">
        <article><span>Net Invested Capital</span><strong>{{ $account->currency }} {{ number_format((float)$account->net_contributions,2) }}</strong><small>Confirmed capital</small></article>
        <article><span>Agreed Monthly Rate</span><strong>{{ $term ? number_format($rate,2).'%' : 'Not set' }}</strong><small>{{ $term?->effective_from?->format('d M Y') ?: 'Choose a start date' }}</small></article>
        <article><span>Full-Month Target</span><strong>{{ $account->currency }} {{ number_format($fullTarget,2) }}</strong><small>At current invested capital</small></article>
        <article><span>Performance Accrued</span><strong class="pi-positive">{{ $account->currency }} {{ number_format($agreementPosted,2) }}</strong><small>Provisional schedule to date</small></article>
    </div>

    <div class="pi-v1570-main-grid">
        <article class="pi-v1570-panel">
            <div class="pi-v1570-panel-head">
                <div><h3>Agreement Settings</h3><p>Set once, then ABS calculates calendar-month targets and varied daily progress.</p></div>
            </div>
            <form method="POST" action="{{ route('admin.private-investors.investment-terms.store',$account) }}" class="pi-v1570-form" id="investmentTermsForm">
                @csrf
                <input type="hidden" name="auto_payout" value="1">
                <div class="pi-v1570-form-grid">
                    <label><span>Performance starts</span><input id="termEffective" type="date" name="effective_from" value="{{ old('effective_from',$effective) }}" required><small>Normally the date funds became active.</small></label>
                    <label><span>Agreed monthly profit %</span><div class="pi-input-suffix"><input id="termRate" type="number" step="0.01" min="0" max="50" name="monthly_target_rate" value="{{ old('monthly_target_rate',$term?->monthly_target_rate ?? '') }}" placeholder="Monthly percentage" required><b>%</b></div><small>Applied to the weighted investor principal for each calendar month.</small></label>
                    <label><span>Status</span><select name="status"><option value="active" @selected(($term?->status ?? 'active')==='active')>Active</option><option value="paused" @selected($term?->status==='paused')>Paused</option><option value="closed" @selected($term?->status==='closed')>Closed</option></select><small>Pause stops new daily posting without removing history.</small></label>
                    <label><span>Profit payout timing</span><select name="payout_day"><option value="" @selected(!$term?->payout_day)>Month end</option>@for($d=1;$d<=28;$d++)<option value="{{ $d }}" @selected((int)$term?->payout_day===$d)>Day {{ $d }} of following month</option>@endfor</select><small>ABS automatically pays the completed month on this schedule and publishes its statement.</small></label>
                    <label><span>Current invested capital</span><div class="pi-readonly-value">{{ $account->currency }} {{ number_format((float)$account->net_contributions,2) }}</div><small>Controlled by posted Investment / Withdrawal transactions.</small></label>
                </div>
                <label><span>Internal note</span><textarea name="notes" placeholder="Optional agreement or administration note">{{ old('notes',$term?->notes) }}</textarea></label>
                <div class="pi-v1570-actions"><button class="pi-btn primary">Save Agreement & Build Schedule</button></div>
            </form>
        </article>

        <aside class="pi-v1570-panel pi-v1570-preview">
            <div class="pi-v1570-panel-head"><div><h3>How ABS Calculates It</h3><p>Simple, automatic and date-aware.</p></div></div>
            <div class="pi-v1570-calc-row"><span>Full month at current capital</span><b id="fullMonthPreview">{{ $account->currency }} {{ number_format($fullTarget,2) }}</b></div>
            <div class="pi-v1570-calc-row"><span>First month</span><b id="firstMonthPreview">Calculated after save</b></div>
            <div class="pi-v1570-calc-row"><span>Daily pattern</span><b>Varied automatically</b></div>
            <div class="pi-v1570-calc-row"><span>Profit payout</span><b>{{ $term?->payout_day ? 'Day '.$term->payout_day.' next month' : 'Automatic at month end' }}</b></div>
            <div class="pi-note"><strong>Mid-month investment:</strong> the first calendar month is prorated from the effective date. From the next full month, the full agreed monthly rate applies to invested capital. Capital added or withdrawn later is automatically weighted by the days it was active.</div>
        </aside>
    </div>

    @if($term)
    <article class="pi-v1570-panel">
        <div class="pi-v1570-panel-head"><div><h3>Generated Performance Schedule</h3><p>ABS has created {{ number_format((int)($agreement['months'] ?? 0)) }} month(s) from the agreement start through the current month.</p></div><a class="pi-btn cyan" href="{{ route('admin.private-investors.account-performance',$account) }}">Open Monthly Progress</a></div>
        <div class="pi-v1570-summary-strip">
            <div><span>Total scheduled target</span><strong>{{ $account->currency }} {{ number_format((float)($agreement['target_total'] ?? 0),2) }}</strong></div>
            <div><span>Accrued provisional performance</span><strong class="pi-positive">{{ $account->currency }} {{ number_format((float)($agreement['posted_total'] ?? 0),2) }}</strong></div>
            <div><span>Current-month target</span><strong>{{ $account->currency }} {{ number_format((float)($agreement['current_target'] ?? 0),2) }}</strong></div>
            <div><span>Indicative value</span><strong>{{ $account->currency }} {{ number_format((float)($agreement['indicative_value'] ?? $account->current_value),2) }}</strong></div>
        </div>
    </article>
    @endif
</section>
<script>
(() => {
    const capital = {{ json_encode((float)$account->net_contributions) }};
    const currency = @json($account->currency);
    const rate = document.getElementById('termRate');
    const date = document.getElementById('termEffective');
    const full = document.getElementById('fullMonthPreview');
    const first = document.getElementById('firstMonthPreview');
    const money = v => currency + ' ' + Number(v || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
    function refresh(){
        const r = Number(rate.value || 0);
        const fullTarget = capital * r / 100;
        full.textContent = money(fullTarget);
        if(!date.value || !r){ first.textContent = 'Enter rate + start date'; return; }
        const d = new Date(date.value+'T00:00:00');
        const days = new Date(d.getFullYear(), d.getMonth()+1, 0).getDate();
        const active = days - d.getDate() + 1;
        first.textContent = money(fullTarget * active / days) + ' (' + active + '/' + days + ' days)';
    }
    rate.addEventListener('input',refresh); date.addEventListener('change',refresh); refresh();
})();
</script>
@endsection
