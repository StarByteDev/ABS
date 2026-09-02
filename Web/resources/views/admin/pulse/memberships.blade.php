@extends('admin.layout')
@section('title','Pulse Memberships & Payments')
@section('heading','Memberships & Payments')
@section('content')
<section class="enterprise-command-bar compact membership-command-bar">
    <div>
        <h2>Commercial membership control center</h2>
        <p>Publish payment details, review USDT membership requests, activate access, and manage coupons or gift vouchers from one controlled administration center.</p>
    </div>
    <div class="enterprise-command-actions"><a class="button button-ghost" href="{{ route('admin.pulse.plans') }}">Plans & entitlements</a><a class="button button-ghost" href="{{ route('admin.pulse.access') }}">Subscriptions</a></div>
</section>

<section class="enterprise-kpi-grid five membership-kpis">
    <article class="enterprise-kpi attention"><small>Awaiting review</small><strong>{{ number_format($summary['open']) }}</strong></article>
    <article class="enterprise-kpi"><small>Approved · 30d</small><strong>{{ number_format($summary['approved30']) }}</strong></article>
    <article class="enterprise-kpi"><small>Rejected · 30d</small><strong>{{ number_format($summary['rejected30']) }}</strong></article>
    <article class="enterprise-kpi"><small>Confirmed value · 30d</small><strong>{{ number_format($summary['confirmedValue30'],2) }}</strong><span>USDT-equivalent request value</span></article>
    <article class="enterprise-kpi"><small>Active promotions</small><strong>{{ number_format($summary['activePromotions']) }}</strong></article>
</section>

<section class="enterprise-section-grid equal membership-admin-grid">
    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>USDT payment settings</h2><p>These details are shown only during authenticated membership checkout.</p></div><span class="admin-status {{ $commerce['requests_enabled']?'good':'muted' }}">{{ $commerce['requests_enabled']?'Requests enabled':'Requests paused' }}</span></div>
        <form method="POST" action="{{ route('admin.pulse.memberships.settings') }}" class="enterprise-form-grid">@csrf @method('PUT')
            <label>Membership requests<select name="membership_requests_enabled"><option value="true" @selected($commerce['requests_enabled'])>Enabled</option><option value="false" @selected(!$commerce['requests_enabled'])>Paused</option></select></label>
            <label>USDT network<input name="usdt_network" value="{{ $commerce['network'] }}" maxlength="80" placeholder="Set the exact network users must select"></label>
            <label class="full">USDT receiving wallet<input name="usdt_wallet_address" value="{{ $commerce['wallet_address'] }}" maxlength="500" autocomplete="off" placeholder="Publish the wallet address used for membership payments"></label>
            <label class="full">Customer payment instructions<textarea name="usdt_payment_instructions" rows="3" maxlength="2000">{{ $commerce['payment_instructions'] }}</textarea></label>
            <label>Payment proof<select name="payment_proof_required"><option value="false" @selected(!$commerce['proof_required'])>Transaction reference required; proof optional</option><option value="true" @selected($commerce['proof_required'])>Transaction reference + proof required</option></select></label>
            <label>Coupons & vouchers<select name="promotion_codes_enabled"><option value="true" @selected($commerce['promotions_enabled'])>Enabled</option><option value="false" @selected(!$commerce['promotions_enabled'])>Disabled</option></select></label>
            <label>New-user Trial assignment<select name="trial_auto_assign_enabled"><option value="true" @selected($commerce['trial_auto_assign_enabled'])>Enabled</option><option value="false" @selected(!$commerce['trial_auto_assign_enabled'])>Disabled</option></select></label>
            <label>Public Trial banner<select name="trial_banner_enabled"><option value="true" @selected($commerce['trial_banner_enabled'])>Show</option><option value="false" @selected(!$commerce['trial_banner_enabled'])>Hide</option></select></label>
            <label>Trial duration (days)<input type="number" name="trial_duration_days" min="1" max="365" value="{{ $commerce['trial_duration_days'] }}"></label>
            <div class="full admin-page-actions"><button class="button button-primary">Save membership settings</button></div>
        </form>
    </article>

    <article class="enterprise-surface membership-policy-card">
        <div class="enterprise-section-head"><div><h2>Activation governance</h2><p>Commercial access stays separated from trading safety controls.</p></div></div>
        <div class="enterprise-metric-list">
            <div><span>Payment collection</span><b>Manual USDT transfer</b></div>
            <div><span>Standard activation</span><b>Administrator verification</b></div>
            <div><span>Gift voucher option</span><b>Manual or auto-activate</b></div>
            <div><span>Plan entitlement source</span><b>Plans & Entitlements</b></div>
            <div><span>Per-user restrictions</span><b>User 360° / Subscriptions</b></div>
        </div>
        <div class="enterprise-callout"><b>Safety boundary</b><p>Activating a membership never bypasses Pulse Live or automatic trading safety gates. Plan permissions remain the maximum access envelope.</p></div>
    </article>
