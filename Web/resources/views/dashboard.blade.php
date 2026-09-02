@extends('pulse.layout')
@section('title','Pulse Dashboard — Alpha Block Solutions')
@section('heading','Pulse Dashboard')
@php($workspaceTarget = auth()->user()?->hasPulseAccess() ? route('pulse.dashboard') : route('pulse.access'))
@push('head')<meta http-equiv="refresh" content="0;url={{ $workspaceTarget }}">@endpush
@section('content')
<section class="pp-page">
    <article class="pp-card" style="max-width:760px;margin:42px auto;padding:28px">
        <h1 style="margin:0 0 8px">Opening your Pulse Dashboard</h1>
        <p class="pp-muted" style="margin:0 0 18px">Your signed-in ABS experience now uses the finalized Pulse dashboard and navigation.</p>
        <a class="pp-button primary" href="{{ $workspaceTarget }}">Continue to Pulse</a>
    </article>
</section>
@endsection
