@extends('layouts.app')
@section('title','Trading Calculators — Alpha Block Solutions')
@section('content')
<section class="page-hero container tools-hero">
    <h1>Plan the numbers before <span class="gradient-text">placing the trade.</span></h1>
    <p>Use these calculators to estimate position exposure, risk, fees and liquidation distance. Results are educational estimates and should be checked against your exchange before execution.</p>
</section>

<section class="container calculator-intro panel">
    <div><b>How to use the tools</b><span>Enter your actual planned values, review the calculation breakdown and keep the result with your trading plan.</span></div>
    <div><b>USD calculations</b><span>All generic money fields below are shown in U.S. dollars. Asset quantity is shown separately.</span></div>
    <div><b>No trade recommendation</b><span>The calculators do not assess strategy quality or guarantee an outcome.</span></div>
</section>

<section class="container calculator-grid calculator-grid-v126">
    <article class="panel calculator" data-calculator="profit">
        <div class="calculator-heading"><span>01</span><div><h2>Profit, Fees &amp; ROI</h2><p>Estimate long or short P/L after entry, exit and funding costs.</p></div></div>
        <div class="calculator-fields">
            <label>Direction<select data-field="direction"><option value="long">Long</option><option value="short">Short</option></select></label>
            <label>Entry price <input type="number" data-field="entry" min="0" step="any" placeholder="e.g. 65000"></label>
            <label>Exit price <input type="number" data-field="exit" min="0" step="any" placeholder="e.g. 65500"></label>
            <label>Margin used (USD) <input type="number" data-field="capital" min="0" step="any" placeholder="e.g. 1000"></label>
            <label>Leverage <input type="number" data-field="leverage" min="1" step="any" placeholder="e.g. 5"></label>
            <label>Entry fee % <input type="number" data-field="entryFee" min="0" step="any" placeholder="e.g. 0.02"></label>
            <label>Exit fee % <input type="number" data-field="exitFee" min="0" step="any" placeholder="e.g. 0.05"></label>
            <label>Funding / other cost (USD) <input type="number" data-field="funding" min="0" step="any" placeholder="Optional"></label>
        </div>
        <div class="calculator-actions"><button class="button button-primary" type="button" data-calculate>Calculate result</button><button class="button button-ghost" type="button" data-reset>Reset</button></div>
        <div class="result-box calculator-result" data-result aria-live="polite"><span>Enter the planned trade values to calculate notional exposure, quantity, gross P/L, fees, net P/L and ROI.</span></div>
    </article>

    <article class="panel calculator" data-calculator="position">
        <div class="calculator-heading"><span>02</span><div><h2>Risk-Based Position Size</h2><p>Size a position from account risk and stop-loss distance.</p></div></div>
        <div class="calculator-fields">
            <label>Account balance (USD) <input type="number" data-field="balance" min="0" step="any" placeholder="e.g. 10000"></label>
            <label>Risk per trade % <input type="number" data-field="risk" min="0" max="100" step="any" placeholder="e.g. 1"></label>
            <label>Entry price <input type="number" data-field="entry" min="0" step="any" placeholder="e.g. 65000"></label>
            <label>Stop price <input type="number" data-field="stop" min="0" step="any" placeholder="e.g. 64500"></label>
            <label>Planned leverage <input type="number" data-field="leverage" min="1" step="any" placeholder="Optional, e.g. 5"></label>
        </div>
        <div class="calculator-actions"><button class="button button-primary" type="button" data-calculate>Calculate position</button><button class="button button-ghost" type="button" data-reset>Reset</button></div>
        <div class="result-box calculator-result" data-result aria-live="polite"><span>Enter the account balance, risk percentage, entry and stop. The result will show risk amount, stop distance, quantity, notional and estimated margin.</span></div>
    </article>

    <article class="panel calculator" data-calculator="liquidation">
        <div class="calculator-heading"><span>03</span><div><h2>Liquidation Distance Estimate</h2><p>Estimate a simplified isolated-position liquidation area.</p></div></div>
        <div class="calculator-fields">
            <label>Direction<select data-field="direction"><option value="long">Long</option><option value="short">Short</option></select></label>
            <label>Entry price <input type="number" data-field="entry" min="0" step="any" placeholder="e.g. 65000"></label>
            <label>Leverage <input type="number" data-field="leverage" min="1" step="any" placeholder="e.g. 5"></label>
            <label>Maintenance margin % <input type="number" data-field="maintenance" min="0" step="any" placeholder="e.g. 0.5"></label>
        </div>
        <div class="calculator-actions"><button class="button button-primary" type="button" data-calculate>Estimate distance</button><button class="button button-ghost" type="button" data-reset>Reset</button></div>
        <div class="result-box calculator-result" data-result aria-live="polite"><span>Actual liquidation depends on exchange tiers, wallet balance, fees, funding and margin mode. Use this only as an initial estimate.</span></div>
    </article>
</section>

<section class="container tools-guidance-grid">
    <article class="panel"><h2>Before using leverage</h2><p>Confirm the invalidation point, maximum acceptable loss and available margin. Higher leverage reduces the distance to liquidation and can increase the effect of fees.</p></article>
    <article class="panel"><h2>Fee input guidance</h2><p>Enter each side separately. For example, an entry fee of 0.02% and exit fee of 0.05% should be entered in their own fields rather than combined.</p></article>
    <article class="panel"><h2>Final exchange check</h2><p>Review the exchange order preview, contract specification, maintenance-margin tier and official liquidation calculator before submitting an order.</p></article>
</section>
@endsection
