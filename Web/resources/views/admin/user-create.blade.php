@extends('admin.layout')
@section('title','Create User — ABS Admin')
@section('heading','Create User')
@section('content')
<section class="enterprise-command-bar compact">
    <div>
        <h2>Create an ABS account with access ready from day one</h2>
        <p>Set the user identity, account role, Private Member status and Pulse plan dates in one controlled administration flow.</p>
    </div>
    <a class="button button-ghost" href="{{ route('admin.users') }}">← Back to User Directory</a>
</section>

<form method="POST" action="{{ route('admin.users.store') }}" class="enterprise-user-create-form">
@csrf
<section class="enterprise-section-grid two-one user-create-grid">
    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Account identity</h2><p>These credentials are used for ABS, Pulse and any service assigned to this account.</p></div></div>
        <div class="enterprise-form-grid">
            <label>Full name<input name="name" value="{{ old('name') }}" maxlength="100" autocomplete="off" required></label>
            <label>Email address<input type="email" name="email" value="{{ old('email') }}" maxlength="255" autocomplete="off" required></label>
            <label>Country<input name="country" value="{{ old('country') }}" maxlength="80" autocomplete="off"></label>
            <label>Phone or WhatsApp<div class="enterprise-phone-fields"><input name="country_code" value="{{ old('country_code') }}" maxlength="8" placeholder="+971"><input name="phone" value="{{ old('phone') }}" maxlength="24" placeholder="Phone number"></div></label>
            <label>Initial password<input type="password" name="password" autocomplete="new-password" required><small>Minimum 8 characters with upper/lowercase letters and a number.</small></label>
            <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <label>Account role
                <select name="role" required>
                    <option value="user" @selected(old('role','user')==='user')>Standard user</option>
                    <option value="private_member" @selected(old('role')==='private_member')>Private Member</option>
                    <option value="admin" @selected(old('role')==='admin')>Administrator</option>
                </select>
                <small>Private Member role activates the restricted reporting portal when the account is Active.</small>
            </label>
            <label>Account status
                <select name="status" required>
                    <option value="active" @selected(old('status','active')==='active')>Active</option>
                    <option value="pending" @selected(old('status')==='pending')>Pending</option>
                    <option value="suspended" @selected(old('status')==='suspended')>Suspended</option>
                </select>
            </label>
            <label class="enterprise-check-card"><input type="checkbox" name="email_verified" value="1" @checked(old('email_verified','1'))><span><b>Mark email as verified</b><small>Useful for accounts created and validated directly by an administrator.</small></span></label>
            <label class="enterprise-check-card"><input type="checkbox" name="send_welcome_email" value="1" @checked(old('send_welcome_email','1'))><span><b>Send account-ready email</b><small>No password is included. Credentials should be shared through your secure channel.</small></span></label>
        </div>
    </article>

    <article class="enterprise-surface enterprise-guidance-card">
        <div class="enterprise-section-head"><div><h2>Role access</h2><p>What each account role controls.</p></div></div>
        <div class="enterprise-access-map">
            <div><span>USER</span><b>Standard user</b><p>ABS account, live market pages and any Pulse plan explicitly assigned below.</p></div>
            <div><span>PRIVATE</span><b>Private Member</b><p>Adds invitation-only reporting. Financial reporting records remain managed separately in Private Member Reporting.</p></div>
            <div><span>ADMIN</span><b>Administrator</b><p>Full Enterprise Console access. Assign only to trusted platform administrators.</p></div>
        </div>
    </article>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Pulse plan & access period</h2><p>Optional. Assign a plan immediately, set exact dates and restrict individual capabilities if required.</p></div></div>
    <label class="enterprise-master-toggle"><input type="checkbox" name="assign_pulse" value="1" @checked(old('assign_pulse'))><span><b>Assign Pulse access to this user</b><small>If left off, the user account is created without Pulse entitlement and can be assigned later.</small></span></label>
    <div class="enterprise-form-grid user-access-create-grid">
        <label>Pulse plan<select name="pulse_plan_id"><option value="">Select a plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string)old('pulse_plan_id')===(string)$plan->id)>{{ $plan->name }}{{ $plan->is_trial ? ' — Trial' : '' }}</option>@endforeach</select></label>
        <label>Access status<select name="pulse_status">@foreach(['active'=>'Active','pending'=>'Pending','suspended'=>'Suspended','expired'=>'Expired','revoked'=>'Revoked'] as $key=>$label)<option value="{{ $key }}" @selected(old('pulse_status','active')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label>Starts at<input type="datetime-local" name="starts_at" value="{{ old('starts_at',now()->format('Y-m-d\TH:i')) }}"></label>
        <label>Ends at<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"><small>Leave blank for open-ended access.</small></label>
        <label class="full">Internal access note<textarea name="access_notes" rows="3" maxlength="2000" placeholder="Internal administration note">{{ old('access_notes') }}</textarea></label>
    </div>
    <div class="enterprise-entitlement-list">
        <h3>Per-user capability restrictions</h3>
        <p>The selected plan remains the maximum entitlement. Check a capability only when this individual user must be restricted further.</p>
        <div class="admin-capability-grid">
            @foreach($capabilityLabels as $key=>$label)
                <label class="admin-capability-toggle restriction-toggle"><input type="checkbox" name="restriction_{{ $key }}" value="1" @checked(old('restriction_'.$key))><span><b>Disable {{ $label }}</b></span></label>
            @endforeach
        </div>
    </div>
</section>

<section class="enterprise-create-footer">
    <div><b>Ready to create the user?</b><p>You can change role, status, plan, dates, restrictions and password later from User 360°.</p></div>
    <div class="admin-page-actions"><a class="button button-ghost" href="{{ route('admin.users') }}">Cancel</a><button class="button button-primary">Create User & Apply Access</button></div>
</section>
</form>
@endsection
