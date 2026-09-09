@extends('admin.layout')
@section('title',$user->name.' — ABS Admin')
@section('heading','User 360° View')
@section('description','One customer record for identity, security, role, Pulse entitlement, usage, signals, trades and private reporting access.')
@section('content')
@php
$expired=$access?->ends_at && $access->ends_at->isPast();
$days=$access?->ends_at ? now()->startOfDay()->diffInDays($access->ends_at->copy()->startOfDay(),false) : null;
$restrictions=is_array($access?->permissions)?$access->permissions:[];
@endphp
<section class="enterprise-user-hero">
    <div class="enterprise-avatar large">{{ strtoupper(substr($user->name,0,1)) }}</div>
    <div class="grow"><small>USER #{{ $user->id }}</small><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p><div class="admin-status-line"><span class="admin-status {{ $user->status==='active'?'good':'warn' }}">Account {{ ucfirst($user->status) }}</span><span class="admin-status {{ $access?->isActive()?'good':($expired?'danger':'muted') }}">Pulse {{ $access?->status ? ucfirst($access->status) : 'Not assigned' }}</span>@if($user->isPrivateMember())<span class="admin-status good">Private member</span>@endif</div></div>
    <div class="admin-user-meta"><span>Registered <b>{{ $user->created_at?->format('d M Y') }}</b></span><span>Last login <b>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</b></span></div>
</section>

<section class="enterprise-mini-kpis">@foreach($usage as $label=>$value)<div><small>{{ $label }}</small><strong>{{ number_format($value) }}</strong></div>@endforeach<div><small>Total trades</small><strong>{{ number_format($tradeSummary['total']) }}</strong></div><div><small>Stored realized P&amp;L</small><strong>{{ number_format($tradeSummary['realized_pnl'],2) }}</strong></div></section>

<section class="enterprise-section-grid two-one">
<article class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Pulse plan access & entitlements</h2><p>Assign the plan, control the access period and optionally restrict individual capabilities.</p></div></div>
    <form method="POST" action="{{ route('admin.pulse.access.update',$user) }}" class="enterprise-form-grid">@csrf @method('PUT')
        <label>Status<select name="status">@foreach(['pending','active','suspended','expired','revoked'] as $x)<option value="{{ $x }}" @selected(old('status',$access?->status ?? 'pending')===$x)>{{ ucfirst($x) }}</option>@endforeach</select></label>
        <label>Pulse plan<select name="pulse_plan_id"><option value="">No plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int)old('pulse_plan_id',$access?->pulse_plan_id)===$plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
        <label>Starts at<input type="datetime-local" name="starts_at" value="{{ old('starts_at',$access?->starts_at?->format('Y-m-d\TH:i')) }}"></label>
        <label>Ends at<input type="datetime-local" name="ends_at" value="{{ old('ends_at',$access?->ends_at?->format('Y-m-d\TH:i')) }}"></label>
        <label class="full">Internal note<textarea name="notes" rows="2">{{ old('notes',$access?->notes) }}</textarea></label>
        <div class="full enterprise-entitlement-list"><h3>Individual restrictions</h3><p>Unchecked features follow the assigned plan. Check only the features this user should not receive.</p><div class="admin-capability-grid">@foreach(\App\Models\PulsePlan::CAPABILITIES as $key=>$label)<label class="admin-capability-toggle restriction-toggle"><input type="checkbox" name="restriction_{{ $key }}" value="1" @checked(array_key_exists($key,$restrictions)&&$restrictions[$key]===false)><span><b>Disable {{ $label }}</b></span></label>@endforeach</div></div>
        <div class="full admin-page-actions"><button class="button button-primary">Save plan access</button></div>
    </form>
</article>

