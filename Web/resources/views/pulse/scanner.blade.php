@extends('pulse.layout')
@section('title','Find Best Signal')
@section('heading','Find Best Signal')
@section('content')
<div class="pp-page pp-scanner">
    <header class="pp-page-head">
        <div class="pp-title">
            <h1>Find Best Signal</h1>
            <p>ABS automatically evaluates your Admin-defined market universe across 15M and 4H using the existing Pulse strategy engine, then unlocks only the strongest qualifying setup.</p>
        </div>
        <div class="pp-head-actions">
            <a class="pp-button muted" href="{{ route('pulse.membership.index') }}">Package Access</a>
            <form method="POST" action="{{ route('pulse.scanner.run') }}" data-run-market-scan data-refresh-url="{{ route('pulse.scanner.refresh') }}">@csrf
                <button class="pp-button primary" type="submit" data-scan-run-button>@include('pulse.partials.icon', ['name' => 'pulse']) <span data-scan-run-label>Find Best Signal</span></button>
            </form>
            <div class="pp-environment"><b>Best Signal included</b><span class="{{ $page['connection_ready'] ? 'connected' : '' }}">{{ $page['connection_ready'] ? 'Trading connection ready' : 'Signal intelligence ready' }}</span><small>Last scan: <span data-scanner-last-scan>{{ $page['latest_run']?->completed_at?->diffForHumans() ?? 'not yet' }}</span></small></div>
        </div>
    </header>
    <div class="pp-async-status" data-scan-action-message hidden></div>

    @if($errors->has('scanner'))<div class="pulse-notice danger">{{ $errors->first('scanner') }}</div>@endif
    @if(session('success'))<div class="pulse-notice success">{{ session('success') }}</div>@endif

    @include('pulse.partials.scanner-metrics')
    @include('pulse.partials.scanner-results')
    @include('pulse.partials.scanner-bottom')
</div>
@endsection
