@extends('pulse.layout')
@section('title','Strategy Intelligence')
@section('heading','Strategy Intelligence')
@section('content')
<div class="pp-page">
    <header class="pp-page-head"><div class="pp-title"><h1>Strategy Intelligence</h1><p>ABS V15 manages strategy selection, weighting, market universe, timeframes and qualification rules at Admin/package level. Members do not configure the signal engine.</p></div><div class="pp-head-actions"><a class="pp-button primary" href="{{ route('pulse.scanner') }}">Find Best Signal</a></div></header>
    <section class="pp-card"><div class="pp-section-head"><h2>Automatic by design</h2><span>Admin controlled</span></div><p>Your plan's strategy intelligence is applied automatically across 15M and 4H. When a signal qualifies, its signal page can show the strategy evidence that supported that specific unlocked result, without exposing or changing Admin engine configuration.</p></section>
</div>
@endsection
