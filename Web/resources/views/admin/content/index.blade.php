@extends('admin.layout')
@section('title',$definition['title'].' — ABS Admin')
@section('heading',$definition['title'])
@section('content')
<section class="admin-page-intro panel">
    <div>
        <span class="admin-kicker">{{ $type === 'news' ? 'EDITORIAL CONTROL' : 'CONTENT MANAGEMENT' }}</span>
        <h2>{{ $definition['title'] }}</h2>
        <p>{{ $definition['description'] }}</p>
    </div>
    <a class="button button-primary" href="{{ route('admin.content.create',$type) }}">{{ $definition['create_label'] }}</a>
</section>

@if($type === 'news')
    <section class="panel admin-table-wrap admin-news-table">
        <div class="table-head news-admin-head"><span>Article</span><span>Publication</span><span>Homepage</span><span>Updated</span><span>Actions</span></div>
        @forelse($items as $item)
            <div class="table-row news-admin-row">
                <span class="news-admin-title"><b>{{ $item->title }}</b><small>{{ $item->category }} · {{ $item->source_name ?: 'ABS Editorial' }}</small></span>
                <span><span class="status-pill status-{{ $item->status }}">{{ ucfirst($item->status) }}</span><small>{{ $item->published_at?->format('d M Y, H:i') ?: 'Not published' }}</small></span>
                <span>
                    @if($item->is_featured)<span class="status-pill status-featured">Latest headline</span>@else<span class="status-pill">Standard article</span>@endif
                    <form method="POST" action="{{ route('admin.news.headline',$item) }}">@csrf @method('PATCH')<button class="admin-inline-action">{{ $item->is_featured ? 'Remove from homepage' : 'Add to homepage' }}</button></form>
                </span>
                <span>{{ $item->updated_at?->format('d M Y H:i') }}</span>
                <span class="admin-row-actions">
                    <a href="{{ route('admin.content.edit',[$type,$item->id]) }}">Edit</a>
                    @if($item->status !== 'published')
                        <form method="POST" action="{{ route('admin.news.publish',$item) }}">@csrf @method('PATCH')<button>Publish now</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.news.unpublish',$item) }}">@csrf @method('PATCH')<button>Return to draft</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.content.destroy',[$type,$item->id]) }}">@csrf @method('DELETE')<button class="danger-action" onclick="return confirm('Delete this article?')">Delete</button></form>
                </span>
            </div>
        @empty
            <div class="empty-state"><h2>No ABS article has been created yet.</h2><p>Create a verified market update, source-linked brief or original ABS article. External publisher feeds remain separate from CMS content.</p><a class="button button-primary" href="{{ route('admin.content.create','news') }}">Publish the First Headline</a></div>
        @endforelse
        {{ $items->links() }}
    </section>
@else
    <section class="panel admin-table-wrap">
        <div class="market-table">
            <div class="table-head"><span>ID</span><span>{{ $definition['singular'] }}</span><span>Status or classification</span><span>Updated</span><span>Actions</span></div>
            @forelse($items as $item)
                <div class="table-row">
                    <span>#{{ $item->id }}</span>
                    <span><b>{{ $item->title ?? $item->name }}</b><small>{{ $item->slug ?? ($item->country ?? '') }}</small></span>
                    <span>{{ ucwords(str_replace('_',' ',$item->status ?? ($item->impact ?? 'Active'))) }}@if($item->category ?? null) · {{ $item->category }}@elseif($item->currency ?? null) · {{ $item->currency }}@endif</span>
                    <span>{{ $item->updated_at?->format('d M Y H:i') }}</span>
                    <span class="admin-row-actions"><a href="{{ route('admin.content.edit',[$type,$item->id]) }}">Edit</a><form method="POST" action="{{ route('admin.content.destroy',[$type,$item->id]) }}">@csrf @method('DELETE')<button class="danger-action" onclick="return confirm('Delete this record?')">Delete</button></form></span>
                </div>
            @empty
                <div class="empty-state"><h2>No {{ strtolower($definition['singular']) }} has been created.</h2><p>{{ $definition['description'] }}</p><a class="button button-primary" href="{{ route('admin.content.create',$type) }}">{{ $definition['create_label'] }}</a></div>
            @endforelse
        </div>
        {{ $items->links() }}
    </section>
@endif
@endsection
