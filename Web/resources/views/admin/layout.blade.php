<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#03111f">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <title>@yield('title','ABS Admin')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/abs-app.css') }}?v={{ @filemtime(public_path('assets/css/abs-app.css')) ?: '13.9' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-premium-v1505.css') }}?v={{ @filemtime(public_path('assets/css/admin-premium-v1505.css')) ?: '15.0.5' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-institutional-v1506.css') }}?v={{ @filemtime(public_path('assets/css/admin-institutional-v1506.css')) ?: '15.0.6' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-executive-v1507.css') }}?v={{ @filemtime(public_path('assets/css/admin-executive-v1507.css')) ?: '15.0.7' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-executive-v1508.css') }}?v={{ @filemtime(public_path('assets/css/admin-executive-v1508.css')) ?: '15.0.8' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-executive-v1509.css') }}?v={{ @filemtime(public_path('assets/css/admin-executive-v1509.css')) ?: '15.0.9' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-executive-v1510.css') }}?v={{ @filemtime(public_path('assets/css/admin-executive-v1510.css')) ?: '15.1.2' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-executive-v1516.css') }}?v={{ @filemtime(public_path('assets/css/admin-executive-v1516.css')) ?: '15.1.6' }}">
    @stack('head')
</head>
@php
    $routeName = request()->route()?->getName() ?? '';
    $is = static fn(string $pattern): bool => request()->routeIs($pattern);
@endphp
<body class="admin-body abs-admin-v1507 abs-admin-v1508 abs-admin-v1509 abs-admin-v1510 abs-admin-v1516">
<button class="admin-mobile-menu" type="button" data-admin-menu aria-label="Open administration navigation" aria-expanded="false"><span></span><span></span><span></span></button>
<div class="admin-sidebar-overlay" data-admin-overlay></div>
<aside class="admin-sidebar abs-exec-sidebar" data-admin-sidebar>
    <div class="abs-exec-brand">
        @include('partials.logo')
        <small>ADMIN CONTROL CENTER</small>
    </div>

    <nav class="abs-exec-nav" aria-label="ABS administration">
        <a class="{{ $is('admin.dashboard')?'active':'' }}" href="{{ route('admin.dashboard') }}"><i>⌂</i><span>Dashboard</span></a>
        <a class="{{ $is('admin.users*')?'active':'' }}" href="{{ route('admin.users') }}"><i>♙</i><span>Members</span></a>
        <a class="{{ $is('admin.pulse.plans')||$is('admin.pulse.access')?'active':'' }}" href="{{ route('admin.pulse.plans') }}"><i>▣</i><span>Pulse Packages</span></a>
        <a class="{{ $is('admin.pulse.signals')?'active':'' }}" href="{{ route('admin.pulse.signals') }}"><i>⌁</i><span>Signals</span></a>
        <a class="{{ $is('admin.pulse.dashboard')?'active':'' }}" href="{{ route('admin.pulse.dashboard') }}"><i>♜</i><span>Best Signal</span></a>
        <a class="{{ $is('admin.pulse.intelligence')?'active':'' }}" href="{{ route('admin.pulse.intelligence') }}"><i>▥</i><span>Pulse Intelligence</span></a>
        <a class="{{ $is('admin.market-data')?'active':'' }}" href="{{ route('admin.market-data') }}"><i>⌁</i><span>Market Feed &amp; Cron</span></a>
        <a class="{{ $is('admin.pulse.logs')?'active':'' }}" href="{{ route('admin.pulse.logs') }}"><i>◎</i><span>Signal Oversight</span></a>
        <a class="{{ $is('admin.pulse.trades')?'active':'' }}" href="{{ route('admin.pulse.trades') }}"><i>↗</i><span>Trades</span></a>
        <a class="{{ $is('admin.pulse.memberships')?'active':'' }}" href="{{ route('admin.pulse.memberships') }}"><i>▤</i><span>Package Payments</span></a>
        <a class="{{ $is('admin.pulse.rewarded-signals')?'active':'' }}" href="{{ route('admin.pulse.rewarded-signals') }}"><i>▶</i><span>Rewarded Signal Ads</span></a>
        <a class="{{ $is('admin.enterprise.emails')||$is('admin.enterprise.contacts*')?'active':'' }}" href="{{ route('admin.enterprise.emails') }}"><i>♧</i><span>Alerts &amp; Emails</span></a>
        <a class="{{ $is('admin.content*')||$is('admin.enterprise.content*')?'active':'' }}" href="{{ route('admin.content.index','news') }}"><i>▧</i><span>CMS</span></a>
        <a class="{{ $is('admin.portfolios*')?'active':'' }}" href="{{ route('admin.portfolios') }}"><i>▥</i><span>Reports</span></a>
        <a class="{{ $is('admin.enterprise.settings*')?'active':'' }}" href="{{ route('admin.enterprise.settings') }}"><i>⚙</i><span>Settings</span></a>
    </nav>

    <div class="abs-exec-sidebar-footer">
        <div class="abs-exec-product-card">
            <span>PULSE</span>
            <strong>Trading Intelligence</strong>
            <small>A product of Alpha Block Solutions</small>
        </div>
        <div class="abs-exec-system"><i></i><span><b>Platform online</b><small>Production administration</small></span></div>
        <div class="abs-exec-signature">BUILDING A SMARTER<br>TRADING TOMORROW <em>v15.1.6</em></div>
    </div>
</aside>

<main class="admin-main abs-exec-main">
    <header class="abs-exec-topbar">
        <form class="abs-exec-search" method="GET" action="{{ route('admin.users') }}" role="search"><span>⌕</span><input name="q" aria-label="Search administration" placeholder="Search members, signals, trades, or anything…"><kbd>⌘ K</kbd></form>
        <div class="abs-exec-top-actions">
            @hasSection('page-actions')<div class="abs-exec-page-actions">@yield('page-actions')</div>@endif
            <a class="abs-exec-alert" href="{{ route('admin.enterprise.contacts') }}" aria-label="Open support alerts">♧</a>
            <details class="admin-account-menu"><summary class="abs-exec-account"><span>{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Administrator</small></div><i>⌄</i></summary><div class="admin-account-popover"><a href="{{ route('home') }}">Public website</a><a href="{{ route('logout') }}">Sign out</a></div></details>
        </div>
    </header>

    <section class="abs-exec-product-strip" aria-label="ABS Pulse administration">
        <div class="abs-exec-product-identity"><div><b><span>ABS</span> Pulse</b><small>A PRODUCT OF ALPHA BLOCK SOLUTIONS</small></div></div>
        <div class="abs-exec-product-message">INTELLIGENCE <i>/</i> OPPORTUNITIES <i>/</i> A BRIGHTER TOMORROW</div>
    </section>

    @unless(View::hasSection('hide-heading'))
    <section class="abs-exec-heading">
        <div><small>ALPHA BLOCK SOLUTIONS / ADMIN</small><h1>@yield('heading','Executive Dashboard')</h1><p>@yield('description','')</p></div>
    </section>
    @endunless

    @if(session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="flash flash-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
<script src="{{ asset('assets/js/abs-app.js') }}?v={{ @filemtime(public_path('assets/js/abs-app.js')) ?: '13.9' }}" defer></script>
<script src="{{ asset('assets/js/admin-analytics-v1505.js') }}?v={{ @filemtime(public_path('assets/js/admin-analytics-v1505.js')) ?: '15.0.9' }}" defer></script>
@stack('scripts')
</body>
</html>
