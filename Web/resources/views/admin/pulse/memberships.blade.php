@extends('admin.layout')
@section('title','Package Payments')
@section('heading','Package Payments & Activation')
@section('description','Verify member USDT transfers, approve or reject package requests, and control the payment instructions shown at checkout.')
@section('content')
<div class="admin-kpi-grid">
    <article class="admin-kpi"><span>Awaiting Review</span><b>{{ number_format($summary['open']) }}</b><small>USDT package requests</small></article>
    <article class="admin-kpi"><span>Approved · 30 Days</span><b>{{ number_format($summary['approved30']) }}</b><small>activated packages</small></article>
    <article class="admin-kpi"><span>Approved Value · 30 Days</span><b>{{ number_format($summary['confirmedValue30'],2) }} USDT</b><small>verified transactions</small></article>
    <article class="admin-kpi"><span>Rejected · 30 Days</span><b>{{ number_format($summary['rejected30']) }}</b><small>declined requests</small></article>
</div>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>USDT checkout configuration</h2><p>Members transfer USDT directly, submit the blockchain transaction reference and optional proof, then wait for Admin verification. Approving a request activates the selected package.</p></div><span>{{ $commerce['requests_enabled'] ? 'ACCEPTING PAYMENTS' : 'PAUSED' }}</span></div>
    <form method="POST" action="{{ route('admin.pulse.memberships.settings') }}" class="admin-form-grid">@csrf @method('PUT')
        <label>Package payment requests<select name="membership_requests_enabled"><option value="true" @selected($commerce['requests_enabled'])>Enabled</option><option value="false" @selected(!$commerce['requests_enabled'])>Paused</option></select></label>
        <label>USDT network<input name="usdt_network" value="{{ $commerce['network'] }}" placeholder="TRC20"></label>
        <label class="full">USDT receiving wallet<input name="usdt_wallet_address" value="{{ $commerce['wallet_address'] }}" placeholder="Admin receiving wallet address"></label>
        <label class="full">Customer payment instructions<textarea name="usdt_payment_instructions" rows="3">{{ $commerce['payment_instructions'] }}</textarea></label>
        <label>Payment proof<select name="payment_proof_required"><option value="false" @selected(!$commerce['proof_required'])>Optional</option><option value="true" @selected($commerce['proof_required'])>Required</option></select></label>
        <label>Auto-assign Trial<select name="trial_auto_assign_enabled"><option value="true" @selected($commerce['trial_auto_assign_enabled'])>Enabled</option><option value="false" @selected(!$commerce['trial_auto_assign_enabled'])>Disabled</option></select></label>
        <label>Trial public banner<select name="trial_banner_enabled"><option value="true" @selected($commerce['trial_banner_enabled'])>Enabled</option><option value="false" @selected(!$commerce['trial_banner_enabled'])>Disabled</option></select></label>
        <label>Trial duration (days)<input type="number" min="1" max="365" name="trial_duration_days" value="{{ $commerce['trial_duration_days'] }}"></label>
        <div class="full"><button class="button button-primary">Save Payment Settings</button></div>
    </form>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Transactions awaiting verification</h2><p>Verify the TXID on the configured network before approving. Approval immediately activates or extends the requested Pulse package.</p></div><span>{{ number_format($pendingMembershipRequests->count()) }} VISIBLE</span></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Member</th><th>Package</th><th>Amount</th><th>Network</th><th>TXID / Proof</th><th>Submitted</th><th>Decision</th></tr></thead><tbody>
    @forelse($pendingMembershipRequests as $payment)
        <tr>
            <td><b>{{ $payment->user?->name }}</b><small>{{ $payment->user?->email }}</small></td>
            <td><b>{{ $payment->plan?->name ?? 'Plan unavailable' }}</b><small>{{ number_format((int)$payment->activation_days) }} days</small></td>
            <td><b>{{ number_format((float)$payment->final_amount,2) }} {{ $payment->currency }}</b></td>
            <td>{{ $payment->network ?: '—' }}</td>
            <td class="admin-payment-proof"><code>{{ $payment->payment_reference ?: '—' }}</code>@if($payment->payment_proof_path)<br><a href="{{ route('admin.pulse.membership-requests.proof',$payment) }}">View payment proof</a>@endif</td>
            <td>{{ $payment->created_at?->format('d M Y H:i') }}</td>
            <td><div class="admin-row-actions">
                <form method="POST" action="{{ route('admin.pulse.membership-requests.approve',$payment) }}" onsubmit="return confirm('Approve this verified USDT transaction and activate the package?')">@csrf
                    <input type="hidden" name="activation_days" value="{{ $payment->activation_days }}"><button class="button button-primary">Approve & Activate</button>
                </form>
                <form method="POST" action="{{ route('admin.pulse.membership-requests.reject',$payment) }}" onsubmit="return confirm('Reject this package payment request?')">@csrf
                    <input name="admin_notes" required placeholder="Reason for rejection"><button class="button button-ghost">Reject</button>
                </form>
            </div></td>
        </tr>
    @empty
        <tr><td colspan="7" class="admin-empty">No USDT package payment is waiting for review.</td></tr>
    @endforelse
    </tbody></table></div>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Payment history</h2><p>Approved, rejected and cancelled direct package payment requests.</p></div></div>
    <form method="GET" class="admin-filter-bar"><input name="q" value="{{ request('q') }}" placeholder="Member name or email"><select name="plan"><option value="">All packages</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string)request('plan')===(string)$plan->id)>{{ $plan->name }}</option>@endforeach</select><select name="status"><option value="">All statuses</option>@foreach(['approved','rejected','cancelled','submitted','under_review'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select><button class="button button-ghost">Apply</button></form>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Member</th><th>Package</th><th>USDT</th><th>TXID</th><th>Status</th><th>Reviewed</th><th>Admin</th></tr></thead><tbody>
    @forelse($membershipRequests as $payment)
        <tr><td>{{ $payment->user?->name }}<small>{{ $payment->user?->email }}</small></td><td>{{ $payment->plan?->name ?? '—' }}</td><td>{{ number_format((float)$payment->final_amount,2) }}</td><td class="admin-payment-proof"><code>{{ $payment->payment_reference ?: '—' }}</code></td><td><span class="status-pill {{ $payment->status==='approved'?'active':($payment->status==='rejected'?'rejected':'pending') }}">{{ strtoupper(str_replace('_',' ',$payment->status)) }}</span></td><td>{{ $payment->reviewed_at?->format('d M Y H:i') ?? '—' }}</td><td>{{ $payment->reviewer?->name ?? '—' }}</td></tr>
    @empty<tr><td colspan="7" class="admin-empty">No payment history matches the filters.</td></tr>@endforelse
    </tbody></table></div>
    {{ $membershipRequests->links() }}
</section>
@endsection
