<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Pulse trading intelligence inside Alpha Block Solutions.">
    <title>@yield('title','Pulse Trading Intelligence')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/abs-app.css') }}?v={{ @filemtime(public_path('assets/css/abs-app.css')) ?: '14.8.11' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pulse-app.css') }}?v={{ @filemtime(public_path('assets/css/pulse-app.css')) ?: '14.8.11' }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pulse-premium.css') }}?v={{ @filemtime(public_path('assets/css/pulse-premium.css')) ?: '14.8.11' }}">
    @stack('head')
</head>
<body class="pulse-body" data-pulse-route="{{ request()->route()?->getName() }}">
<script>try{if(window.localStorage.getItem('abs.pulse.sidebar.collapsed.v1')==='1')document.body.classList.add('pulse-sidebar-collapsed')}catch(error){}</script>
<?php
    $pulseSettings = auth()->user()?->pulseSettings;
    $pulseAccessService = app(\App\Services\PulseAccessService::class);
    $pulseUser = auth()->user();
    $pulseHasActive = $pulseUser?->hasPulseAccess() ?? false;
    $pulseAccess = $pulseUser?->pulseAccess()->with('plan')->first();
    $pulsePlanName = $pulseAccess?->plan?->name ?? ($pulseUser?->isAdmin() ? 'Administrator' : 'Pulse Access');
    $pulseRoleLabel = $pulseUser?->isAdmin()
        ? 'Administrator'
        : (str_contains(strtolower($pulsePlanName), 'professional') ? 'Pro Trader' : 'Pulse Member');
    $pulseMembershipService = app(\App\Services\PulseMembershipService::class);
    $pulseNextPlan = $pulseUser?->isAdmin() ? null : $pulseMembershipService->nextUpgradePlan($pulseAccess);
    $pulseHasPaidCurrentPlan = $pulseAccess?->isActive() && $pulseAccess?->plan && ! $pulseAccess->plan->is_trial;
    $pulsePlanChipLabel = $pulseNextPlan
        ? (($pulseHasPaidCurrentPlan ? 'Upgrade to ' : 'Choose ').$pulseNextPlan->name)
        : (($pulseAccess?->isActive() ? $pulsePlanName.' · Active' : 'View Pulse Plans'));
    $pulsePlanChipTitle = $pulseNextPlan
        ? (($pulseHasPaidCurrentPlan ? 'Next tier: ' : 'Recommended plan: ').$pulseNextPlan->name)
        : ($pulseAccess?->isActive() ? $pulsePlanName.' is your highest available tier' : 'View available Pulse plans');
    $pulseUnreadAlerts = isset($unreadAlerts)
        ? (int) $unreadAlerts
        : \App\Models\PulseAlert::query()->where('user_id', $pulseUser?->id)->where('is_read', false)->count();
    $pulseAlertBadge = $pulseUnreadAlerts > 0 ? min($pulseUnreadAlerts, 99) : null;
    $pulseUsage = app(\App\Services\PulseUsageService::class)->today($pulseUser);
    $pulseScanQuotaText = data_get($pulseUsage, 'scans.unlimited')
        ? 'Unlimited'
        : data_get($pulseUsage, 'scans.remaining', 0).' / '.data_get($pulseUsage, 'scans.limit', 0).' left';
    $pulseSignalQuotaText = data_get($pulseUsage, 'signals.unlimited')
        ? 'Unlimited'
        : data_get($pulseUsage, 'signals.remaining', 0).' / '.data_get($pulseUsage, 'signals.limit', 0).' left';
    // V14.7.9: keep the finalized signed-in navigation visible as one stable
    // product shell. Plan/account permissions restrict access to a destination;
    // they no longer make core navigation disappear or fall back to the legacy
    // member UI. This preserves the exact information architecture approved for
    // Dashboard, Scanner, Signals, Strategies, Execution and account controls.
    $pulseNavItems = [
        ['enabled' => $pulseHasActive || $pulseUser?->isAdmin(), 'match' => 'pulse.dashboard', 'route' => 'pulse.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'scanner', false), 'match' => 'pulse.scanner*', 'route' => 'pulse.scanner', 'icon' => 'search', 'label' => 'Market Scanner'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'signals', false), 'match' => 'pulse.signals*', 'route' => 'pulse.signals.index', 'icon' => 'pulse', 'label' => 'Signals'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'signals', false), 'match' => 'pulse.strategies', 'route' => 'pulse.strategies', 'icon' => 'strategy', 'label' => 'Strategies'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'orders', false), 'match' => 'pulse.positions', 'route' => 'pulse.positions', 'icon' => 'briefcase', 'label' => 'Open Positions'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'trades', false), 'match' => 'pulse.trades*', 'route' => 'pulse.trades.index', 'icon' => 'history', 'label' => 'Trade History'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'alerts', false), 'match' => 'pulse.alerts*', 'route' => 'pulse.alerts.index', 'icon' => 'bell', 'label' => 'Alerts & Watchlists'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'reports', false), 'match' => 'pulse.reports', 'route' => 'pulse.reports', 'icon' => 'bars', 'label' => 'Reports & P&L'],
        ['enabled' => $pulseAccessService->allows($pulseUser, 'settings', false), 'match' => ['pulse.settings*','pulse.risk.*','pulse.binance*'], 'route' => 'pulse.settings.edit', 'icon' => 'settings', 'label' => 'Trading Setup'],
    ];
?>

<svg class="pulse-svg-sprite" aria-hidden="true" focusable="false">
    <symbol id="pulse-icon-dashboard" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
    <symbol id="pulse-icon-search" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></symbol>
    <symbol id="pulse-icon-pulse" viewBox="0 0 24 24"><path d="M2 12h4l2-6 4 12 3-9 2 6h5"/></symbol>
    <symbol id="pulse-icon-strategy" viewBox="0 0 24 24"><path d="M7 4h10l-2 5 3 3v8H6v-5l5-5-4-2z"/><path d="M8 16h8"/></symbol>
    <symbol id="pulse-icon-send" viewBox="0 0 24 24"><path d="m3 11 18-8-8 18-2-8-8-2z"/><path d="m11 13 5-5"/></symbol>
    <symbol id="pulse-icon-briefcase" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5c0-1 1-2 2-2h4c1 0 2 1 2 2v2M3 12h18M10 12v2h4v-2"/></symbol>
    <symbol id="pulse-icon-history" viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2-5.4L3 9"/><path d="M3 4v5h5M12 7v5l3 2"/></symbol>
    <symbol id="pulse-icon-shield" viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3z"/><path d="m9 12 2 2 4-5"/></symbol>
    <symbol id="pulse-icon-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol>
    <symbol id="pulse-icon-bars" viewBox="0 0 24 24"><rect x="4" y="12" width="3" height="8" rx="1"/><rect x="10.5" y="7" width="3" height="13" rx="1"/><rect x="17" y="3" width="3" height="17" rx="1"/></symbol>
    <symbol id="pulse-icon-binance" viewBox="0 0 24 24"><path d="m12 3 3 3-3 3-3-3 3-3zM6 9l3 3-3 3-3-3 3-3zM18 9l3 3-3 3-3-3 3-3zM12 15l3 3-3 3-3-3 3-3zM12 9l3 3-3 3-3-3 3-3z"/></symbol>
    <symbol id="pulse-icon-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 14.5l2 1.2-2 3.5-2.2-1a8 8 0 0 1-2.3 1.3L14.2 22h-4.4l-.3-2.5a8 8 0 0 1-2.3-1.3l-2.2 1-2-3.5 2-1.2a8 8 0 0 1 0-2.6L3 10.7l2-3.5 2.2 1a8 8 0 0 1 2.3-1.3L9.8 4h4.4l.3 2.9a8 8 0 0 1 2.3 1.3l2.2-1 2 3.5-2 1.2a8 8 0 0 1 0 2.6z"/></symbol>
    <symbol id="pulse-icon-chevron-left" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></symbol>
    <symbol id="pulse-icon-chevron-right" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
    <symbol id="pulse-icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-5 4-7 8-7s7 2 8 7"/></symbol>
    <symbol id="pulse-icon-diamond" viewBox="0 0 24 24"><path d="M3 8h18l-9 12L3 8zM7 8l3-4h4l3 4M8 8l4 12 4-12"/></symbol>
    <symbol id="pulse-icon-card" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></symbol>
    <symbol id="pulse-icon-trend" viewBox="0 0 24 24"><path d="m4 17 5-5 4 3 7-9"/><path d="M15 6h5v5"/></symbol>
    <symbol id="pulse-icon-target" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/></symbol>
    <symbol id="pulse-icon-crosshair" viewBox="0 0 24 24"><circle cx="12" cy="12" r="7"/><path d="M12 2v5M12 17v5M2 12h5M17 12h5"/></symbol>
    <symbol id="pulse-icon-arrow-up" viewBox="0 0 24 24"><path d="M12 20V4M5 11l7-7 7 7"/></symbol>
    <symbol id="pulse-icon-arrow-down" viewBox="0 0 24 24"><path d="M12 4v16M5 13l7 7 7-7"/></symbol>
    <symbol id="pulse-icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="pulse-icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="pulse-icon-refresh" viewBox="0 0 24 24"><path d="M20 6v5h-5M4 18v-5h5M18 9a7 7 0 0 0-12-2L4 11M6 15a7 7 0 0 0 12 2l2-4"/></symbol>
    <symbol id="pulse-icon-list" viewBox="0 0 24 24"><path d="M9 6h12M9 12h12M9 18h12M3 6h1M3 12h1M3 18h1"/></symbol>
    <symbol id="pulse-icon-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></symbol>
    <symbol id="pulse-icon-book" viewBox="0 0 24 24"><path d="M4 5c4-1 6 0 8 2v13c-2-2-4-3-8-2V5zM20 5c-4-1-6 0-8 2v13c2-2 4-3 8-2V5z"/></symbol>
    <symbol id="pulse-icon-edit" viewBox="0 0 24 24"><path d="m4 20 4-1 11-11-3-3L5 16l-1 4zM14 7l3 3"/></symbol>
    <symbol id="pulse-icon-alert" viewBox="0 0 24 24"><path d="M12 3 22 20H2L12 3z"/><path d="M12 9v5M12 17h.01"/></symbol>
    <symbol id="pulse-icon-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/></symbol>
    <symbol id="pulse-icon-lock" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
    <symbol id="pulse-icon-wallet" viewBox="0 0 24 24"><path d="M4 6h14a2 2 0 0 1 2 2v11H4a2 2 0 0 1-2-2V6h2z"/><path d="M4 6V4h13v2M15 12h5v4h-5a2 2 0 0 1 0-4z"/></symbol>
    <symbol id="pulse-icon-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></symbol>
    <symbol id="pulse-icon-document" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6V3zM15 3v5h4M9 12h7M9 16h7"/></symbol>
    <symbol id="pulse-icon-breakout" viewBox="0 0 24 24"><path d="M4 18h4v-4h4v-4h4V6h4M15 6h5v5"/></symbol>
    <symbol id="pulse-icon-reversal" viewBox="0 0 24 24"><path d="M5 18V9a7 7 0 0 1 14 0v9M15 14l4 4 4-4"/></symbol>
    <symbol id="pulse-icon-coin" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 7h4a3 3 0 0 1 0 6H9V7zM9 13h5a3 3 0 0 1 0 6H9V5M12 3v3M12 19v2"/></symbol>
    <symbol id="pulse-icon-share" viewBox="0 0 24 24"><circle cx="12" cy="5" r="3"/><circle cx="5" cy="18" r="3"/><circle cx="19" cy="18" r="3"/><path d="m10.5 7.5-4 7.5M13.5 7.5l4 7.5M8 18h8"/></symbol>
    <symbol id="pulse-icon-pie" viewBox="0 0 24 24"><path d="M11 3a9 9 0 1 0 10 10H11V3z"/><path d="M14 3v7h7a8 8 0 0 0-7-7z"/></symbol>
    <symbol id="pulse-icon-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c.5-4 2.5-6 6-6s5.5 2 6 6M14 15c3-1 6 1 7 5"/></symbol>
</svg>

<div class="pulse-app-shell">
    <header class="pulse-global-header">
        <a href="{{ route('home') }}" class="pulse-global-brand" aria-label="Alpha Block Solutions home">
            <img src="{{ asset('assets/brand/abs-logo-512.png') }}" alt="Alpha Block Solutions" width="44" height="44">
            <span>ALPHA <em>BLOCK</em> SOLUTIONS</span>
        </a>

        <nav class="pulse-global-nav" aria-label="Alpha Block Solutions navigation">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('markets') }}">Market Intelligence</a>
            <a href="{{ route('pulse.entry') }}">Services</a>
            <a href="{{ route('pulse.entry') }}#plans">Plans</a>
            <a href="{{ route('about') }}">About</a>
            <a href="{{ route('home') }}#contact">Contact</a>
        </nav>

        <div class="pulse-global-actions">
            <?php if ($pulseAccessService->allows($pulseUser, 'alerts', false)): ?>
                <a class="pulse-header-icon" href="{{ route('pulse.alerts.index') }}" aria-label="Open alerts">
                    @include('pulse.partials.icon', ['name' => 'bell'])
                    <?php if ($pulseAlertBadge !== null): ?><b>{{ $pulseAlertBadge }}</b><?php endif; ?>
                </a>
            <?php endif; ?>
            <details class="pulse-account-menu">
                <summary class="pulse-profile-link" aria-label="Open account menu" title="Account">
                    @include('pulse.partials.icon', ['name' => 'user'])
                    <i></i>
                </summary>
                <div class="pulse-account-popover">
                    <div class="pulse-account-identity"><b>{{ $pulseUser->name }}</b><span>{{ $pulsePlanName }} · {{ $pulseRoleLabel }}</span></div>
                    <div class="pulse-account-usage">
                        <span><small>Scans today</small><b data-usage-scans>{{ $pulseScanQuotaText }}</b></span>
                        <span><small>Signals today</small><b data-usage-signals>{{ $pulseSignalQuotaText }}</b></span>
                    </div>
                    <a href="{{ route('profile') }}">@include('pulse.partials.icon', ['name' => 'user']) <span>Profile & Account</span></a>
                    <a href="{{ route('pulse.plans') }}">@include('pulse.partials.icon', ['name' => 'diamond']) <span>Plan & Limits</span></a>
                    <a class="pulse-account-logout" href="{{ route('logout') }}">@include('pulse.partials.icon', ['name' => 'send']) <span>Sign Out</span></a>
                </div>
            </details>
        </div>
    </header>

    <div class="pulse-workspace">
        <aside class="pulse-sidebar" id="pulse-sidebar">
            <nav class="pulse-nav" aria-label="Pulse navigation">
                <?php foreach ($pulseNavItems as $pulseNavItem): ?>
                    <?php
                        $pulseItemEnabled = (bool) ($pulseNavItem['enabled'] ?? false);
                        $pulseItemActive = request()->routeIs(...(array) $pulseNavItem['match']);
                        $pulseItemHref = $pulseItemEnabled ? route($pulseNavItem['route']) : route('pulse.access');
                        $pulseItemTitle = $pulseItemEnabled ? $pulseNavItem['label'] : $pulseNavItem['label'].' — not included in current access';
                    ?>
                    <a class="{{ $pulseItemActive ? 'active ' : '' }}{{ $pulseItemEnabled ? '' : 'locked' }}" href="{{ $pulseItemHref }}" data-pulse-tooltip="{{ $pulseItemTitle }}" title="{{ $pulseItemTitle }}" @if(! $pulseItemEnabled) aria-disabled="true" @endif>
                        @include('pulse.partials.icon', ['name' => $pulseNavItem['icon'], 'class' => $pulseNavItem['icon_class'] ?? ''])
                        <span>{{ $pulseNavItem['label'] }}</span>
                        <?php if (! $pulseItemEnabled): ?><small class="pulse-nav-lock">LOCKED</small><?php elseif (! empty($pulseNavItem['badge'])): ?><small>{{ $pulseNavItem['badge'] }}</small><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="pulse-sidebar-account">
                <div class="pulse-usage-strip" title="Daily plan usage resets at midnight">
                    <span><small>Scans</small><b data-usage-scans>{{ $pulseScanQuotaText }}</b></span>
                    <span><small>Signals</small><b data-usage-signals>{{ $pulseSignalQuotaText }}</b></span>
                </div>
                <a class="pulse-plan-chip {{ $pulseNextPlan ? 'upgrade-available' : 'current-tier' }}" href="{{ $pulseNextPlan ? route('pulse.membership.checkout', $pulseNextPlan) : route('pulse.plans') }}" data-pulse-tooltip="{{ $pulsePlanChipTitle }}" title="{{ $pulsePlanChipTitle }}">
                    @include('pulse.partials.icon', ['name' => 'diamond'])
                    <span class="pulse-plan-chip-copy">
                        @if($pulseNextPlan)<small>{{ $pulseHasPaidCurrentPlan ? 'NEXT TIER' : 'AVAILABLE PLAN' }}</small>@endif
                        <b>{{ $pulsePlanChipLabel }}</b>
                    </span>
                </a>
                <a class="pulse-user-card" href="{{ route('profile') }}" data-pulse-tooltip="My Profile" title="My Profile">
                    <span class="pulse-user-avatar">@include('pulse.partials.icon', ['name' => 'user'])</span>
                    <span class="pulse-user-copy"><b>{{ $pulseUser->name }}</b><small>{{ $pulseRoleLabel }}</small></span>
                    <span class="pulse-user-chevron" aria-hidden="true">@include('pulse.partials.icon', ['name' => 'chevron-right'])</span>
                </a>
            </div>
        </aside>

        <button class="pulse-sidebar-toggle" type="button" data-pulse-sidebar-toggle aria-label="Collapse navigation" aria-expanded="true">
            <span class="expanded-icon">@include('pulse.partials.icon', ['name' => 'chevron-left'])</span>
            <span class="collapsed-icon">@include('pulse.partials.icon', ['name' => 'chevron-right'])</span>
        </button>

        <main class="pulse-main">
            <div class="pulse-mobile-toolbar">
                <button type="button" data-pulse-menu aria-label="Open Pulse navigation" aria-expanded="false">☰</button>
                <span>@yield('heading','Pulse Trading Intelligence')</span>
            </div>

            <?php if (session('success')): ?><div class="flash flash-success">{{ session('success') }}</div><?php endif; ?>
            <?php if (session('warning')): ?><div class="flash flash-warning">{{ session('warning') }}</div><?php endif; ?>
            <?php if ($errors->any()): ?><div class="flash flash-error"><strong>Please review:</strong> {{ $errors->first() }}</div><?php endif; ?>

            <section class="pulse-content">
                <?php if ($pulseSettings?->emergency_stop): ?>
                    <div class="pulse-automation-banner"><strong>Emergency stop is active.</strong> New manual and automatic execution is blocked. Review exchange orders and positions before disabling it.</div>
                <?php elseif ($pulseSettings?->execution_mode === 'automatic'): ?>
                    <div class="pulse-automation-banner enabled"><strong>Automatic mode selected.</strong> Execution remains subject to your plan, account permissions and platform controls.</div>
                <?php endif; ?>
                @yield('content')
            </section>
        </main>
    </div>
    <button class="pulse-sidebar-scrim" type="button" data-pulse-menu aria-label="Close Pulse navigation"></button>
</div>

<script src="{{ asset('assets/js/abs-app.js') }}?v={{ @filemtime(public_path('assets/js/abs-app.js')) ?: '14.8.11' }}" defer></script>
<script src="{{ asset('assets/js/pulse-app.js') }}?v={{ @filemtime(public_path('assets/js/pulse-app.js')) ?: '14.8.11' }}" defer></script>
<script src="{{ asset('assets/js/pulse-premium.js') }}?v={{ @filemtime(public_path('assets/js/pulse-premium.js')) ?: '14.8.11' }}" defer></script>
@stack('scripts')
</body>
</html>
