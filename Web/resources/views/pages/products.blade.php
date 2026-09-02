@extends('layouts.app')
@section('title','ABS Services — Pulse & Private Member Portal')
@section('content')
<section class="page-hero container focused-page-hero">
    <h1>Two focused services. <span class="gradient-text">One controlled platform.</span></h1>
    <p>Alpha Block Solutions is concentrating its public product development on Pulse Trading Intelligence and its invitation-only Private Member Portal.</p>
</section>

<section class="container focused-products-page">
    @forelse($products as $product)
        <article id="{{ $product->slug }}" class="panel focused-product-detail {{ $product->slug === 'private-member-portal' ? 'focused-product-private' : '' }}">
            <span class="service-icon accent-{{ $product->accent }}">{{ $product->icon }}</span>
            <div>
                <small>{{ $product->category }}</small>
                <h2>{{ $product->name }}</h2>
                <h4>{{ $product->tagline }}</h4>
                <p>{{ $product->description }}</p>
                <ul>@foreach($product->features ?? [] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
            </div>
            @if($product->slug === 'pulse-trading-intelligence')
                <a class="button button-primary" href="{{ route('pulse.entry') }}">Explore Pulse</a>
            @else
                <a class="button button-ghost" href="{{ route('login', ['service' => 'private']) }}">Authorized Member Login</a>
            @endif
        </article>
    @empty
        <div class="panel empty-state full-span"><h2>Service information is temporarily unavailable.</h2><p>Please contact {{ config('brand.support_email') }} for assistance.</p></div>
    @endforelse
</section>
@endsection
