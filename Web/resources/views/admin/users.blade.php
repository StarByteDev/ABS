@extends('admin.layout')
@section('title','User Directory — ABS Admin')
@section('heading','User Directory')
@section('description','Manage account identity, user level, Pulse package access, expiry, security and customer activity from one operational directory.')
@section('content')
<section class="enterprise-command-bar compact">
    <div><span class="admin-report-eyebrow">CUSTOMER ADMINISTRATION · USER 360°</span><h2>Users, access levels & service entitlement</h2><p>Create and manage every ABS account, role, Private Member permission, Pulse package, access period and security state from one directory.</p></div>
    <div class="admin-actions"><a class="button button-ghost" href="{{ route('admin.pulse.access') }}">Plan Access & Expiry</a><a class="button button-primary" href="{{ route('admin.users.create') }}">+ Create User</a></div>
</section>
<section class="admin-report-kpis user-directory-kpis">@foreach($summary as $label=>$value)<article><span class="report-kpi-icon {{ str_contains($label,'Suspended')||str_contains($label,'Deleted')?'red':(str_contains($label,'Pulse')?'gold':'blue') }}">{{ str_contains($label,'Pulse')?'✦':'◎' }}</span><div><small>{{ $label }}</small><strong>{{ number_format($value) }}</strong><em>{{ str_contains($label,'accounts')?'Account lifecycle':'Access classification' }}</em></div></article>@endforeach</section>

<section class="enterprise-surface admin-insight-band user-level-guide"><div><span class="insight-dot info"></span><p><b>Standard User</b><small>Core ABS account. Pulse access is granted separately through an Admin-verified USDT package.</small></p></div><div><span class="insight-dot good"></span><p><b>Private Member</b><small>Receives controlled private portfolio reporting in addition to any assigned Pulse package.</small></p></div><div><span class="insight-dot warn"></span><p><b>Administrator</b><small>Enterprise control authority. At least one active administrator is always protected.</small></p></div></section>

<section class="enterprise-surface enterprise-filter-surface">
<form method="GET" class="enterprise-filter-grid">
    <label>Search<input name="q" value="{{ request('q') }}" placeholder="Name or email"></label>
    <label>Account status<select name="status"><option value="">All</option>@foreach(['active','pending','suspended'] as $x)<option value="{{ $x }}" @selected(request('status')===$x)>{{ ucfirst($x) }}</option>@endforeach</select></label>
    <label>Role<select name="role"><option value="">All</option>@foreach(['user'=>'Standard','private_member'=>'Private member','admin'=>'Administrator'] as $k=>$v)<option value="{{ $k }}" @selected(request('role')===$k)>{{ $v }}</option>@endforeach</select></label>
    <label>Pulse plan<select name="plan"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((string)request('plan')===(string)$plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
    <label>Pulse status<select name="pulse_status"><option value="">Any</option>@foreach(['pending','active','suspended','expired','revoked'] as $x)<option value="{{ $x }}" @selected(request('pulse_status')===$x)>{{ ucfirst($x) }}</option>@endforeach</select></label>
    <label>Deleted users<select name="deleted"><option value="">Active directory</option><option value="with" @selected(request('deleted')==='with')>Include deleted</option><option value="only" @selected(request('deleted')==='only')>Deleted only</option></select></label>
    <label>Expiry<select name="expiry"><option value="">Any</option><option value="7" @selected(request('expiry')==='7')>Next 7 days</option><option value="30" @selected(request('expiry')==='30')>Next 30 days</option><option value="expired" @selected(request('expiry')==='expired')>Expired</option><option value="unassigned" @selected(request('expiry')==='unassigned')>No Pulse access</option></select></label>
    <div class="filter-actions"><button class="button button-primary">Apply filters</button><a class="button button-ghost" href="{{ route('admin.users') }}">Clear</a></div>
</form>
</section>

<section class="enterprise-surface no-pad">
<div class="enterprise-table-wrap"><table class="enterprise-table user-directory-table"><thead><tr><th>User</th><th>Account</th><th>Pulse plan</th><th>Pulse access</th><th>Expiry</th><th>Last login</th><th>Private member</th><th></th></tr></thead><tbody>
@forelse($users as $user)
@php $access=$user->pulseAccess; $expired=$access?->ends_at && $access->ends_at->isPast(); $days=$access?->ends_at ? now()->startOfDay()->diffInDays($access->ends_at->copy()->startOfDay(),false) : null; @endphp
<tr>
<td>@if($user->trashed())<b>{{ $user->name }}</b>@else<a href="{{ route('admin.users.show',$user) }}"><b>{{ $user->name }}</b></a>@endif<small>#{{ $user->id }} · {{ $user->email }}</small></td>
<td>@if($user->trashed())<span class="admin-status danger">Deleted</span>@else<span class="admin-status {{ $user->status==='active'?'good':($user->status==='suspended'?'danger':'warn') }}">{{ ucfirst($user->status) }}</span>@endif<small>{{ str_replace('_',' ',ucfirst($user->role)) }}</small></td>
<td>{{ $access?->plan?->name ?? '—' }}</td>
<td><span class="admin-status {{ $access?->isActive()?'good':($expired?'danger':'muted') }}">{{ $access?->status ? ucfirst($access->status) : 'Not assigned' }}</span></td>
<td>@if($access?->ends_at)<b>{{ $access->ends_at->format('d M Y') }}</b><small>{{ $expired ? abs($days).' days expired' : $days.' days remaining' }}</small>@else<span>Open-ended</span>@endif</td>
<td>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
<td>{{ $user->isPrivateMember() ? 'Active' : '—' }}</td>
<td>@if($user->trashed())<div class="admin-inline-actions"><form method="POST" action="{{ route('admin.users.restore',$user->id) }}">@csrf<button class="enterprise-row-action" type="submit">Restore</button></form><form method="POST" action="{{ route('admin.users.force-delete',$user->id) }}" onsubmit="return confirm('Permanently delete this user? This cannot be undone.')">@csrf @method('DELETE')<button class="enterprise-row-action danger-text" type="submit">Permanent delete</button></form></div>@else<a class="enterprise-row-action" href="{{ route('admin.users.show',$user) }}">Manage →</a>@endif</td>
</tr>
@empty<tr><td colspan="8">No users match the selected filters.</td></tr>@endforelse
</tbody></table></div>
<div class="enterprise-pagination">{{ $users->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
</section>
@endsection
