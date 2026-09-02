@extends('admin.layout')
@section('title','User Directory — ABS Admin')
@section('heading','User Directory')
@section('content')
<section class="enterprise-command-bar compact">
    <div><h2>Users, roles & service access</h2><p>Create and manage every ABS account, role, Private Member permission, Pulse plan, access period and account status from one directory.</p></div>
    <div class="admin-actions"><a class="button button-ghost" href="{{ route('admin.pulse.access') }}">Subscriptions & expiry</a><a class="button button-primary" href="{{ route('admin.users.create') }}">+ Create User</a></div>
</section>
<section class="enterprise-mini-kpis">@foreach($summary as $label=>$value)<div><small>{{ ucfirst($label) }}</small><strong>{{ number_format($value) }}</strong></div>@endforeach</section>

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
<div class="enterprise-pagination">{{ $users->links() }}</div>
</section>
@endsection
