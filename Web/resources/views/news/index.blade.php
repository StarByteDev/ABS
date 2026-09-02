@extends('layouts.app')
@section('title','Market News — Alpha Block Solutions')
@section('content')
<section class="page-hero container">
    <h1>Current headlines with <span class="gradient-text">clear sources.</span></h1>
    <p>Read recent industry headlines from their original publishers and verified Alpha Block Solutions editorial content from the CMS.</p>
</section>

<section class="container live-news-section">
    <div class="section-heading-row">
        <div><h2>Live Industry Headlines</h2><p>Titles link directly to the original publisher. ABS does not republish the full third-party article.</p></div>
        <button class="button button-ghost button-small" type="button" data-refresh-live-news>Refresh Headlines</button>
    </div>
    <div class="live-headline-grid" data-live-news-list data-live-news-mode="page" data-live-news-limit="12">
        @forelse($liveHeadlines as $headline)
            <a class="panel live-headline-card" data-live-news-item data-external="1" href="{{ $headline['url'] }}" target="_blank" rel="noopener noreferrer">
                <div class="headline-source"><span>{{ $headline['source_name'] }}</span><time>{{ $headline['published_at'] ? $headline['published_at']->diffForHumans() : 'Time unavailable' }}</time></div>
                <h2>{{ $headline['title'] }}</h2>
                @if($headline['excerpt'])<p>{{ $headline['excerpt'] }}</p>@endif
                <span class="source-link">Open original article ↗</span>
            </a>
        @empty
            <div class="panel empty-state full-span" data-live-news-empty><h2>Loading live industry headlines…</h2><p>Please check again shortly. The news service refreshes automatically when publisher feeds are reachable.</p></div>
        @endforelse
    </div>
</section>

<section class="container editorial-section">
    <div class="section-heading-row"><div><h2>ABS Editorial</h2><p>Original market-awareness articles published by Alpha Block Solutions.</p></div></div>
    <div class="content-grid editorial-grid">
        @forelse($articles as $article)
            <a class="content-card article-card" href="{{ route('news.show',$article->slug) }}">
                <span class="card-visual">{{ $article->category==='Blockchain'?'⬡':'↗' }}</span>
                <small>{{ $article->category }} · {{ optional($article->published_at)->format('d M Y') }}</small>
                <h2>{{ $article->title }}</h2>
                <p>{{ $article->excerpt }}</p>
                <span>Read ABS article →</span>
            </a>
        @empty
            <div class="panel empty-state full-span"><h2>No ABS article is published right now.</h2><p>New Alpha Block Solutions market updates will appear here when available.</p></div>
        @endforelse
    </div>
    <div class="pagination-wrap">{{ $articles->links() }}</div>
</section>
@endsection
