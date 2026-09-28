<nav class="pi-tabs" aria-label="Investor portfolio navigation">
    <a class="{{ request()->routeIs('private.index') ? 'active' : '' }}" href="{{ route('private.index') }}">Overview</a>
    <a class="{{ request()->routeIs('private.statements*') || request()->routeIs('private.statement*') ? 'active' : '' }}" href="{{ route('private.statements') }}">Statements</a>
    <a class="{{ request()->routeIs('private.transactions') ? 'active' : '' }}" href="{{ route('private.transactions') }}">Transactions</a>
    <a class="{{ request()->routeIs('private.requests*') ? 'active' : '' }}" href="{{ route('private.requests') }}">Requests</a>
</nav>