<article class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Plan access status</h2><p>Commercial and access timing summary.</p></div></div>
    <div class="enterprise-metric-list subscription-summary">
        <div><span>Current plan</span><b>{{ $access?->plan?->name ?? 'Not assigned' }}</b></div>
        <div><span>Starts</span><b>{{ $access?->starts_at?->format('d M Y') ?? '—' }}</b></div>
        <div><span>Expires</span><b>{{ $access?->ends_at?->format('d M Y') ?? 'Open-ended' }}</b></div>
        <div><span>Remaining</span><b>{{ $access?->ends_at ? ($expired ? 'Expired '.abs($days).'d ago' : $days.' days') : 'No expiry' }}</b></div>
    </div>
    @if($access)
    <div class="renew-box"><h3>Extend access</h3><p>Extend from the current future expiry date, or from today if already expired.</p><div class="renew-actions">@foreach([7,30,60,90,180,365] as $d)<form method="POST" action="{{ route('admin.pulse.access.renew',$user) }}">@csrf<input type="hidden" name="days" value="{{ $d }}"><button class="button button-ghost">+{{ $d }} days</button></form>@endforeach</div></div>
    @endif
</article>
</section>

<section class="enterprise-surface"><div class="enterprise-section-head"><div><h2>Legacy membership request history</h2><p>Direct USDT package payment history retained for audit and access verification.</p></div><a href="{{ route('admin.pulse.memberships',['q'=>$user->email]) }}">Open payment history →</a></div><div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Request</th><th>Plan</th><th>Value</th><th>Promotion</th><th>Status</th><th>Submitted</th></tr></thead><tbody>@forelse($membershipRequests as $requestItem)<tr><td>#{{ $requestItem->id }}</td><td><b>{{ $requestItem->plan?->name ?? 'Plan removed' }}</b><small>{{ $requestItem->activation_days }} days</small></td><td>{{ number_format((float)$requestItem->final_amount,2) }} {{ $requestItem->currency }}</td><td>{{ $requestItem->promotion_code_snapshot ?: '—' }}</td><td><span class="admin-status {{ $requestItem->status==='approved'?'good':($requestItem->status==='rejected'?'danger':(in_array($requestItem->status,['submitted','under_review'])?'warn':'muted')) }}">{{ ucfirst(str_replace('_',' ',$requestItem->status)) }}</span></td><td>{{ $requestItem->created_at?->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="6">No membership requests for this account.</td></tr>@endforelse</tbody></table></div></section>

<section class="enterprise-section-grid equal">
<article class="enterprise-surface"><div class="enterprise-section-head"><div><h2>Recent signals</h2><p>Latest generated Pulse signals for this user.</p></div><a href="{{ route('admin.pulse.signals') }}">All signals →</a></div><div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Generated</th><th>Market</th><th>Direction</th><th>Score</th><th>Status</th></tr></thead><tbody>@forelse($recentSignals as $signal)<tr><td>{{ $signal->generated_at?->format('d M H:i') }}</td><td>{{ $signal->symbol }}</td><td>{{ $signal->direction }}</td><td>{{ number_format((float)$signal->score,1) }}</td><td>{{ ucfirst($signal->status) }}</td></tr>@empty<tr><td colspan="5">No signal history.</td></tr>@endforelse</tbody></table></div></article>
<article class="enterprise-surface"><div class="enterprise-section-head"><div><h2>Recent trades</h2><p>Latest Pulse trade records for this user.</p></div><a href="{{ route('admin.pulse.trades') }}">All trades →</a></div><div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Created</th><th>Market</th><th>Environment</th><th>Status</th><th>P&amp;L</th></tr></thead><tbody>@forelse($recentTrades as $trade)<tr><td>{{ $trade->created_at?->format('d M H:i') }}</td><td>{{ $trade->symbol }}</td><td>{{ ucfirst($trade->environment) }}</td><td>{{ ucfirst(str_replace('_',' ',$trade->status)) }}</td><td>{{ number_format((float)$trade->realized_pnl,2) }}</td></tr>@empty<tr><td colspan="5">No trade history.</td></tr>@endforelse</tbody></table></div></article>
</section>

