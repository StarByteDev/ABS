@extends('pulse.layout')
@section('title','Pulse Package Payment — Alpha Block Solutions')
@section('heading','Pulse Package Payment')
@section('content')
<section class="container membership-checkout-shell">
    <div class="membership-checkout-head"><span class="membership-eyebrow">DIRECT USDT · ADMIN VERIFIED</span><h1>{{ $plan->name }}</h1><p>Transfer the exact amount using the network below, then submit the blockchain transaction reference. Your package activates only after Admin verifies the transfer.</p></div>
    <div class="membership-checkout-grid">
        <article class="membership-checkout-card primary-card">
            <small>PACKAGE TOTAL</small><h2>{{ number_format((float)$quote['final_amount'],2) }} {{ $quote['currency'] }}</h2>
            <dl><div><dt>Access duration</dt><dd>{{ $quote['activation_days'] }} days</dd></div><div><dt>Network</dt><dd>{{ $commerce['network'] ?: 'Not configured' }}</dd></div><div><dt>Wallet</dt><dd style="word-break:break-all">{{ $commerce['wallet_address'] ?: 'Not configured' }}</dd></div></dl>
            <p>{{ $commerce['payment_instructions'] }}</p>
        </article>
        <article class="membership-checkout-card">
            <h2>Submit payment for verification</h2>
            <form method="POST" action="{{ route('pulse.membership.store') }}" enctype="multipart/form-data" class="membership-checkout-form">@csrf
                <input type="hidden" name="pulse_plan_id" value="{{ $plan->id }}">
                <label>USDT transaction ID / hash<input name="payment_reference" value="{{ old('payment_reference') }}" required maxlength="190" placeholder="Paste the blockchain TXID"></label>
                @if($commerce['promotions_enabled'])<label>Promotion code (optional)<input name="promotion_code" value="{{ old('promotion_code') }}"></label>@endif
                <label>Payment proof {{ $commerce['proof_required']?'(required)':'(optional)' }}<input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf" @required($commerce['proof_required'])></label>
                <label>Note to Admin (optional)<textarea name="user_notes" rows="3">{{ old('user_notes') }}</textarea></label>
                <label class="membership-risk-ack"><input type="checkbox" name="risk_acknowledgement" value="1" required @checked(old('risk_acknowledgement'))><span>I confirm the wallet address/network before transfer and acknowledge the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms</a>, <a href="{{ route('legal.risk') }}" target="_blank" rel="noopener">Risk Disclosure</a> and <a href="{{ route('legal.disclaimer') }}" target="_blank" rel="noopener">Market Disclaimer</a>.</span></label>
                <button class="button button-primary">Submit for Admin Verification</button>
            </form>
            <small class="pulse-legal-note">Do not submit until the transaction is sent. Admin will independently verify the TXID before activating access.</small>
        </article>
    </div>
</section>
@endsection
