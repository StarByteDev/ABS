@extends('layouts.app')
@section('title','Search — Alpha Block Solutions')
@section('content')
<section class="page-hero container compact focused-page-hero">
    <h1>Search Pulse, member services and <span class="gradient-text">ABS market updates.</span></h1>
    <p>Search Pulse, the Private Member Portal and market articles published by Alpha Block Solutions.</p>
    <form class="search-form" method="GET" action="{{ route('search') }}"><input name="q" value="{{ $q }}" placeholder="Search Pulse, private member portal or market news" aria-label="Search Alpha Block Solutions"><button class="button button-primary">Search ABS</button></form>
</section>
<section class="container search-results">
    @forelse($results as $result)
        <a class="panel search-result" href="{{ $result['url'] }}"><small>{{ $result['type'] }}</small><h2>{{ $result['title'] }}</h2><p>{{ $result['excerpt'] }}</p><span>Open result →</span></a>
    @empty
        @if($q)
            <div class="panel empty-state"><h2>No active content matched “{{ $q }}”.</h2><p>Try a broader term or open Pulse, Live Markets or ABS News directly.</p><div class="hero-actions"><a class="button button-ghost" href="{{ route('pulse.entry') }}">Explore Pulse</a><a class="button button-ghost" href="{{ route('news.index') }}">Open ABS News</a></div></div>
        @else
            <div class="panel empty-state"><h2>Enter a topic to search the focused ABS platform.</h2><p>Examples include Pulse, scanner, signals, Binance, risk controls, private member statements or market news.</p></div>
        @endif
    @endforelse
</section>
@endsection
