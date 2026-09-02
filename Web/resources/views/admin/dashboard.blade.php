@extends('admin.layout')
@section('title','ABS Enterprise Administration')
@section('heading','Executive Dashboard')
@section('content')
<section class="enterprise-command-bar">
    <div><h2>Platform health at a glance</h2><p>Users, Pulse subscriptions, upcoming expiries, plan adoption and operational activity in one place.</p></div>
    <div class="admin-actions"><a class="button button-primary" href="{{ route('admin.users.create') }}">+ Create User</a><a class="button button-ghost" href="{{ route('admin.pulse.memberships') }}">Review membership requests</a><a class="button button-ghost" href="{{ route('admin.pulse.access') }}">Manage subscriptions</a><a class="button button-ghost" href="{{ route('admin.users') }}">Open user directory</a><a class="button button-ghost" href="{{ route('admin.pulse.plans') }}">Manage plans</a></div>
</section>

<section class="enterprise-kpi-grid">
@foreach($stats as $label=>$value)
    <article class="enterprise-kpi {{ str_contains(strtolower($label),'expir') ? 'attention' : '' }}"><small>{{ $label }}</small><strong>{{ number_format($value) }}</strong></article>
@endforeach
</section>

<section class="enterprise-section-grid two-one">
    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Plans & active subscriptions</h2><p>Current assignment, active usage and upcoming renewal exposure by Pulse plan.</p></div><a href="{{ route('admin.pulse.plans') }}">Manage plans →</a></div>
        <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Plan</th><th>Status</th><th>Assigned</th><th>Active</th><th>Expiring ≤30d</th><th>List price</th></tr></thead><tbody>
        @forelse($planRows as $plan)<tr><td><b>{{ $plan->name }}</b><small>{{ $plan->slug }}</small></td><td><span class="admin-status {{ $plan->is_active?'good':'muted' }}">{{ $plan->is_active?'Active':'Inactive' }}</span></td><td>{{ $plan->assigned_count }}</td><td>{{ $plan->active_count }}</td><td>{{ $plan->expiring_30_count }}</td><td>{{ $plan->currency }} {{ number_format((float)$plan->monthly_price,2) }}</td></tr>@empty<tr><td colspan="6">No Pulse plans configured.</td></tr>@endforelse
        </tbody></table></div>
    </article>

    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Operational snapshot</h2><p>Current Pulse and content activity.</p></div><a href="{{ route('admin.pulse.dashboard') }}">Operations →</a></div>
        <div class="enterprise-metric-list">@foreach($operations as $label=>$value)<div><span>{{ $label }}</span><b>{{ number_format($value) }}</b></div>@endforeach</div>
    </article>
</section>

<section class="enterprise-section-grid equal">
    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Renewal & expiry queue</h2><p>Active Pulse access ending within the next 30 days, earliest first.</p></div><a href="{{ route('admin.pulse.access',['expiry'=>30]) }}">View all →</a></div>
        <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>User</th><th>Plan</th><th>Expires</th><th>Remaining</th><th>Action</th></tr></thead><tbody>
        @forelse($expiryQueue as $access)
            @php $days=max(0,now()->startOfDay()->diffInDays($access->ends_at->copy()->startOfDay(),false)); @endphp
            <tr><td><a href="{{ route('admin.users.show',$access->user) }}"><b>{{ $access->user?->name }}</b></a><small>{{ $access->user?->email }}</small></td><td>{{ $access->plan?->name ?? 'No plan' }}</td><td>{{ $access->ends_at?->format('d M Y') }}</td><td><span class="admin-status {{ $days<=7?'warn':'good' }}">{{ $days }} days</span></td><td><a href="{{ route('admin.users.show',$access->user) }}">Review →</a></td></tr>
        @empty<tr><td colspan="5">No subscriptions expire in the next 30 days.</td></tr>@endforelse
        </tbody></table></div>
    </article>

    <article class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Recently registered users</h2><p>Latest account registrations with current Pulse assignment.</p></div><a href="{{ route('admin.users') }}">All users →</a></div>
        <div class="enterprise-user-list">@foreach($recentUsers as $user)<a href="{{ route('admin.users.show',$user) }}"><span class="enterprise-avatar">{{ strtoupper(substr($user->name,0,1)) }}</span><div><b>{{ $user->name }}</b><small>{{ $user->email }}</small></div><div class="right"><span>{{ $user->pulseAccess?->plan?->name ?? 'No Pulse plan' }}</span><small>{{ $user->created_at?->diffForHumans() }}</small></div></a>@endforeach</div>
    </article>
</section>
@endsection
