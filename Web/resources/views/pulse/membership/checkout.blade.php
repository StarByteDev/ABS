@extends('pulse.layout')
@section('title','Pulse Package Payment — Alpha Block Solutions')
@section('heading','Pulse Package Payment')
@section('content')
<section class="container membership-checkout-shell membership-v1563">
    <header class="membership-checkout-head">
        <span class="membership-eyebrow">ABS PULSE · SECURE PACKAGE PAYMENT</span>
        <h1>{{ $plan->name }}</h1>
        <p>Transfer the exact package amount, submit the transaction reference, and track verification from your ABS Pulse account.</p>
    </header>

    <div class="membership-checkout-grid">
        <article class="membership-checkout-card membership-payment-card">
            <div class="membership-checkout-section-title">
                <div><small>PAYMENT SUMMARY</small><h2>Direct USDT transfer</h2></div>
                <span class="membership-secure-chip">SECURE VERIFICATION</span>
            </div>

            <div class="membership-price-breakdown">
                @if((float)($quote['discount_amount'] ?? 0) > 0)
                    <div><span>Package price</span><b>{{ number_format((float)$quote['base_amount'],2) }} {{ $quote['currency'] }}</b></div>
                    <div class="discount"><span>Discount</span><b>−{{ number_format((float)$quote['discount_amount'],2) }} {{ $quote['currency'] }}</b></div>
                @endif
                <div class="total"><span>Amount to transfer</span><b>{{ number_format((float)$quote['final_amount'],2) }} {{ $quote['currency'] }}</b></div>
                <div><span>Access after approval</span><b>{{ (int)$quote['activation_days'] }} days</b></div>
            </div>

            <section class="membership-wallet-block">
                <label>Transfer destination</label>
                <div class="membership-wallet-network"><span>Network</span><b>{{ $commerce['network'] ?: 'Not configured' }}</b></div>
                <div class="membership-wallet-copy">
                    <code id="pulse-wallet-address">{{ $commerce['wallet_address'] ?: 'Wallet not configured' }}</code>
                    @if($commerce['wallet_address'])<button type="button" data-copy-wallet>Copy</button>@endif
                </div>
                <p>Use the network shown above and send the exact amount. Network or amount mismatches can delay verification.</p>
            </section>

            <section class="membership-transfer-steps" aria-label="Payment steps">
                <article><span>1</span><div><b>Transfer</b><small>Send {{ number_format((float)$quote['final_amount'],2) }} {{ $quote['currency'] }} to the wallet shown above.</small></div></article>
                <article><span>2</span><div><b>Keep the TXID</b><small>Copy the blockchain transaction ID after the transfer is submitted.</small></div></article>
                <article><span>3</span><div><b>Submit for verification</b><small>ABS reviews the transaction and activates your package after confirmation.</small></div></article>
            </section>

            @php
                $paymentInstructionText = trim((string)($commerce['payment_instructions'] ?? ''));
                $paymentInstructionItems = $paymentInstructionText !== ''
                    ? array_values(array_filter(array_map('trim', preg_split('/(?=\b\d+\.\s+)/', $paymentInstructionText) ?: [])))
                    : [];
            @endphp
            @if($paymentInstructionText !== '')
                <div class="membership-payment-note">
                    <b>Payment note</b>
                    @if(count($paymentInstructionItems) > 1)
                        <ol>@foreach($paymentInstructionItems as $instruction)<li>{{ preg_replace('/^\d+\.\s*/', '', $instruction) }}</li>@endforeach</ol>
                    @else
                        <p>{{ $paymentInstructionText }}</p>
                    @endif
                </div>
            @endif

            @if(!empty($planHighlights))
                <section class="membership-plan-includes">
                    <small>YOUR {{ strtoupper($plan->name) }} ACCESS</small>
                    <div class="membership-includes-grid">
                        @foreach($planHighlights as $item)<span>✓ {{ $item }}</span>@endforeach
                    </div>
                </section>
            @endif
        </article>

        <article class="membership-checkout-card submit-card">
            <small>VERIFY YOUR PAYMENT</small>
            <h2>Submit transaction details</h2>
            <p>After submission, your request appears in Payment History with live verification status.</p>

            <form method="POST" action="{{ route('pulse.membership.store') }}" enctype="multipart/form-data" class="membership-checkout-form">
                @csrf
                <input type="hidden" name="pulse_plan_id" value="{{ $plan->id }}">
                <label>USDT transaction ID / hash
                    <input name="payment_reference" value="{{ old('payment_reference') }}" required maxlength="190" placeholder="Paste the blockchain TXID">
                </label>
                @if($commerce['promotions_enabled'])
                    <label>Promotion code <small>Optional</small><input name="promotion_code" value="{{ old('promotion_code') }}" placeholder="Enter code"></label>
                @endif
                <label>Payment proof <small>{{ $commerce['proof_required']?'Required':'Optional' }}</small>
                    <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf" @required($commerce['proof_required'])>
                </label>
                <label>Note <small>Optional</small><textarea name="user_notes" rows="3" placeholder="Anything the verification team should know">{{ old('user_notes') }}</textarea></label>
                <label class="membership-risk-ack">
                    <input type="checkbox" name="risk_acknowledgement" value="1" required @checked(old('risk_acknowledgement'))>
                    <span>I confirm the wallet address and network before transfer and acknowledge the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms</a>, <a href="{{ route('legal.risk') }}" target="_blank" rel="noopener">Risk Disclosure</a> and <a href="{{ route('legal.disclaimer') }}" target="_blank" rel="noopener">Market Disclaimer</a>.</span>
                </label>
                <button class="button button-primary membership-submit-button">Submit Payment for Verification</button>
            </form>

            <div class="membership-verification-flow">
                <span><i></i><b>Submitted</b><small>Request created immediately</small></span>
                <span><i></i><b>Verification</b><small>Transaction confirmation in progress</small></span>
                <span><i></i><b>Activation</b><small>Access starts after approval</small></span>
            </div>
        </article>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('click', async function(event){
    const button = event.target.closest('[data-copy-wallet]');
    if (!button) return;
    const value = document.getElementById('pulse-wallet-address')?.textContent?.trim() || '';
    if (!value) return;
    try { await navigator.clipboard.writeText(value); button.textContent = 'Copied'; setTimeout(()=>button.textContent='Copy',1600); }
    catch (error) { button.textContent = 'Copy manually'; }
});
</script>
@endpush
