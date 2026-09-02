@extends('layouts.app')
@section('title',$document['title'].' — Alpha Block Solutions')
@section('content')
<section class="container premium-legal-shell">
    <header class="premium-legal-head">
        <span>ALPHA BLOCK SOLUTIONS · LEGAL</span>
        <h1>{{ $document['title'] }}</h1>
        <p>{{ $document['summary'] }}</p>
        <small>Effective {{ $document['effective'] }}</small>
    </header>

    <div class="premium-legal-grid">
        <aside class="premium-legal-nav panel">
            <b>SECTIONS</b>
            <nav>
                @foreach($document['sections'] as $index => $section)
                    @php($sectionId = 'section-'.($index+1).'-'.\Illuminate\Support\Str::slug($section[0]))
                    <a href="#{{ $sectionId }}"><span>{{ $index+1 }}.</span>{{ $section[0] }}</a>
                @endforeach
            </nav>
            <div class="premium-legal-doc-links">
                <a class="{{ $type==='terms'?'active':'' }}" href="{{ route('legal.terms') }}">Terms</a>
                <a class="{{ $type==='privacy'?'active':'' }}" href="{{ route('legal.privacy') }}">Privacy</a>
                <a class="{{ $type==='risk'?'active':'' }}" href="{{ route('legal.risk') }}">Risk</a>
                <a class="{{ $type==='disclaimer'?'active':'' }}" href="{{ route('legal.disclaimer') }}">Market Disclaimer</a>
            </div>
        </aside>

        <article class="premium-legal-document panel">
            @foreach($document['sections'] as $index => $section)
                @php($sectionId = 'section-'.($index+1).'-'.\Illuminate\Support\Str::slug($section[0]))
                <section id="{{ $sectionId }}">
                    <span class="legal-section-number">{{ str_pad((string)($index+1),2,'0',STR_PAD_LEFT) }}</span>
                    <div><h2>{{ $section[0] }}</h2>{!! $section[1] !!}</div>
                </section>
            @endforeach
        </article>
    </div>
</section>
@endsection
