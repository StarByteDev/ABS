@extends('layouts.app')
@section('title','ABS Services — Pulse Trading Intelligence')
@section('content')
<section class="page-hero container focused-page-hero">
    <h1>Pulse Trading Intelligence. <span class="gradient-text">One controlled platform.</span></h1>
    <p>Alpha Block Solutions is focused on Pulse Trading Intelligence, live market awareness and member trading-intelligence services.</p>
</section>

<section class="container focused-products-page">
    @forelse($products as $product)
        <article id="{{ $product->slug }}" class="panel focused-product-detail">
            <span class="service-icon accent-{{ $product->accent }}">{{ $product->icon }}</span>
            <div>
                <small>{{ $product->category }}</small>
                <h2>{{ $product->name }}</h2>
                <h4>{{ $product->tagline }}</h4>
                <p>{{ $product->description }}</p>
                <ul>@foreach($product->features ?? [] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
            </div>
            <a class="button button-primary" href="{{ route('pulse.entry') }}">Explore Pulse</a>
        </article>
    @empty
        <div class="panel empty-state full-span"><h2>Service information is temporarily unavailable.</h2><p>Please contact {{ config('brand.support_email') }} for assistance.</p></div>
    @endforelse
</section>
@endsection
