@extends('layouts.app')
@section('title',$article->title.' — ABS News')
@section('content')
<article class="container reading-layout">
    <header>
        <h1>{{ $article->title }}</h1>
        <p>{{ $article->excerpt }}</p>
        <div class="article-meta">
            {{ $article->category }} · {{ $article->author_name ?: 'ABS Editorial' }}
            @if($article->published_at) · {{ $article->published_at->format('d M Y, H:i T') }} @endif
            @if($article->source_name) · Source: {{ $article->source_name }} @endif
        </div>
        @if($article->source_url)
            <a class="button button-ghost button-small source-article-link" href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer">Open original source ↗</a>
        @endif
    </header>
    <div class="reading-card panel">
        @if(trim((string) $article->body) !== '')
            {!! $article->body !!}
        @else
            <p>{{ $article->excerpt }}</p>
            @if($article->source_url)<p>This headline links to the original publisher for the complete report.</p>@endif
        @endif
        <div class="disclaimer">This content is for general market awareness and education. It is not personalized financial advice.</div>
    </div>
</article>
@endsection
