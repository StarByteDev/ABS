@php($labRoute = request()->route()?->getName() ?? '')
<nav class="pulse-lab-subnav" aria-label="Pulse strategy navigation">
    <a class="{{ $labRoute==='admin.pulse.strategy-dashboard'?'active':'' }}" href="{{ route('admin.pulse.strategy-dashboard') }}"><span>⌂</span>Strategy Overview</a>
    <a class="{{ $labRoute==='admin.pulse.strategy-performance'?'active':'' }}" href="{{ route('admin.pulse.strategy-performance') }}"><span>◆</span>Performance</a>
    <a class="{{ str_starts_with($labRoute,'admin.pulse.scan-audit')?'active':'' }}" href="{{ route('admin.pulse.scan-audit') }}"><span>≡</span>Audit</a>
    @if(in_array($labRoute,['admin.pulse.strategies','admin.pulse.pairs','admin.pulse.settings'],true))
        <a class="active" href="{{ route('admin.pulse.strategies') }}"><span>⚙</span>Advanced Setup</a>
    @endif
</nav>
