@extends('layouts.app')
@section('title','ABS News & Economic Calendar — Alpha Block Solutions')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/abs-news-v1512.css') }}?v={{ @filemtime(public_path('assets/css/abs-news-v1512.css')) ?: '15.1.2' }}">
@endpush
@section('content')
<section class="container news-hero-v1512">
    <span class="news-kicker-v1512">ABS NEWS · MARKET & MACRO INTELLIGENCE</span>
    <h1>Know what can move the market.<br><span>Understand why it matters.</span></h1>
    <p>Track major economic releases, digital-asset headlines and market context in one focused intelligence view.</p>
    <div class="news-hero-pills"><span>{{ $macroTimezone }} time</span><span>Previous · Forecast · Actual</span><span>Pulse market context</span></div>
</section>

<section class="container macro-v1512" id="economic-calendar">
    <div class="macro-v1512-head">
        <div><span class="news-kicker-v1512">ECONOMIC CALENDAR</span><h2>Market-moving events</h2><p>CPI, PPI, PCE, FOMC, employment, GDP, retail sales, PMI and other releases that can influence crypto risk sentiment.</p></div>
        <div class="macro-v1512-legend"><span class="high">High</span><span class="medium">Medium</span><span class="low">Low</span></div>
    </div>

    <nav class="macro-v1512-tabs" aria-label="Economic calendar period">
        <a class="{{ $macroMode==='today' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'today', 'impact'=>$macroImpact]) }}"><span>Today</span><b>{{ number_format($macroCounts['today']) }}</b></a>
        <a class="{{ $macroMode==='upcoming' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'upcoming', 'impact'=>$macroImpact]) }}"><span>Upcoming</span><b>{{ number_format($macroCounts['upcoming']) }}</b></a>
        <a class="{{ $macroMode==='history' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'history', 'impact'=>$macroImpact]) }}"><span>Previous Releases</span><b>{{ number_format($macroCounts['history']) }}</b></a>
        <a class="{{ $macroMode==='all' ? 'active' : '' }}" href="{{ route('news.index',['calendar'=>'all', 'impact'=>$macroImpact]) }}"><span>All</span><b>{{ number_format($macroCounts['all'] ?? 0) }}</b></a>
    </nav>

    <div class="macro-impact-filter" role="group" aria-label="Filter economic calendar by event impact">
        <span>Filter calendar by impact</span>
        <div class="macro-impact-options">
            @foreach(['all'=>'All impacts','high'=>'High','medium'=>'Medium','low'=>'Low'] as $impactKey => $impactLabel)
                <a href="{{ route('news.index', ['calendar'=>$macroMode,'impact'=>$impactKey]) }}#economic-calendar" aria-current="{{ $macroImpact === $impactKey ? 'true' : 'false' }}">{{ $impactLabel }}</a>
            @endforeach
        </div>
        <small>{{ $macroImpact === 'all' ? 'Showing all relevant impacts' : 'Showing '.ucfirst($macroImpact).' impact events' }}</small>
    </div>

    <div class="macro-status-v1561">
        <span class="{{ ($calendarLastSyncStatus ?? null) === 'success' ? 'good' : 'pending' }}">{{ ($calendarLastSyncStatus ?? null) === 'success' ? 'Calendar active' : 'Calendar refresh pending' }}</span>
        <small>@if(!empty($calendarLastSyncAt)) Updated {{ \Illuminate\Support\Carbon::parse($calendarLastSyncAt)->diffForHumans() }} @else Waiting for first successful refresh @endif</small>
    </div>

    <div class="macro-v1512-list">
        @forelse($macroEvents as $event)
            @php
                $released = filled($event->actual_value);
                $impactClass = strtolower($event->impact ?: 'medium');
                $cryptoClass = strtolower($event->crypto_impact ?: 'mixed');
            @endphp
            <article id="macro-event-{{ $event->id }}" class="macro-v1512-event {{ $impactClass }}" data-macro-event-id="{{ $event->id }}" data-macro-event-at="{{ $event->event_at?->toIso8601String() }}">
                <div class="macro-v1512-time"><b>{{ $event->event_at?->format('H:i') ?: '—' }}</b><span>{{ $event->event_at?->format('d M') ?: 'Unscheduled' }}</span></div>
                <div class="macro-v1512-event-main">
                    <div class="macro-v1512-title"><span class="macro-impact {{ $impactClass }}">{{ strtoupper($event->impact ?: 'MEDIUM') }}</span><small>{{ $event->country ?: 'Global' }}{{ $event->currency ? ' · '.$event->currency : '' }}</small><h3>{{ $event->title }}</h3></div>
                    <div class="macro-v1512-values"><span><small>Previous</small><b data-macro-value="previous">{{ $event->previous_value ?: 'Not available' }}</b></span><span><small>Forecast</small><b data-macro-value="forecast">{{ $event->forecast_value ?: 'Not published' }}</b></span><span class="actual"><small>Actual</small><b data-macro-value="actual">{{ $event->actual_value ?: ($event->event_at?->isFuture() ? 'Awaiting release' : 'Awaiting result') }}</b></span></div>
                </div>
                <div class="macro-v1512-context {{ $cryptoClass }}">
                    <span data-macro-state>{{ $released ? 'Released' : ($event->event_at?->isFuture() ? 'Upcoming' : 'Awaiting actual') }} · {{ ucfirst($event->crypto_impact ?: 'mixed') }} crypto context</span>
                    @if($event->easy_explanation)
                        <p><b>In simple words:</b> {{ $event->easy_explanation }}</p>
                    @endif
                    @if($event->crypto_impact_summary)
                        <p><b>Possible crypto effect:</b> <span data-macro-impact-analysis>{{ $event->crypto_impact_summary }}</span></p>
                    @endif
                    <div>
                        @if($event->source)
                            <span>Source: {{ $event->source }}</span>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="macro-v1512-empty">
                <span>◷</span><h3>{{ $macroMode==='history' ? 'No previous releases are stored yet.' : ($macroImpact !== 'all' ? 'No '.strtolower($macroImpact).' impact events in this period.' : 'No events are available in this period yet.') }}</h3>
                <p>{{ $calendarConfigured ? 'Calendar data is refreshing. Try another period or check again shortly.' : 'Economic calendar data is temporarily unavailable.' }}</p>
            </div>
        @endforelse
    </div>

    <div class="macro-v1512-note"><strong>Market data notice:</strong> Forecasts are estimates and actual readings can be revised. Crypto context is educational market interpretation, not a prediction or trading instruction. <a href="{{ route('legal.disclaimer') }}">Read disclaimer</a>.</div>