</section>

<section class="enterprise-surface membership-review-surface">
    <div class="enterprise-section-head membership-queue-head">
        <div>
            <div class="membership-section-kicker">SUBSCRIPTION DECISIONS</div>
            <h2>Membership request queue</h2>
            <p>Review the subscriber, plan, payment evidence and requested access period, then approve or reject the request with a clear decision remark.</p>
        </div>
        <div class="membership-queue-summary">
            <span class="admin-status warn">{{ number_format($summary['open']) }} awaiting decision</span>
        </div>
    </div>

    <form method="GET" class="enterprise-filter-grid subscriptions membership-filters membership-filters-premium">
        <label>Search<input name="q" value="{{ request('q') }}" placeholder="Search name or email"></label>
        <label>Plan<select name="plan"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string)request('plan')===(string)$plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(['submitted','under_review','approved','rejected','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
        <div class="filter-actions"><button class="button button-primary">Apply filters</button><a class="button button-ghost" href="{{ route('admin.pulse.memberships') }}">Clear</a></div>
    </form>

    @if($pendingMembershipRequests->isNotEmpty())
        <div class="membership-pending-heading">
            <div><b>Pending administrator review</b><span>Approve only after the request and payment details have been checked.</span></div>
        </div>
        <div class="membership-review-queue">
        @foreach($pendingMembershipRequests as $item)
            <article class="membership-decision-card">
                <div class="membership-decision-topbar">
                    <div class="membership-request-id">
                        <span>REQUEST #{{ $item->id }}</span>
                        <b>{{ $item->plan?->name ?? 'Deleted plan' }}</b>
                    </div>
                    <span class="admin-status warn">{{ ucfirst(str_replace('_',' ',$item->status)) }}</span>
                </div>

                <div class="membership-decision-grid">
                    <div class="membership-decision-details">
                        <div class="membership-user-chip">
                            <span class="membership-avatar">{{ strtoupper(substr($item->user?->name ?? 'U',0,1)) }}</span>
                            <div>
                                <a href="{{ $item->user ? route('admin.users.show',$item->user) : '#' }}"><b>{{ $item->user?->name ?? 'Deleted user' }}</b></a>
                                <small>{{ $item->user?->email ?: 'No email available' }}</small>
                            </div>
                        </div>

                        <div class="membership-fact-grid">
                            <div><span>Plan</span><b>{{ $item->plan?->name ?? 'Deleted plan' }}</b></div>
                            <div><span>Amount</span><b>{{ $item->currency }} {{ number_format((float)$item->final_amount,2) }}</b></div>
                            <div><span>Requested access</span><b>{{ number_format((int)$item->activation_days) }} days</b></div>
                            <div><span>Submitted</span><b>{{ $item->created_at?->format('d M Y · H:i') }}</b></div>
                            <div><span>Promotion</span><b>{{ $item->promotion_code_snapshot ?: 'None' }}</b></div>
                            <div><span>Discount</span><b>{{ (float)$item->discount_amount > 0 ? $item->currency.' '.number_format((float)$item->discount_amount,2) : 'None' }}</b></div>
                        </div>
                    </div>

                    <div class="membership-payment-review">
                        <div class="membership-review-block-title">Payment verification</div>
                        <div class="membership-payment-status {{ (float)$item->final_amount <= 0 ? 'complimentary' : ($item->payment_reference ? 'provided' : 'missing') }}">
                            <span>{{ (float)$item->final_amount <= 0 ? 'No transfer required' : ($item->payment_reference ? 'Reference supplied' : 'Reference missing') }}</span>
                            <b>{{ $item->payment_reference ?: ((float)$item->final_amount <= 0 ? 'Complimentary / promotion request' : 'Not supplied') }}</b>
                        </div>
                        <dl class="membership-payment-meta">
                            <div><dt>Network</dt><dd>{{ $item->network ?: '—' }}</dd></div>
                            <div><dt>Proof</dt><dd>@if($item->payment_proof_path)<a class="membership-proof-link" href="{{ route('admin.pulse.membership-requests.proof',$item) }}">Open payment proof ↗</a>@else<span>No file attached</span>@endif</dd></div>
                        </dl>
                        @if($item->user_notes)
                            <div class="membership-user-note"><span>User note</span><p>{{ $item->user_notes }}</p></div>
                        @endif
                    </div>

                    <div class="membership-decision-actions">
                        <div class="membership-review-block-title">Administrator decision</div>
                        <p class="membership-decision-help">Your decision is logged. Rejection remarks are sent to the user; approval remarks are included in the activation record and email when provided.</p>

                        <form method="POST" action="{{ route('admin.pulse.membership-requests.approve',$item) }}" class="membership-approve-form">
                            @csrf
                            <label>Access duration
                                <div class="membership-days-input"><input type="number" name="activation_days" min="1" max="3650" value="{{ $item->activation_days }}" required><span>days</span></div>
                            </label>
                            <label>Approval remarks
                                <textarea name="admin_notes" rows="3" maxlength="2000" placeholder="Example: Payment verified. Activate Pulse Professional for 30 days." required></textarea>
                            </label>
                            <button class="button membership-approve-button button-wide" onclick="return confirm('Approve this subscription request and activate Pulse access?')">✓ Approve & activate subscription</button>
                        </form>

                        <form method="POST" action="{{ route('admin.pulse.membership-requests.reject',$item) }}" class="membership-decline-form">
                            @csrf
                            <label>Rejection reason
                                <textarea name="admin_notes" rows="3" maxlength="2000" placeholder="Explain clearly what the user needs to correct or why the request cannot be approved." required></textarea>
                            </label>
                            <button class="button membership-reject-button button-wide" onclick="return confirm('Reject this subscription request? The user will receive the reason by email.')">Reject request</button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
        </div>
    @elseif(!request('status') || in_array(request('status'), ['submitted','under_review'], true))
        <div class="membership-empty-queue">
            <span>✓</span><div><b>No subscription requests are waiting for review.</b><p>New submitted requests will appear here with payment evidence and one-step approval controls.</p></div>
        </div>
    @endif

    <div class="membership-history-head">
        <div><b>Request history</b><span>Reviewed, rejected, cancelled and filtered requests.</span></div>
    </div>
    <div class="enterprise-table-wrap membership-history-table-wrap"><table class="enterprise-table membership-request-table membership-history-table"><thead><tr><th>Request</th><th>User</th><th>Plan</th><th>Payment</th><th>Submitted</th><th>Status</th><th>Decision</th></tr></thead><tbody>
    @forelse($membershipRequests as $item)
        <tr>
            <td><b>#{{ $item->id }}</b><small>{{ $item->activation_days }} days</small></td>
            <td>@if($item->user)<a href="{{ route('admin.users.show',$item->user) }}"><b>{{ $item->user->name }}</b></a><small>{{ $item->user->email }}</small>@else<b>Deleted user</b>@endif</td>
            <td><b>{{ $item->plan?->name ?? 'Deleted plan' }}</b><small>{{ $item->currency }} {{ number_format((float)$item->final_amount,2) }}</small></td>
            <td><b>{{ $item->payment_reference ?: ((float)$item->final_amount <= 0 ? 'No transfer required' : 'Not supplied') }}</b><small>{{ $item->network ?: '—' }}</small></td>
            <td>{{ $item->created_at?->format('d M Y H:i') }}</td>
            <td><span class="admin-status {{ $item->status==='approved'?'good':($item->status==='rejected'?'danger':'muted') }}">{{ ucfirst(str_replace('_',' ',$item->status)) }}</span></td>
            <td><b>{{ $item->reviewer?->name ?: '—' }}</b><small>{{ $item->reviewed_at?->format('d M Y H:i') ?: '—' }}</small>@if($item->admin_notes)<small class="membership-history-note">{{ $item->admin_notes }}</small>@endif</td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="membership-history-empty">No closed requests match the current filters.</div></td></tr>
    @endforelse
    </tbody></table></div>
    <div class="enterprise-pagination">{{ $membershipRequests->links() }}</div>
</section>

<section class="enterprise-section-grid two-one membership-promo-grid">
    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Coupons & gift vouchers</h2><p>Create controlled codes for discounts, complimentary access, campaigns or direct member invitations.</p></div></div>
        <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Code</th><th>Type</th><th>Benefit</th><th>Plan</th><th>Uses</th><th>Validity</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($promotions as $promotion)
            <tr><td><b>{{ $promotion->code }}</b><small>{{ $promotion->label ?: '—' }}</small></td><td>{{ $promotion->type==='gift_voucher'?'Gift voucher':'Coupon' }}</td><td>{{ $promotion->displayBenefit() }}@if($promotion->access_days)<small>{{ $promotion->access_days }} access days</small>@endif</td><td>{{ $promotion->plan?->name ?? 'Any public plan' }}@if($promotion->assignee)<small>Assigned: {{ $promotion->assignee->email }}</small>@else<small>General code</small>@endif</td><td>{{ $promotion->redemptions_count }}{{ $promotion->max_uses>0?' / '.$promotion->max_uses:'' }}</td><td>{{ $promotion->valid_until?->format('d M Y') ?? 'No expiry' }}</td><td><span class="admin-status {{ $promotion->is_active?'good':'muted' }}">{{ $promotion->is_active?'Active':'Disabled' }}</span></td><td><details class="membership-review-pop"><summary>Edit →</summary><div class="membership-review-panel promo-editor"><form method="POST" action="{{ route('admin.pulse.promotions.update',$promotion) }}">@csrf @method('PUT') @include('admin.pulse.partials.promotion-fields',['promotion'=>$promotion,'plans'=>$plans])<button class="button button-primary button-wide">Save code</button></form><form method="POST" action="{{ route('admin.pulse.promotions.destroy',$promotion) }}" class="membership-reject-form">@csrf @method('DELETE')<button class="button button-ghost button-wide" onclick="return confirm('Delete this unused code?')">Delete unused code</button></form></div></details></td></tr>
        @empty<tr><td colspan="8">No coupons or gift vouchers created.</td></tr>@endforelse
        </tbody></table></div>
    </article>
    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Create promotion</h2><p>Codes can apply to one plan or all public plans.</p></div></div>
        <form method="POST" action="{{ route('admin.pulse.promotions.store') }}" class="enterprise-form-grid">@csrf @include('admin.pulse.partials.promotion-fields',['promotion'=>null,'plans'=>$plans])<div class="full admin-page-actions"><button class="button button-primary button-wide">Create code</button></div></form>
    </article>
</section>
@endsection
