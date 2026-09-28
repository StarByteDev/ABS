@extends('admin.layout')
@section('title','Members Overview — ABS Admin')
@section('heading','Members Overview')
@section('hide-heading','1')
@section('description','Member accounts, Pulse access, renewals and payment approvals in one clear view.')
@section('content')
@php
    $activeMembers=(int)$stats['Active accounts'];
    $activePulse=(int)$stats['Active Pulse users'];
    $expiring7=(int)$stats['Expiring in 7 days'];
    $expiring30=(int)$stats['Expiring in 30 days'];
    $expired=(int)$stats['Expired Pulse access'];
    $pendingPayments=(int)($paymentStats['pending_count']??0);
    $pendingUsdt=(float)($paymentStats['pending_usdt']??0);
    $activePackages=$planRows->filter(fn($plan)=>(bool)($plan->is_active ?? true))->count();
@endphp

<section class="mo-hero">
    <div>
        <span class="wf-kicker">ABS PULSE · MEMBERS &amp; ACCESS</span>
        <h1>Members Overview</h1>
        <p>Review accounts, package access, renewals and approvals without leaving this workflow.</p>
    </div>
    <div class="mo-hero-actions">
        <a class="button button-primary" href="{{ route('admin.users') }}">Member Accounts</a>
        <a class="button button-ghost" href="{{ route('admin.pulse.memberships') }}">Payment Approvals</a>
    </div>
</section>

<section class="mo-status-grid">
    <article><span>ACTIVE MEMBERS</span><strong>{{ number_format($activeMembers) }}</strong><small>{{ number_format((int)$stats['Total users']) }} total accounts</small></article>
    <article><span>ACTIVE PULSE ACCESS</span><strong>{{ number_format($activePulse) }}</strong><small>current package entitlement</small></article>
    <article><span>EXPIRING SOON</span><strong class="{{ $expiring7>0?'warn':'' }}">{{ number_format($expiring7) }}</strong><small>within the next 7 days</small></article>
    <article><span>PAYMENTS TO REVIEW</span><strong class="{{ $pendingPayments>0?'warn':'' }}">{{ number_format($pendingPayments) }}</strong><small>{{ number_format($pendingUsdt,2) }} USDT pending</small></article>
</section>

<section class="mo-section-head"><div><span class="wf-kicker">MEMBER WORKFLOW</span><h2>Choose the task you want to manage</h2></div></section>
<section class="mo-task-grid">
    <a href="{{ route('admin.users') }}" class="mo-task-card"><span class="mo-task-icon">♙</span><div><h3>Member Accounts</h3><strong>{{ number_format((int)$stats['Total users']) }} accounts</strong><small>Search members, review status and open an account.</small></div><i>›</i></a>
    <a href="{{ route('admin.pulse.plans') }}" class="mo-task-card"><span class="mo-task-icon">▣</span><div><h3>Packages</h3><strong>{{ number_format($activePackages) }} available</strong><small>Configure package access, markets, features and permissions.</small></div><i>›</i></a>
    <a href="{{ route('admin.pulse.access') }}" class="mo-task-card"><span class="mo-task-icon">◷</span><div><h3>Access &amp; Renewals</h3><strong>{{ number_format($expiring30) }} expiring</strong><small>Review active access, expiry dates and renewals.</small></div><i>›</i></a>
    <a href="{{ route('admin.pulse.memberships') }}" class="mo-task-card"><span class="mo-task-icon">▤</span><div><h3>Payments &amp; Approvals</h3><strong>{{ number_format($pendingPayments) }} awaiting review</strong><small>Verify USDT payments and activate approved packages.</small></div><i>›</i></a>
</section>

<section class="mo-two-col">
    <article class="wf-panel mo-panel">
        <div class="wf-panel-head"><div><span class="wf-kicker">RENEWAL ATTENTION</span><h2>Access requiring review</h2></div><a href="{{ route('admin.pulse.access') }}">View all →</a></div>
        @forelse($expiryQueue->take(6) as $access)
            @php($days=max(0,(int)ceil(now()->diffInDays($access->ends_at,false))))
            <div class="mo-list-row">
                <span><b>{{ $access->user?->name ?? 'Member' }}</b><small>{{ $access->plan?->name ?? 'Pulse package' }}</small></span>
                <em>{{ $access->ends_at?->format('d M Y') ?? '—' }}</em>
                <strong class="{{ $days<=3?'bad':'warn' }}">{{ $days }}d</strong>
                @if($access->user)<a href="{{ route('admin.users.show',$access->user) }}">Open</a>@endif
            </div>
        @empty
            <div class="wf-empty">No access expires in the next 30 days.</div>
        @endforelse
    </article>

    <article class="wf-panel mo-panel">
        <div class="wf-panel-head"><div><span class="wf-kicker">RECENT MEMBERS</span><h2>Latest accounts</h2></div><a href="{{ route('admin.users') }}">View all →</a></div>
        @forelse($recentUsers->take(6) as $user)
            <div class="mo-list-row mo-member-row">
                <span><b>{{ $user->name ?: 'Member' }}</b><small>{{ $user->email }}</small></span>
                <em>{{ $user->created_at?->format('d M Y') ?? '—' }}</em>
                <strong class="{{ $user->status==='active'?'good':'warn' }}">{{ strtoupper((string)$user->status) }}</strong>
                <a href="{{ route('admin.users.show',$user) }}">Open</a>
            </div>
        @empty
            <div class="wf-empty">No member accounts are available yet.</div>
        @endforelse
    </article>
</section>

<section class="mo-footnote">
    <span>Access status</span><b>{{ number_format($activePulse) }} active</b><i>·</i><b>{{ number_format($expiring30) }} expiring within 30 days</b><i>·</i><b>{{ number_format($expired) }} expired</b>
</section>
@endsection
