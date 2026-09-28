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
    <link rel="stylesheet" href="{{ asset('assets/css/admin-navigation-v1521.css') }}?v={{ @filemtime(public_path('assets/css/admin-navigation-v1521.css')) ?: '15.2.1' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-strategy-lab-v1530.css') }}?v={{ @filemtime(public_path('assets/css/admin-strategy-lab-v1530.css')) ?: '15.3.0' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-workflow-v1540.css') }}?v={{ @filemtime(public_path('assets/css/admin-workflow-v1540.css')) ?: '15.4.0' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-flow-v1550.css') }}?v={{ @filemtime(public_path('assets/css/admin-flow-v1550.css')) ?: '15.5.0' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-release-v1562.css') }}?v={{ @filemtime(public_path('assets/css/admin-release-v1562.css')) ?: '15.6.6' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/support-v1566.css') }}?v={{ @filemtime(public_path('assets/css/support-v1566.css')) ?: '15.6.6' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/private-investor-v1566.css') }}?v={{ @filemtime(public_path('assets/css/private-investor-v1566.css')) ?: '15.6.6' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/private-investor-v1569.css') }}?v={{ @filemtime(public_path('assets/css/private-investor-v1569.css')) ?: '15.6.9' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/private-investor-v1573.css') }}?v={{ @filemtime(public_path('assets/css/private-investor-v1573.css')) ?: '15.7.3' }}">
    <script>try{document.documentElement.dataset.absAdminSidebar=localStorage.getItem('abs.admin.sidebar.state')||'expanded';}catch(e){document.documentElement.dataset.absAdminSidebar='expanded';}</script>
    @stack('head')
</head>
@php
    $routeName = request()->route()?->getName() ?? '';
    $is = static fn(string $pattern): bool => request()->routeIs($pattern);
    $pendingPaymentCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('pulse_membership_requests')) {
            $pendingPaymentCount = \App\Models\PulseMembershipRequest::query()->whereIn('status',['submitted','under_review'])->count();
        }
    } catch (\Throwable) {}
    $supportUnreadCount = 0;
    try { $supportUnreadCount = app(\App\Services\PulseSupportService::class)->unreadForAdmin(); } catch (\Throwable) {}
    $investorRequestCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('portfolio_requests')) {
            $investorRequestCount = \App\Models\PortfolioRequest::query()->whereIn('status',['submitted','under_review'])->count();
        }
    } catch (\Throwable) {}
