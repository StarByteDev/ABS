<nav class="pi-account-nav pi-v1570-tabs" aria-label="Investor account sections">
    <a class="{{ request()->routeIs('admin.private-investors.show') ? 'active' : '' }}" href="{{ route('admin.private-investors.show', $account) }}">Summary</a>
    <a class="{{ request()->routeIs('admin.private-investors.investment-setup') ? 'active' : '' }}" href="{{ route('admin.private-investors.investment-setup', $account) }}">Investment Setup</a>
    <a class="{{ request()->routeIs('admin.private-investors.account-performance') ? 'active' : '' }}" href="{{ route('admin.private-investors.account-performance', $account) }}">Monthly Progress</a>
    <a class="{{ request()->routeIs('admin.private-investors.account-transactions') ? 'active' : '' }}" href="{{ route('admin.private-investors.account-transactions', $account) }}">Transactions</a>
    <a class="{{ request()->routeIs('admin.private-investors.account-statements') ? 'active' : '' }}" href="{{ route('admin.private-investors.account-statements', $account) }}">Statements</a>
    <a class="{{ request()->routeIs('admin.private-investors.account-requests') ? 'active' : '' }}" href="{{ route('admin.private-investors.account-requests', $account) }}">Requests</a>
    <a class="{{ request()->routeIs('admin.private-investors.portfolio-values') ? 'active' : '' }}" href="{{ route('admin.private-investors.portfolio-values', $account) }}">Account Controls</a>
</nav>
