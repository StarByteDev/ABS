<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Secure Alpha Block Solutions member access for Pulse market intelligence and private reporting.">
    <meta name="theme-color" content="#020711">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/png" href="{{ asset('assets/brand/abs-logo-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/brand/abs-logo-512.png') }}">
    <title>@yield('title', 'Member Portal — Alpha Block Solutions')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/abs-auth.css') }}?v={{ @filemtime(public_path('assets/css/abs-auth.css')) ?: '14.3' }}">
    @stack('head')
</head>
<body class="abs-auth-page @yield('body-class')">
    <a class="auth-skip-link" href="#auth-form">@yield('auth-skip-label', 'Skip to account form')</a>

    <header class="auth-site-header">
        <div class="auth-site-header__inner">
            <a class="auth-brand" href="{{ route('home') }}" aria-label="Alpha Block Solutions home">
                <img src="{{ asset('assets/brand/abs-logo-512.png') }}" alt="" width="58" height="58">
                <span class="auth-brand__name">
                    <b>ALPHA <em>BLOCK</em> SOLUTIONS</b>
                </span>
            </a>
            <a class="auth-back-link" href="{{ route('home') }}">
                <span aria-hidden="true">&#8592;</span>
                <span>Back to Website</span>
            </a>
        </div>
    </header>

    <main class="auth-main">
        <section class="auth-intelligence" aria-labelledby="auth-intelligence-title">
            <canvas class="auth-market-canvas" data-auth-market-canvas aria-hidden="true"></canvas>
            <div class="auth-intelligence__shade" aria-hidden="true"></div>
            <div class="auth-intelligence__content">
                <h1 id="auth-intelligence-title">@yield('auth-heading-primary', 'Intelligence that')<br><span>@yield('auth-heading-accent', 'keeps you ahead.')</span></h1>
                <p>@hasSection('auth-intelligence-copy')@yield('auth-intelligence-copy')@else Access verified market insights, Pulse alerts<br class="auth-desktop-break"> and your private member account.@endif</p>

                <ul class="auth-benefits" aria-label="Member portal benefits">
                    <li>
                        <span class="auth-benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 3 20 6v5c0 5.1-3.4 8.5-8 10-4.6-1.5-8-4.9-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>
                        </span>
                        <span>@yield('auth-benefit-one', 'Verified Intelligence')</span>
                    </li>
                    <li>
                        <span class="auth-benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7Z"/><path d="M10 20h4"/></svg>
                        </span>
                        <span>@yield('auth-benefit-two', 'Real-Time Pulse Alerts')</span>
                    </li>
                    <li>
                        <span class="auth-benefit-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        </span>
                        <span>@yield('auth-benefit-three', 'Secure Member Access')</span>
                    </li>
                </ul>
            </div>
        </section>

        <section class="auth-panel-wrap" aria-label="@yield('auth-panel-label', 'Account access')">
            @yield('content')
        </section>
    </main>

    <div class="auth-global-risk">Market intelligence and Pulse signals are informational only, not financial advice. Digital assets involve substantial risk. <a href="{{ route('legal.risk') }}">Risk Disclosure</a> · <a href="{{ route('legal.disclaimer') }}">Disclaimer</a></div>

    <footer class="auth-site-footer">
        <p>&copy; {{ now()->year }} Alpha Block Solutions. All rights reserved.</p>
        <nav aria-label="Legal and support">
            <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
            <i aria-hidden="true"></i>
            <a href="{{ route('legal.terms') }}">Terms &amp; Conditions</a>
            <i aria-hidden="true"></i>
            <a href="{{ route('legal.risk') }}">Risk Disclosure</a>
            <i aria-hidden="true"></i>
            <a href="mailto:{{ config('brand.support_email') }}">Contact Support</a>
        </nav>
    </footer>

    <script src="{{ asset('assets/js/abs-auth.js') }}?v={{ @filemtime(public_path('assets/js/abs-auth.js')) ?: '14.3' }}" defer></script>
    @stack('scripts')
</body>
</html>
