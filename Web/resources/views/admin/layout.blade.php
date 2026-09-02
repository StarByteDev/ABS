<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','ABS Admin')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/abs-app.css') }}?v={{ @filemtime(public_path('assets/css/abs-app.css')) ?: '13.9' }}">
    @stack('head')
</head>
<body class="admin-body enterprise-admin-body">
<aside class="admin-sidebar enterprise-admin-sidebar">
    <div class="admin-brand-block">
        @include('partials.logo')
        <span>Enterprise Console</span>
    </div>
    <nav class="enterprise-admin-nav">
        <small>OVERVIEW</small>
        <a class="{{ request()->routeIs('admin.dashboard')?'active':'' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
        <a class="{{ request()->routeIs('admin.users*')?'active':'' }}" href="{{ route('admin.users') }}">User Management</a>

        <small>PULSE COMMERCIAL</small>
        <a class="{{ request()->routeIs('admin.pulse.access')?'active':'' }}" href="{{ route('admin.pulse.access') }}">Subscriptions & Expiry</a>
        <a class="{{ request()->routeIs('admin.pulse.memberships') || request()->routeIs('admin.pulse.membership-requests.*') || request()->routeIs('admin.pulse.promotions.*') ? 'active' : '' }}" href="{{ route('admin.pulse.memberships') }}">Memberships & Payments</a>
        <a class="{{ request()->routeIs('admin.pulse.plans')?'active':'' }}" href="{{ route('admin.pulse.plans') }}">Plans & Entitlements</a>

        <small>PULSE OPERATIONS</small>
        <a class="{{ request()->routeIs('admin.pulse.dashboard')?'active':'' }}" href="{{ route('admin.pulse.dashboard') }}">Operations Center</a>
        <a class="{{ request()->routeIs('admin.pulse.intelligence')?'active':'' }}" href="{{ route('admin.pulse.intelligence') }}">Signal Intelligence</a>
        <a class="{{ request()->routeIs('admin.pulse.signals')?'active':'' }}" href="{{ route('admin.pulse.signals') }}">Signals</a>
        <a class="{{ request()->routeIs('admin.pulse.trades')?'active':'' }}" href="{{ route('admin.pulse.trades') }}">Trades</a>
        <a class="{{ request()->routeIs('admin.pulse.strategies')?'active':'' }}" href="{{ route('admin.pulse.strategies') }}">Strategies</a>
        <a class="{{ request()->routeIs('admin.pulse.pairs')?'active':'' }}" href="{{ route('admin.pulse.pairs') }}">Markets & Pairs</a>
        <a class="{{ request()->routeIs('admin.pulse.settings')?'active':'' }}" href="{{ route('admin.pulse.settings') }}">Controls & Alerts</a>
        <a class="{{ request()->routeIs('admin.pulse.logs')?'active':'' }}" href="{{ route('admin.pulse.logs') }}">Audit Log</a>
        <a class="{{ request()->routeIs('admin.market-data*')?'active':'' }}" href="{{ route('admin.market-data') }}">Market Data & Cron Health</a>

        <small>CONTENT & MEMBERS</small>
        <a class="{{ request()->routeIs('admin.portfolios')?'active':'' }}" href="{{ route('admin.portfolios') }}">Private Member Reporting</a>
        <a class="{{ request()->is('admin/content/news*')?'active':'' }}" href="{{ route('admin.content.index','news') }}">Market News CMS</a>
        <a class="{{ request()->is('admin/cms/content/research*')?'active':'' }}" href="{{ route('admin.enterprise.content.index','research') }}">Research CMS</a>
        <a class="{{ request()->is('admin/cms/content/learning*')?'active':'' }}" href="{{ route('admin.enterprise.content.index','learning') }}">Learning CMS</a>
        <a class="{{ request()->is('admin/cms/content/events*')?'active':'' }}" href="{{ route('admin.enterprise.content.index','events') }}">Economic Calendar CMS</a>
        <a class="{{ request()->is('admin/cms/content/products*')?'active':'' }}" href="{{ route('admin.enterprise.content.index','products') }}">Products & Services CMS</a>

        <small>COMMUNICATIONS & APP</small>
        <a class="{{ request()->routeIs('admin.enterprise.settings*')?'active':'' }}" href="{{ route('admin.enterprise.settings') }}">Website & Mobile Settings</a>
        <a class="{{ request()->routeIs('admin.enterprise.newsletters*')?'active':'' }}" href="{{ route('admin.enterprise.newsletters') }}">Newsletter Subscribers</a>
        <a class="{{ request()->routeIs('admin.enterprise.contacts*')?'active':'' }}" href="{{ route('admin.enterprise.contacts') }}">Contact & Support Inbox</a>
        <a class="{{ request()->routeIs('admin.enterprise.emails*')?'active':'' }}" href="{{ route('admin.enterprise.emails') }}">Email Communications</a>

        <small>SYSTEM & MIGRATION</small>
        <a class="{{ request()->routeIs('admin.enterprise.updates*')?'active':'' }}" href="{{ route('admin.enterprise.updates') }}">System Updates & Rollback</a>
        <a class="{{ request()->routeIs('admin.enterprise.backups*')?'active':'' }}" href="{{ route('admin.enterprise.backups') }}">Database Backup & Restore</a>
        <a class="{{ request()->routeIs('admin.enterprise.maintenance*')?'active':'' }}" href="{{ route('admin.enterprise.maintenance') }}">Database Maintenance</a>
    </nav>
    <div class="admin-sidebar-footer">
        <a href="{{ route('home') }}">View public website</a>
        <a class="button button-ghost button-wide" href="{{ route('logout') }}">Sign out</a>
    </div>
</aside>
<main class="admin-main enterprise-admin-main">
    <header class="admin-top enterprise-admin-top">
        <div><small>ALPHA BLOCK SOLUTIONS / ADMINISTRATION</small><h1>@yield('heading','Enterprise Console')</h1></div>
        <div class="admin-account-chip"><span>{{ auth()->user()->name }}</span><small>{{ auth()->user()->email }}</small></div>
    </header>
    @if(session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="flash flash-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
<script src="{{ asset('assets/js/abs-app.js') }}?v={{ @filemtime(public_path('assets/js/abs-app.js')) ?: '13.9' }}" defer></script>
@stack('scripts')
</body>
</html>
