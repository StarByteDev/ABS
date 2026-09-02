@extends('pulse.layout')
@section('title',$plan->name.' — Alpha Block Solutions')
@section('heading','Plan Checkout')
@section('content')
<section class="container membership-checkout-shell">
    <div class="membership-checkout-head">
        <a href="{{ route('pulse.plans') }}" class="membership-back-link">← Back to Pulse plans</a>
        <span class="membership-eyebrow">PULSE PLAN</span>
        <h1>{{ $plan->name }}</h1>
        <p>{{ $plan->description }}</p>
    </div>

    <?php if ($errors->any()): ?><div class="flash flash-error"><strong>Please review:</strong> {{ $errors->first() }}</div><?php endif; ?>

    <div class="membership-checkout-grid">
        <article class="membership-checkout-card primary-card">
            <div class="membership-checkout-section-title"><div><small>PLAN SUMMARY</small><h2>{{ $plan->name }}</h2></div><?php if ($plan->badge): ?><span class="membership-plan-badge">{{ $plan->badge }}</span><?php endif; ?></div>
            <div class="membership-price-breakdown">
                <div><span>Access period</span><b>{{ $quote['activation_days'] }} days</b></div>
                <div><span>Plan amount</span><b>{{ number_format($quote['base_amount'],2) }} {{ $quote['currency'] }}</b></div>
                <?php if ($promotion): ?><div class="discount"><span>{{ $promotion->type==='gift_voucher'?'Gift voucher':'Coupon' }} · {{ $promotion->code }}</span><b>-{{ number_format($quote['discount_amount'],2) }} {{ $quote['currency'] }}</b></div><?php endif; ?>
                <div class="total"><span>Amount due</span><b>{{ number_format($quote['final_amount'],2) }} {{ $quote['currency'] }}</b></div>
            </div>

            <?php if ($commerce['promotions_enabled']): ?>
            <form method="GET" action="{{ route('pulse.membership.checkout',$plan) }}" id="promotion" class="membership-promo-form">
                <label>Coupon or gift voucher</label>
                <div><input name="promo" value="{{ request('promo',$promotion?->code) }}" maxlength="80" placeholder="Enter code"><button class="button button-ghost">Apply</button></div>
                <?php if ($promotion): ?><small class="positive">{{ $promotion->label ?: 'Code accepted' }} · {{ $promotion->displayBenefit() }}</small><?php endif; ?>
                <?php if ($assignedPromotions->isNotEmpty()): ?>
                    <div class="membership-assigned-offers"><span>Available for your account:</span><?php foreach ($assignedPromotions as $offer): ?><a href="{{ route('pulse.membership.checkout',['plan'=>$plan,'promo'=>$offer->code]) }}" class="membership-offer-chip"><b>{{ $offer->code }}</b> · {{ $offer->displayBenefit() }}</a><?php endforeach; ?></div>
                <?php endif; ?>
            </form>
            <?php endif; ?>

            @php($pricePublished = !$plan->requires_payment || $quote['base_amount'] > 0 || ($promotion && $promotion->type === 'gift_voucher' && $promotion->discount_type === 'full'))
            <?php if ($plan->requires_payment && $quote['final_amount'] > 0 && $quote['base_amount'] > 0): ?>
                <div class="membership-wallet-block">
                    <div class="membership-checkout-section-title"><div><small>PAYMENT DETAILS</small><h2>Transfer USDT</h2></div><span class="membership-secure-chip">Secure checkout</span></div>
                    <?php if ($commerce['wallet_address'] && $commerce['network']): ?>
                        <div class="membership-wallet-network"><span>Network</span><b>{{ $commerce['network'] }}</b></div>
                        <label>USDT receiving wallet</label>
                        <div class="membership-wallet-copy"><code id="membership-wallet">{{ $commerce['wallet_address'] }}</code><button type="button" data-copy-wallet>Copy</button></div>
                        <p>{{ $commerce['payment_instructions'] }}</p>
                    <?php else: ?>
                        <div class="membership-payment-unavailable"><b>Payment details are being updated.</b><p>Please return shortly or contact {{ config('brand.support_email') }} for plan assistance.</p></div>
                    <?php endif; ?>
                </div>
            <?php elseif (!$pricePublished): ?>
                <div class="membership-payment-unavailable"><b>Plan rate is being updated.</b><p>Please return shortly or contact {{ config('brand.support_email') }} for plan assistance.</p></div>
            <?php else: ?>
                <div class="membership-payment-unavailable good"><b>No USDT transfer is required for this request.</b><p>Submit the plan request below to continue.</p></div>
            <?php endif; ?>
        </article>

        <aside class="membership-checkout-card submit-card">
            <small>SUBMIT FOR ACTIVATION</small>
            <h2>Confirm your request</h2>
            <p>Pulse access is activated after the submitted details are verified.</p>
            <form method="POST" action="{{ route('pulse.membership.store') }}" enctype="multipart/form-data" class="membership-request-form">@csrf
                <input type="hidden" name="pulse_plan_id" value="{{ $plan->id }}">
                <?php if ($promotion): ?><input type="hidden" name="promotion_code" value="{{ $promotion->code }}"><?php endif; ?>
                <?php if ($plan->requires_payment && $quote['final_amount'] > 0 && $quote['base_amount'] > 0): ?>
                    <label>USDT transaction reference / hash<input name="payment_reference" value="{{ old('payment_reference') }}" maxlength="190" required placeholder="Paste the transaction reference"></label>
                    <label>Payment proof <?php if (!$commerce['proof_required']): ?><small>Optional</small><?php endif; ?><input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" @required($commerce['proof_required'])></label>
                <?php endif; ?>
                <label>Note <small>Optional</small><textarea name="user_notes" rows="3" maxlength="2000" placeholder="Add any detail relevant to this plan request">{{ old('user_notes') }}</textarea></label>
                @php($canSubmit = $pricePublished && (!$plan->requires_payment || $quote['final_amount'] <= 0 || ($commerce['wallet_address'] && $commerce['network'])))
                <button class="button button-primary button-wide membership-submit-button" @disabled(!$canSubmit)>Submit plan request</button>
            </form>
            <div class="membership-trust-list"><span>✓ Payment details are shown only after sign in</span><span>✓ Coupons and gift vouchers are validated securely</span><span>✓ Pulse access is linked to your ABS account</span></div>
        </aside>
    </div>
</section>
@push('scripts')
<script>
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-wallet]');
    if (!button) return;
    const text = document.getElementById('membership-wallet')?.textContent?.trim();
    if (!text) return;
    try { await navigator.clipboard.writeText(text); button.textContent = 'Copied'; setTimeout(() => button.textContent = 'Copy', 1600); } catch (_) {}
});
</script>
@endpush
@endsection
