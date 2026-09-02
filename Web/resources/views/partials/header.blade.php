<header class="site-header final-site-header" data-header>
    <div class="container nav-wrap final-nav-wrap">
        @include('partials.logo')

        <button class="mobile-menu-button" type="button" data-menu-button aria-label="Open menu" aria-expanded="false">☰</button>

        <nav class="main-nav final-main-nav" data-main-nav aria-label="Primary navigation">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
            <a href="{{ route('pulse.entry') }}" class="{{ request()->routeIs('pulse.*') ? 'active' : '' }}">Pulse Intelligence</a>
            <a href="{{ route('news.index') }}" class="{{ request()->routeIs('news.*') ? 'active' : '' }}">Market News</a>
            <a href="{{ route('pulse.entry') }}#plans">Membership</a>
            <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">About</a>
            <a href="{{ route('home') }}#contact">Contact</a>
        </nav>

        <div class="nav-actions final-nav-actions">
            @auth
                @if(auth()->user()->isAdmin())<a class="final-header-button outline" href="{{ route('admin.dashboard') }}">Admin</a>@endif
                @if(!auth()->user()->isAdmin())
                    <a class="final-header-button gold" href="{{ auth()->user()->hasPulseAccess() ? route('pulse.dashboard') : route('pulse.access') }}">Dashboard</a>
                @else
                    <a class="final-header-button gold" href="{{ route('admin.dashboard') }}">Dashboard</a>
                @endif
                <a class="final-header-button outline" href="{{ route('profile') }}">Profile</a>
                <a class="final-header-button outline" href="{{ route('logout') }}">Logout</a>
            @else
                <a class="final-header-button outline" href="{{ route('login') }}">Sign In</a>
                <a class="final-header-button gold" href="{{ route('register') }}">Get Started</a>
            @endauth
        </div>
    </div>
</header>