</section>

<section class="container live-news-section news-live-v1512">
    <div class="section-heading-row"><div><span class="news-kicker-v1512">MARKET HEADLINES</span><h2>Digital Asset News</h2><p>Current market developments with focused Pulse context, risk read and assets in focus.</p></div><button class="button button-ghost button-small" type="button" data-refresh-live-news>Refresh Headlines</button></div>
    <div class="live-headline-grid" data-live-news-list data-live-news-mode="page" data-live-news-limit="12">
        @forelse($liveHeadlines as $headline)
            <a class="panel live-headline-card" data-live-news-item data-external="1" href="{{ $headline['abs_url'] ?? route('news.live.show',['id'=>$headline['id']]) }}">
                <div class="headline-source"><span>{{ $headline['source_name'] }}</span><time>{{ $headline['published_at'] ? $headline['published_at']->diffForHumans() : 'Time unavailable' }}</time></div>
                <h2>{{ $headline['title'] }}</h2>
                @if($headline['excerpt'])
                    <p>{{ $headline['excerpt'] }}</p>
                @endif
                <span class="source-link">Open Market Brief →</span>
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

@push('scripts')
<script>
// Poll released values while the calendar remains open; the scheduler performs
// authoritative provider updates. This endpoint reads the same database as mobile.
(() => {
    const cards = document.querySelectorAll('[data-macro-event-id]');
    if (!cards.length) return;
    let active = false;
    async function refreshMacro() {
        if (document.hidden || active) return;
        active = true;
        try {
            const start = new Date(), end = new Date();
            start.setDate(start.getDate() - 31); end.setDate(end.getDate() + 46);
            const url = new URL(@json(url('/api/v1/economic-calendar')), location.origin);
            url.searchParams.set('from', start.toISOString().slice(0,10));
            url.searchParams.set('to', end.toISOString().slice(0,10));
            const response = await fetch(url.toString(), {cache: 'no-store'});
            if (!response.ok) return;
            const payload = await response.json();
            const events = new Map((payload.data || []).map(event => [String(event.id), event]));
            cards.forEach(card => {
                const event = events.get(card.dataset.macroEventId);
                if (!event) return;
                for (const [key, field, fallback] of [
                    ['previous','previous_value','Not available'],
                    ['forecast','forecast_value','Not published'],
                    ['actual','actual_value','Awaiting result']
                ]) {
                    const target = card.querySelector('[data-macro-value="' + key + '"]');
                    if (!target) continue;
                    let value = event[field] || fallback;
                    if (key === 'actual' && !event[field]) {
                        value = new Date(card.dataset.macroEventAt).getTime() > Date.now()
                            ? 'Awaiting release' : 'Awaiting result';
                    }
                    if (target.textContent !== value) target.textContent = value;
                }
                const state = card.querySelector('[data-macro-state]');
                if (state) state.textContent = (event.actual_value ? 'Released' :
                    (new Date(card.dataset.macroEventAt).getTime() > Date.now() ? 'Upcoming' : 'Awaiting actual'))
                    + ' · ' + ((event.crypto_impact || 'mixed').replace(/^./, c => c.toUpperCase())) + ' crypto context';
                const analysis = card.querySelector('[data-macro-impact-analysis]');
                if (analysis && event.crypto_impact_summary) analysis.textContent = event.crypto_impact_summary;
            });
        } catch (_) { /* A temporary network failure must not hide existing calendar values. */ }
        finally { active = false; }
    }
    setInterval(refreshMacro, 60000);
    window.addEventListener('focus', refreshMacro);
})();
</script>
@endpush
