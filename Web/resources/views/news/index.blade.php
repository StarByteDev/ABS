@extends('layouts.app')
@section('title','ABS News & Economic Calendar — Alpha Block Solutions')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/abs-news-v1512.css') }}?v={{ @filemtime(public_path('assets/css/abs-news-v1512.css')) ?: '15.1.2' }}">
@endpush
@section('content')
<section class="container news-hero-v1512">
    <span class="news-kicker-v1512">ABS NEWS · MARKET & MACRO INTELLIGENCE</span>
    <h1>Know what can move the market.<br><span>Understand why it matters.</span></h1>
    <p>Track major economic releases and verified crypto-industry headlines in one place, with simple explanations instead of economic jargon.</p>
    <div class="news-hero-pills"><span>{{ $macroTimezone }} time</span><span>Previous · Forecast · Actual</span><span>Crypto impact explained</span></div>
</section>

<section class="container macro-v1512" id="economic-calendar">
    <div class="macro-v1512-head">
        <div><span class="news-kicker-v1512">ECONOMIC CALENDAR</span><h2>Market-moving events</h2><p>CPI, PPI, PCE, FOMC, employment, GDP, retail sales, PMI and other releases that can influence crypto risk sentiment.</p></div>
        <div class="macro-v1512-legend"><span class="high">High</span><span class="medium">Medium</span><span class="low">Low</span></div>
    </div>

    <nav class="macro-v1512-tabs" aria-label="Economic calendar period">
        <a class="{{ $macroMode==='today' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'today']) }}"><span>Today</span><b>{{ number_format($macroCounts['today']) }}</b></a>
        <a class="{{ $macroMode==='upcoming' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'upcoming']) }}"><span>Upcoming</span><b>{{ number_format($macroCounts['upcoming']) }}</b></a>
        <a class="{{ $macroMode==='history' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'history']) }}"><span>Previous Releases</span><b>{{ number_format($macroCounts['history']) }}</b></a>
        <a class="{{ $macroMode==='all' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'all']) }}"><span>All</span><b>↕</b></a>
    </nav>

    <div class="macro-v1512-list">
        @forelse($macroEvents as $event)
            @php
                $released = filled($event->actual_value);
                $impactClass = strtolower($event->impact ?: 'medium');
                $cryptoClass = strtolower($event->crypto_impact ?: 'mixed');
            @endphp
            <article class="macro-v1512-event {{ $impactClass }}">
                <div class="macro-v1512-time"><b>{{ $event->event_at?->format('H:i') ?: '—' }}</b><span>{{ $event->event_at?->format('d M') ?: 'Unscheduled' }}</span></div>
                <div class="macro-v1512-event-main">
                    <div class="macro-v1512-title"><span class="macro-impact {{ $impactClass }}">{{ strtoupper($event->impact ?: 'MEDIUM') }}</span><small>{{ $event->country ?: 'Global' }}{{ $event->currency ? ' · '.$event->currency : '' }}</small><h3>{{ $event->title }}</h3></div>
                    <div class="macro-v1512-values"><span><small>Previous</small><b>{{ $event->previous_value ?: '—' }}</b></span><span><small>Forecast</small><b>{{ $event->forecast_value ?: '—' }}</b></span><span class="actual"><small>Actual</small><b>{{ $event->actual_value ?: 'Pending' }}</b></span></div>
                </div>
                <div class="macro-v1512-context {{ $cryptoClass }}">
                    <span>{{ $released ? 'Released' : ($event->event_at?->isFuture() ? 'Upcoming' : 'Awaiting actual') }} · {{ ucfirst($event->crypto_impact ?: 'mixed') }} crypto context</span>
                    @if($event->easy_explanation)
                        <p><b>In simple words:</b> {{ $event->easy_explanation }}</p>
                    @endif
                    @if($event->crypto_impact_summary)
                        <p><b>Possible crypto effect:</b> {{ $event->crypto_impact_summary }}</p>
                    @endif
                    <div>
                        @if($event->source_url)
                            <a href="{{ $event->source_url }}" target="_blank" rel="noopener noreferrer">Source ↗</a>
                        @elseif($event->source)
                            <span>{{ $event->source }}</span>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="macro-v1512-empty">
                <span>◷</span><h3>{{ $macroMode==='history' ? 'No previous releases are stored yet.' : 'No events are available in this period yet.' }}</h3>
                <p>{{ $calendarConfigured ? 'The calendar is connected. Try another period or refresh again after the next provider sync.' : 'The economic calendar will appear here as soon as the ABS data source is connected and synchronized.' }}</p>
            </div>
        @endforelse
    </div>

    <div class="macro-v1512-note"><strong>Market data notice:</strong> Forecasts are estimates and actual readings can be revised. Crypto context is educational market interpretation, not a prediction or trading instruction. <a href="{{ route('legal.disclaimer') }}">Read disclaimer</a>.</div>
</section>

<section class="container live-news-section news-live-v1512">
    <div class="section-heading-row"><div><span class="news-kicker-v1512">LIVE HEADLINES</span><h2>Verified Industry Headlines</h2><p>Open the original publisher directly for the full story.</p></div><button class="button button-ghost button-small" type="button" data-refresh-live-news>Refresh Headlines</button></div>
    <div class="live-headline-grid" data-live-news-list data-live-news-mode="page" data-live-news-limit="12">
        @forelse($liveHeadlines as $headline)
            <a class="panel live-headline-card" data-live-news-item data-external="1" href="{{ $headline['url'] }}" target="_blank" rel="noopener noreferrer">
                <div class="headline-source"><span>{{ $headline['source_name'] }}</span><time>{{ $headline['published_at'] ? $headline['published_at']->diffForHumans() : 'Time unavailable' }}</time></div>
                <h2>{{ $headline['title'] }}</h2>
                @if($headline['excerpt'])
                    <p>{{ $headline['excerpt'] }}</p>
                @endif
                <span class="source-link">Open original article ↗</span>
            </a>
        @empty
            <div class="panel empty-state full-span" data-live-news-empty><h2>Live headlines are refreshing.</h2><p>Please check again shortly.</p></div>
        @endforelse
    </div>
</section>

<section class="container editorial-section">
    <div class="section-heading-row"><div><span class="news-kicker-v1512">ABS EDITORIAL</span><h2>Easy Market Context</h2><p>Original market-awareness articles published by Alpha Block Solutions.</p></div></div>
    <div class="content-grid editorial-grid">
        @forelse($articles as $article)
            <a class="content-card article-card" href="{{ route('news.show',$article->slug) }}"><span class="card-visual">{{ $article->category==='Blockchain'?'⬡':'↗' }}</span><small>{{ $article->category }} · {{ optional($article->published_at)->format('d M Y') }}</small><h2>{{ $article->title }}</h2><p>{{ $article->excerpt }}</p><span>Read ABS article →</span></a>
        @empty
            <div class="panel empty-state full-span"><h2>No ABS article is published right now.</h2><p>New Alpha Block Solutions market updates will appear here when available.</p></div>
        @endforelse
    </div>
    <div class="pagination-wrap">{{ $articles->links() }}</div>
</section>
@endsection
