@extends('layouts.app')
@section('title',($headline['title'] ?? 'Market Headline').' — ABS News')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/abs-news-v1512.css') }}?v={{ @filemtime(public_path('assets/css/abs-news-v1512.css')) ?: '15.6.6' }}">
@endpush
@section('content')
<article class="container live-story-v1561">
    <a class="live-story-back-v1561" href="{{ route('news.index') }}">← Back to Market Intelligence</a>

    <header class="live-story-head-v1561">
        <div class="live-story-meta-v1561">
            <span>{{ strtoupper((string) ($headline['source_name'] ?? 'MARKET SOURCE')) }}</span>
            @if(!empty($headline['category']))<span>{{ strtoupper((string) $headline['category']) }}</span>@endif
            <time>{{ !empty($headline['published_at']) ? $headline['published_at']->format('d M Y · H:i T') : 'Recently published' }}</time>
        </div>
        <h1>{{ $headline['title'] ?? 'Market update' }}</h1>
        @if(!empty($headline['excerpt']))
            <p class="live-story-lead-v1561">{{ $headline['excerpt'] }}</p>
        @endif
    </header>

    @if(!empty($headline['image_url']))
        <figure class="live-story-image-v1561">
            <img src="{{ $headline['image_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.closest('figure').remove()">
        </figure>
    @endif

    <section class="live-story-snapshot-v1561" aria-label="Pulse market read">
        <article><span>MARKET THEME</span><strong>{{ $marketBrief['theme'] ?? 'Market Development' }}</strong></article>
        <article><span>RISK READ</span><strong class="{{ $marketBrief['bias']['class'] ?? 'neutral' }}">{{ $marketBrief['bias']['label'] ?? 'Mixed / Neutral' }}</strong></article>
        <article><span>MARKET IMPACT</span><strong>{{ strtoupper($marketBrief['impact'] ?? 'Medium') }}</strong></article>
        <article><span>ASSETS IN FOCUS</span><strong>{{ implode(' · ', $marketBrief['assets'] ?? ['Broad Crypto']) }}</strong></article>
    </section>

    <section class="live-story-grid-v1561">
        <div class="live-story-main-v1561">
            <section class="live-story-panel-v1561">
                <div class="live-story-panel-head-v1561"><span>MARKET BRIEF</span><h2>What happened</h2></div>
                <div class="live-story-copy-v1561">
                    <p>{{ ($headline['detail'] ?? null) ?: (($headline['summary'] ?? null) ?: ($headline['excerpt'] ?? 'Market information is updating.')) }}</p>
                </div>
            </section>

            <section class="live-story-panel-v1561">
                <div class="live-story-panel-head-v1561"><span>PULSE CONTEXT</span><h2>Why it matters</h2></div>
                <div class="live-story-copy-v1561">
                    <p>{{ $marketBrief['significance'] ?? 'Monitor price, liquidity and positioning for confirmation of the market reaction.' }}</p>
                </div>
            </section>

            <section class="live-story-panel-v1561">
                <div class="live-story-panel-head-v1561"><span>MARKET WATCH</span><h2>What to monitor next</h2></div>
                <ul class="live-story-watch-v1561">
                    @foreach(($marketBrief['watch'] ?? []) as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="live-story-side-v1561">
            <section class="live-story-source-v1561">
                <span>SOURCE</span>
                <strong>{{ $headline['source_name'] ?? 'Market Source' }}</strong>
                @if(!empty($headline['author_name']))<small>{{ $headline['author_name'] }}</small>@endif
                <dl>
                    <div><dt>Published</dt><dd>{{ !empty($headline['published_at']) ? $headline['published_at']->format('d M Y · H:i') : 'Recent' }}</dd></div>
                    <div><dt>Category</dt><dd>{{ $headline['category'] ?? 'Market News' }}</dd></div>
                    <div><dt>Coverage</dt><dd>{{ implode(', ', $marketBrief['assets'] ?? ['Broad Crypto']) }}</dd></div>
                </dl>
            </section>

            <section class="live-story-trade-note-v1561">
                <span>TRADING DISCIPLINE</span>
                <p>Use the headline as context, then confirm direction with price structure, volume, liquidity and the ABS Pulse strategy signal before acting.</p>
            </section>
        </aside>
    </section>

    <div class="live-story-actions-v1561">
        <a class="button button-primary" href="{{ route('news.index') }}">More Market Intelligence</a>
        <a class="button button-ghost" href="{{ route('pulse.entry') }}">Open Pulse Intelligence</a>
    </div>

    <div class="macro-v1512-note"><strong>Market &amp; risk notice:</strong> News and market data can change quickly. ABS market context is provided for informational and educational purposes and does not guarantee any market outcome. <a href="{{ route('legal.disclaimer') }}">Read disclaimer</a>.</div>
</article>
@endsection