@endphp
<body class="admin-body abs-admin-v1507 abs-admin-v1508 abs-admin-v1509 abs-admin-v1510 abs-admin-v1516 abs-admin-v1521 abs-admin-v1530 abs-admin-v1540 abs-admin-v1550 abs-admin-v1562 abs-admin-v1564 abs-admin-v1565 abs-admin-v1569">
<button class="admin-mobile-menu" type="button" data-admin-menu aria-label="Open administration navigation" aria-expanded="false"><span></span><span></span><span></span></button>
<div class="admin-sidebar-overlay" data-admin-overlay></div>
<aside class="admin-sidebar abs-exec-sidebar" data-admin-sidebar>
    <div class="abs-exec-brand">
        @include('partials.logo')
        <small>ADMIN CONTROL CENTER</small>
    </div>

    <div class="abs-sidebar-toolbar" aria-label="Sidebar controls">
        <button type="button" class="abs-sidebar-tool" data-sidebar-collapse aria-label="Collapse sidebar" title="Collapse / expand sidebar"><span class="tool-icon">⇤</span><span class="tool-label">Collapse</span></button>
        <button type="button" class="abs-sidebar-tool" data-sidebar-hide aria-label="Hide sidebar" title="Hide sidebar"><span class="tool-icon">◐</span><span class="tool-label">Hide</span></button>
    </div>

    @php
        $groupOverview = $is('admin.dashboard') || $is('admin.users*') || $is('admin.pulse.plans') || $is('admin.pulse.access*') || $is('admin.pulse.memberships*');
        $groupStrategy = $is('admin.pulse.strategy-dashboard*') || $is('admin.pulse.price-source') || $is('admin.pulse.latest-prices') || $is('admin.pulse.price-history*') || $is('admin.pulse.scan-signals') || $is('admin.pulse.paper-trades') || $is('admin.pulse.trade-results') || $is('admin.pulse.strategy-performance') || $is('admin.pulse.scan-audit*') || $is('admin.pulse.strategies') || $is('admin.pulse.pairs') || $is('admin.pulse.settings');
        $groupInvestor = $is('admin.private-investors*') || $is('admin.portfolios*');
        $groupRevenue = $is('admin.pulse.rewarded-signals') || $is('admin.ads*');
        $groupSupport = $is('admin.support*');
        $groupComms = $is('admin.enterprise.emails') || $is('admin.enterprise.contacts*') || $is('admin.content*') || $is('admin.enterprise.content*');
        $groupReports = $is('admin.enterprise.settings*') || $is('admin.enterprise.updates*') || $is('admin.enterprise.maintenance*') || $is('admin.enterprise.backups*');
    @endphp
    <nav class="abs-exec-nav abs-categorized-nav" aria-label="ABS administration">
        <section class="abs-nav-group {{ $groupOverview ? 'is-current' : '' }}" data-nav-group="overview">
            <button class="abs-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true"><span>Members &amp; Access</span><i>⌄</i></button>
            <div class="abs-nav-group-links">
                <a class="{{ $is('admin.dashboard')?'active':'' }}" href="{{ route('admin.dashboard') }}" title="Members Overview"><i>⌂</i><span>Members Overview</span></a>
                <a class="{{ $is('admin.users*')?'active':'' }}" href="{{ route('admin.users') }}" title="Member Accounts"><i>♙</i><span>Member Accounts</span></a>
                <a class="{{ $is('admin.pulse.plans')?'active':'' }}" href="{{ route('admin.pulse.plans') }}" title="Packages"><i>▣</i><span>Packages</span></a>
                <a class="{{ $is('admin.pulse.access*')?'active':'' }}" href="{{ route('admin.pulse.access') }}" title="Access & Renewals"><i>◷</i><span>Access &amp; Renewals</span></a>
                <a class="{{ $is('admin.pulse.memberships*')?'active':'' }}" href="{{ route('admin.pulse.memberships') }}" title="Payments & Approvals"><i>▤</i><span>Payments &amp; Approvals</span>@if($pendingPaymentCount)<b class="abs-nav-count">{{ min($pendingPaymentCount,99) }}</b>@endif</a>
            </div>
        </section>

        <section class="abs-nav-group {{ $groupStrategy ? 'is-current' : '' }}" data-nav-group="strategy">
            <button class="abs-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true"><span>Strategy Engine &amp; Evaluation</span><i>⌄</i></button>
            <div class="abs-nav-group-links abs-workflow-links">
                <a class="{{ $is('admin.pulse.strategy-dashboard')?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard') }}" title="Strategy Overview"><i>◫</i><span>Strategy Overview</span></a>
                <a class="{{ $is('admin.pulse.price-source')?'active':'' }}" href="{{ route('admin.pulse.price-source') }}" title="Price Source & Schedule"><i>⌁</i><span>Price Source &amp; Schedule</span></a>
                <a class="{{ $is('admin.pulse.latest-prices')?'active':'' }}" href="{{ route('admin.pulse.latest-prices') }}" title="Latest Market Prices"><i>◉</i><span>Latest Market Prices</span></a>
                <a class="{{ $is('admin.pulse.price-history*')?'active':'' }}" href="{{ route('admin.pulse.price-history') }}" title="Price Sync History"><i>↻</i><span>Price Sync History</span></a>
                <a class="{{ $is('admin.pulse.scan-signals')?'active':'' }}" href="{{ route('admin.pulse.scan-signals') }}" title="Scan & Signals"><i>⌁</i><span>Scan &amp; Signals</span></a>
                <a class="{{ $is('admin.pulse.paper-trades')?'active':'' }}" href="{{ route('admin.pulse.paper-trades') }}" title="Paper Trades"><i>↗</i><span>Paper Trades</span></a>
                <a class="{{ $is('admin.pulse.trade-results')?'active':'' }}" href="{{ route('admin.pulse.trade-results') }}" title="Trade Results"><i>◎</i><span>Trade Results</span></a>
                <a class="{{ $is('admin.pulse.strategy-performance')||$is('admin.pulse.strategies')||$is('admin.pulse.pairs')||$is('admin.pulse.settings')?'active':'' }}" href="{{ route('admin.pulse.strategy-performance') }}" title="Strategy Performance"><i>◆</i><span>Strategy Performance</span></a>
                <a class="{{ $is('admin.pulse.scan-audit*')?'active':'' }}" href="{{ route('admin.pulse.scan-audit') }}" title="Audit & History"><i>≡</i><span>Audit &amp; History</span></a>
            </div>
        </section>

        <section class="abs-nav-group {{ $groupInvestor ? 'is-current' : '' }}" data-nav-group="investors">
            <button class="abs-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true"><span>Private Investors</span><i>⌄</i></button>
            <div class="abs-nav-group-links">
                <a class="{{ $is('admin.private-investors.overview')||$is('admin.portfolios')?'active':'' }}" href="{{ route('admin.private-investors.overview') }}" title="Portfolio Overview"><i>◫</i><span>Portfolio Overview</span></a>
                <a class="{{ $is('admin.private-investors.investors')||$is('admin.private-investors.show')||$is('admin.private-investors.investment-setup')||$is('admin.private-investors.portfolio-values')||$is('admin.private-investors.account-*')?'active':'' }}" href="{{ route('admin.private-investors.investors') }}" title="Investor Accounts"><i>♙</i><span>Investor Accounts</span></a>
                <a class="{{ $is('admin.private-investors.activity')?'active':'' }}" href="{{ route('admin.private-investors.activity') }}" title="Transactions"><i>⇄</i><span>Transactions</span></a>
                <a class="{{ $is('admin.private-investors.monthly-performance')?'active':'' }}" href="{{ route('admin.private-investors.monthly-performance') }}" title="Monthly Progress"><i>◉</i><span>Monthly Progress</span></a>
                <a class="{{ $is('admin.private-investors.statements')?'active':'' }}" href="{{ route('admin.private-investors.statements') }}" title="Monthly Statements"><i>▥</i><span>Statements</span></a>
                <a class="{{ $is('admin.private-investors.requests')?'active':'' }}" href="{{ route('admin.private-investors.requests') }}" title="Investment & Withdrawal Requests"><i>↗</i><span>Requests</span>@if($investorRequestCount)<b class="abs-nav-count">{{ min($investorRequestCount,99) }}</b>@endif</a>
                <a class="{{ $is('admin.private-investors.reports*')?'active':'' }}" href="{{ route('admin.private-investors.reports') }}" title="Portfolio Reports"><i>▤</i><span>Reports</span></a>
            </div>
        </section>

        <section class="abs-nav-group {{ $groupRevenue ? 'is-current' : '' }}" data-nav-group="revenue">
            <button class="abs-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true"><span>Revenue &amp; Advertising</span><i>⌄</i></button>
            <div class="abs-nav-group-links">
                <a class="{{ $is('admin.pulse.rewarded-signals')?'active':'' }}" href="{{ route('admin.pulse.rewarded-signals') }}" title="Rewarded Signal Ads"><i>▶</i><span>Rewarded Signal Ads</span></a>
                <a class="{{ $is('admin.ads*')?'active':'' }}" href="{{ route('admin.ads.index') }}" title="Ads CMS"><i>▤</i><span>Ads CMS</span></a>
            </div>
        </section>

        <section class="abs-nav-group {{ $groupSupport ? 'is-current' : '' }}" data-nav-group="support">
            <button class="abs-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true"><span>Support &amp; Service</span><i>⌄</i></button>
            <div class="abs-nav-group-links">
                <a class="{{ $is('admin.support*')?'active':'' }}" href="{{ route('admin.support.index') }}" title="Pulse Support Inbox"><i>◌</i><span>Support Inbox</span>@if($supportUnreadCount)<b class="abs-nav-count">{{ min($supportUnreadCount,99) }}</b>@endif</a>
            </div>
        </section>

        <section class="abs-nav-group {{ $groupComms ? 'is-current' : '' }}" data-nav-group="communications">
            <button class="abs-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true"><span>Content &amp; Communications</span><i>⌄</i></button>
            <div class="abs-nav-group-links">
                <a class="{{ $is('admin.enterprise.emails')||$is('admin.enterprise.contacts*')?'active':'' }}" href="{{ route('admin.enterprise.emails') }}" title="Alerts & Emails"><i>♧</i><span>Alerts &amp; Emails</span></a>
                <a class="{{ $is('admin.content*')||$is('admin.enterprise.content*')?'active':'' }}" href="{{ route('admin.content.index','news') }}" title="Content CMS"><i>▧</i><span>Content CMS</span></a>
            </div>
        </section>

        <section class="abs-nav-group {{ $groupReports ? 'is-current' : '' }}" data-nav-group="reporting">
            <button class="abs-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true"><span>Reporting &amp; System</span><i>⌄</i></button>
            <div class="abs-nav-group-links">
                <a class="{{ $is('admin.enterprise.settings*')?'active':'' }}" href="{{ route('admin.enterprise.settings') }}" title="Platform Settings"><i>⚙</i><span>Platform Settings</span></a>
                <a class="{{ $is('admin.enterprise.updates*')?'active':'' }}" href="{{ route('admin.enterprise.updates') }}" title="Updates & Recovery"><i>↻</i><span>Updates &amp; Recovery</span></a>
                <a class="{{ $is('admin.enterprise.backups*')?'active':'' }}" href="{{ route('admin.enterprise.backups') }}" title="Database Backups"><i>▦</i><span>Database Backups</span></a>
                <a class="{{ $is('admin.enterprise.maintenance*')?'active':'' }}" href="{{ route('admin.enterprise.maintenance') }}" title="Database Fix"><i>⌁</i><span>Database Fix</span></a>
            </div>
        </section>
    </nav>

    <div class="abs-exec-sidebar-footer">
        <div class="abs-exec-product-card">
            <span>PULSE</span>
            <strong>Trading Intelligence</strong>
            <small>A product of Alpha Block Solutions</small>
        </div>
        <div class="abs-exec-system"><i></i><span><b>Platform online</b><small>Production administration</small></span></div>
        <div class="abs-exec-signature">BUILDING A SMARTER<br>TRADING TOMORROW <em>v15.7.3</em></div>
    </div>