<section class="enterprise-section-grid two-one user-admin-grid">
<article class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Identity, role & account status</h2><p>Manage the user's sign-in identity and platform-level authorization. Private Member access is activated when that role is Active.</p></div></div>
    @if($isLastActiveAdmin)<div class="enterprise-protection-note"><b>Protected administrator</b><span>This is the last active administrator. The system will not allow this account to be demoted or suspended until another active administrator exists.</span></div>@endif
    <form method="POST" action="{{ route('admin.users.update',$user) }}" class="enterprise-form-grid">@csrf @method('PATCH')
        <label>Full name<input name="name" value="{{ old('name',$user->name) }}" maxlength="100" required></label>
        <label>Email address<input type="email" name="email" value="{{ old('email',$user->email) }}" maxlength="255" required></label>
        <label>Country<input name="country" value="{{ old('country',$user->country) }}" maxlength="80"></label>
        <label>Phone or WhatsApp<div class="enterprise-phone-fields"><input name="country_code" value="{{ old('country_code',$user->country_code) }}" maxlength="8" placeholder="+971"><input name="phone" value="{{ old('phone',$user->phone) }}" maxlength="24" placeholder="Phone number"></div></label>
        <label>Role<select name="role"><option value="user" @selected(old('role',$user->role)==='user')>Standard user</option><option value="private_member" @selected(old('role',$user->role)==='private_member')>Private Member</option><option value="admin" @selected(old('role',$user->role)==='admin')>Administrator</option></select></label>
        <label>Account status<select name="status"><option value="active" @selected(old('status',$user->status)==='active')>Active</option><option value="pending" @selected(old('status',$user->status)==='pending')>Pending</option><option value="suspended" @selected(old('status',$user->status)==='suspended')>Suspended</option></select></label>
        <label class="full enterprise-check-card compact-check"><input type="checkbox" name="email_verified" value="1" @checked(old('email_verified',$user->email_verified_at ? '1' : null))><span><b>Email verified</b><small>Verification state is managed by Admin for manually created or validated accounts.</small></span></label>
        <div class="full admin-page-actions"><button class="button button-primary">Save Account</button></div>
    </form>
</article>

<article class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Security & service status</h2><p>Reset credentials and review restricted-service readiness.</p></div></div>
    <div class="enterprise-metric-list user-security-summary">
        <div><span>Email verification</span><b>{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</b></div>
        <div><span>Phone or WhatsApp</span><b>{{ trim(($user->country_code ?? '').' '.($user->phone ?? '')) ?: 'Not provided' }}</b></div>
        <div><span>Country</span><b>{{ $user->country ?: 'Not provided' }}</b></div>
        <div><span>Private Member Portal</span><b>{{ $user->isPrivateMember() ? 'Active' : ($user->role==='private_member' ? 'Inactive — account not active' : 'Not assigned') }}</b></div>
        <div><span>Private reporting account</span><b>{{ $user->portfolioAccount ? 'Configured' : 'Not configured' }}</b></div>
        <div><span>Last sign in</span><b>{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}</b></div>
    </div>
    @if($user->role==='private_member')<div class="admin-page-actions user-service-actions"><a class="button button-ghost" href="{{ route('admin.portfolios',['user'=>$user->id]) }}">{{ $user->portfolioAccount ? 'Manage Private Reporting' : 'Set Up Private Reporting' }}</a></div>@endif
    <div class="enterprise-password-reset">
        <h3>Set a new password</h3><p>The password is never sent by email. Other database sessions and mobile API tokens are revoked after an Admin reset.</p>
        <form method="POST" action="{{ route('admin.users.password',$user) }}" class="enterprise-form-grid">@csrf
            <label>New password<input type="password" name="password" autocomplete="new-password" required></label>
            <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <div class="full admin-page-actions"><button class="button button-ghost">Update Password</button></div>
        </form>
    </div>
</article>

</section>

<section class="enterprise-surface enterprise-danger-zone">
    <div class="enterprise-section-head"><div><h2>Account lifecycle</h2><p>Deactivate access immediately or move the account to Deleted Users while preserving trade, signal and audit history.</p></div></div>
    <div class="admin-page-actions">
        @if($user->status !== 'suspended')
        <form method="POST" action="{{ route('admin.users.deactivate',$user) }}" onsubmit="return confirm('Deactivate this user and revoke active sessions/service access?')">@csrf<button class="button button-ghost">Deactivate User</button></form>
        @endif
        <form method="POST" action="{{ route('admin.users.destroy',$user) }}" onsubmit="return confirm('Move this user to Deleted Users? Historical trading records will be preserved.')">@csrf @method('DELETE')<button class="button button-danger">Delete User</button></form>
    </div>
    <p class="pulse-legal-note">Deletion is soft by default. Permanent deletion is available only from Deleted Users and is blocked while open/pending trades exist.</p>
</section>
@endsection
