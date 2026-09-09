@extends('admin.layout')
@section('title',$definition['title'].' — ABS Admin')
@section('heading',$definition['title'])
@section('description','Publish verified market coverage, control homepage placement and monitor the complete editorial pipeline.')
@section('content')
<section class="enterprise-command-bar compact admin-report-command">
    <div>
        <span class="admin-report-eyebrow">CONTENT STUDIO · MARKET NEWS</span>
        <h2>Verified publishing and homepage control</h2>
        <p>{{ $definition['description'] }} Track draft readiness, live coverage, featured placement and publishing freshness.</p>
    </div>
    <div class="enterprise-command-actions"><a class="button button-primary" href="{{ route('admin.content.create',$type) }}">{{ $definition['create_label'] }}</a>@if($type==='news')<a class="button button-ghost" href="{{ route('admin.enterprise.content.index','events') }}">Economic Calendar CMS</a>@endif<a class="button button-ghost" href="{{ route('news.index') }}" target="_blank" rel="noopener">Preview ABS News</a></div>
</section>

@if($type === 'news')
    <section class="admin-report-kpis cms-kpis">
        <article><span class="report-kpi-icon blue">Σ</span><div><small>Total articles</small><strong>{{ number_format($summary['total']) }}</strong><em>Complete editorial inventory</em></div></article>
        <article><span class="report-kpi-icon green">✓</span><div><small>Published</small><strong>{{ number_format($summary['published']) }}</strong><em>Available to readers</em></div></article>
        <article class="{{ $summary['draft'] > 0 ? 'attention' : '' }}"><span class="report-kpi-icon amber">◷</span><div><small>Drafts</small><strong>{{ number_format($summary['draft']) }}</strong><em>Awaiting editorial decision</em></div></article>
        <article><span class="report-kpi-icon gold">★</span><div><small>Homepage headlines</small><strong>{{ number_format($summary['featured']) }}</strong><em>Featured placement</em></div></article>
        <article><span class="report-kpi-icon violet">↻</span><div><small>Updated · 30 days</small><strong>{{ number_format($summary['updated30']) }}</strong><em>Recently maintained</em></div></article>
    </section>
    <section class="enterprise-surface enterprise-filter-surface"><div class="filter-surface-head"><div><h2>Editorial filters</h2><p>Search by headline, category or source and isolate publication states.</p></div></div><form method="GET" class="enterprise-filter-grid cms-filter-grid"><label>Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Headline, category or source"></label><label>Publication status<select name="status"><option value="">All statuses</option>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label><label>Homepage placement<select name="featured"><option value="">All articles</option><option value="1" @selected(request('featured')==='1')>Featured only</option></select></label><div class="filter-actions"><button class="button button-primary">Apply Filters</button><a class="button button-ghost" href="{{ route('admin.content.index','news') }}">Clear</a></div></form></section>
    <section class="enterprise-surface no-pad admin-news-table">
        <div class="enterprise-section-head padded"><div><h2>Market news register</h2><p>{{ number_format($items->total()) }} matching articles · featured and recently published first</p></div><span class="report-period-chip">NEWS CMS</span></div>
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
        <div class="enterprise-pagination">{{ $items->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
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
        {{ $items->onEachSide(1)->links('vendor.pagination.abs-admin') }}
    </section>
@endif
@endsection