</aside>
<button type="button" class="abs-sidebar-show-tab" data-sidebar-show aria-label="Show administration menu"><span>›</span><b>Show menu</b></button>

<main class="admin-main abs-exec-main">
    <header class="abs-exec-topbar">
        <form class="abs-exec-search" method="GET" action="{{ route('admin.users') }}" role="search"><span>⌕</span><input name="q" aria-label="Search administration" placeholder="Search members, signals, trades, or anything…"><kbd>⌘ K</kbd></form>
        <div class="abs-exec-top-actions">
            @hasSection('page-actions')<div class="abs-exec-page-actions">@yield('page-actions')</div>@endif
            <a class="abs-exec-alert {{ ($pendingPaymentCount || $supportUnreadCount || $investorRequestCount) ? 'has-pending' : '' }}" href="{{ $supportUnreadCount ? route('admin.support.index') : ($investorRequestCount ? route('admin.private-investors.requests') : ($pendingPaymentCount ? route('admin.pulse.memberships') : route('admin.enterprise.contacts'))) }}" aria-label="Open administration alerts">♧@if($supportUnreadCount || $pendingPaymentCount || $investorRequestCount)<b>{{ min($supportUnreadCount + $pendingPaymentCount + $investorRequestCount,99) }}</b>@endif</a>
            <details class="admin-account-menu"><summary class="abs-exec-account"><span>{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>Administrator</small></div><i>⌄</i></summary><div class="admin-account-popover"><a href="{{ route('home') }}">Public website</a><a href="{{ route('logout') }}">Sign out</a></div></details>
        </div>
    </header>

    <section class="abs-exec-product-strip" aria-label="ABS Pulse administration">
        <div class="abs-exec-product-identity"><div><b><span>ABS</span> Pulse</b><small>A PRODUCT OF ALPHA BLOCK SOLUTIONS</small></div></div>
        <div class="abs-exec-product-message">INTELLIGENCE <i>/</i> OPPORTUNITIES <i>/</i> A BRIGHTER TOMORROW</div>
    </section>

    @unless(View::hasSection('hide-heading'))
    <section class="abs-exec-heading">
        <div><small>ALPHA BLOCK SOLUTIONS / ADMIN</small><h1>@yield('heading','ABS Admin')</h1><p>@yield('description','')</p></div>
    </section>
    @endunless

    @if(session('success'))<div class="flash flash-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="flash flash-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="flash flash-error">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
<script src="{{ asset('assets/js/abs-app.js') }}?v={{ @filemtime(public_path('assets/js/abs-app.js')) ?: '13.9' }}" defer></script>
<script src="{{ asset('assets/js/admin-analytics-v1505.js') }}?v={{ @filemtime(public_path('assets/js/admin-analytics-v1505.js')) ?: '15.0.9' }}" defer></script>
<script src="{{ asset('assets/js/admin-navigation-v1521.js') }}?v={{ @filemtime(public_path('assets/js/admin-navigation-v1521.js')) ?: '15.2.1' }}" defer></script>
@stack('scripts')
</body>
</html>
