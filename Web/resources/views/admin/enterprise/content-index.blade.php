@extends('admin.layout')
@section('title',$definition['title'].' — ABS Admin')
@section('heading',$definition['title'])
@section('description',$definition['description'].' Review publishing state, classification, freshness and featured placement from one editorial register.')
@section('content')
@php
    $isEvents = $type === 'events';
    $primaryLabel = $isEvents ? 'Upcoming events' : ($type === 'products' ? 'Live' : 'Published');
    $secondaryLabel = $isEvents ? 'High impact upcoming' : 'Drafts';
@endphp

<section class="enterprise-command-bar compact admin-report-command">
    <div>
        <span class="admin-report-eyebrow">CONTENT STUDIO · {{ strtoupper($type) }}</span>
        <h2>{{ $definition['title'] }} publishing control</h2>
        <p>{{ $definition['description'] }} Keep draft, live and featured records clear before customer demonstrations or production publishing.</p>
    </div>
    <div class="enterprise-command-actions">
        <a class="button button-primary" href="{{ route('admin.enterprise.content.create',$type) }}">+ Add {{ $isEvents ? 'Event' : 'Content' }}</a>
        <a class="button button-ghost" href="{{ route('home') }}">View Public Website</a>
    </div>
</section>

@if($isEvents)
<section class="enterprise-surface economic-integration-panel">
    <div class="enterprise-section-head">
        <div>
            <h2>Economic calendar source</h2>
            <p>Connect the calendar provider once, then keep upcoming and historical CPI, PPI, FOMC, jobs, GDP and other market-moving releases synchronized automatically.</p>
        </div>
        <span class="admin-status {{ ($economicIntegration['configured'] ?? false) ? 'good' : 'warn' }}">
            {{ ($economicIntegration['configured'] ?? false) ? 'CONNECTED' : 'SETUP REQUIRED' }}
        </span>
    </div>
    <div class="economic-integration-grid">
        <form method="POST" action="{{ route('admin.enterprise.economic-calendar.settings') }}" class="economic-integration-form">
            @csrf
            @method('PUT')
            <label>Financial Modeling Prep API key
                <input type="password" name="api_key" autocomplete="new-password" placeholder="{{ ($economicIntegration['configured'] ?? false) ? 'Connected — enter a new key only to replace it' : 'Paste your FMP API key' }}">
            </label>
            <label class="economic-switch"><input type="checkbox" name="auto_sync" value="1" @checked($economicIntegration['auto_sync'] ?? true)> <span>Keep calendar synchronized automatically</span></label>
            <label class="economic-switch"><input type="checkbox" name="clear_api_key" value="1"> <span>Disconnect current API key</span></label>
            <button class="button button-ghost">Save Calendar Settings</button>
        </form>
        <div class="economic-integration-actions">
            <div><small>Provider</small><b>{{ $economicIntegration['provider'] ?? 'Financial Modeling Prep' }}</b></div>
            <div><small>Last successful sync</small><b>{{ !empty($economicIntegration['last_sync_at']) ? \Illuminate\Support\Carbon::parse($economicIntegration['last_sync_at'])->format('d M Y · H:i') : 'Not synced yet' }}</b></div>
            <form method="POST" action="{{ route('admin.enterprise.economic-calendar.sync') }}">
                @csrf
                <button class="button button-primary" @disabled(!($economicIntegration['configured'] ?? false))>Sync Calendar Now</button>
            </form>
            <a class="button button-ghost" href="{{ route('news.index') }}" target="_blank" rel="noopener">Preview ABS News ↗</a>
        </div>
    </div>
    <p class="economic-integration-note">Forecasts are estimates and actual values can be revised. ABS presents simplified crypto context for information only.</p>
</section>
@endif

<section class="admin-report-kpis cms-kpis">
    <article><span class="report-kpi-icon blue">Σ</span><div><small>Total records</small><strong>{{ number_format($summary['total']) }}</strong><em>Complete CMS inventory</em></div></article>
    <article><span class="report-kpi-icon green">✓</span><div><small>{{ $primaryLabel }}</small><strong>{{ number_format($summary['primary']) }}</strong><em>{{ $isEvents ? 'Scheduled from today' : 'Available to customers' }}</em></div></article>
    <article class="{{ $summary['secondary'] > 0 ? 'attention' : '' }}"><span class="report-kpi-icon amber">◷</span><div><small>{{ $secondaryLabel }}</small><strong>{{ number_format($summary['secondary']) }}</strong><em>{{ $isEvents ? 'Requires monitoring' : 'Awaiting editorial decision' }}</em></div></article>
    @if($summary['featured'] !== null)
        <article><span class="report-kpi-icon gold">★</span><div><small>Featured</small><strong>{{ number_format($summary['featured']) }}</strong><em>Priority presentation</em></div></article>
    @endif
    <article><span class="report-kpi-icon violet">↻</span><div><small>Updated · 30 days</small><strong>{{ number_format($summary['updated30']) }}</strong><em>Recently maintained</em></div></article>
</section>

<section class="enterprise-surface enterprise-filter-surface">
    <div class="filter-surface-head"><div><h2>Editorial filters</h2><p>Find content by title, category, asset, country or classification.</p></div></div>
    <form method="GET" class="enterprise-filter-grid cms-filter-grid">
        <label>Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Title, category or asset"></label>
        <label>{{ $isEvents ? 'Impact level' : 'Publication status' }}
            <select name="status">
                <option value="">All {{ $isEvents ? 'impact levels' : 'statuses' }}</option>
                @foreach($filterOptions as $option)
                    <option value="{{ $option }}" @selected(request('status')===$option)>{{ ucwords(str_replace('_',' ',$option)) }}</option>
                @endforeach
            </select>
        </label>
        @if(!$isEvents)
            <label>Placement<select name="featured"><option value="">All content</option><option value="1" @selected(request('featured')==='1')>Featured only</option></select></label>
        @endif
        <div class="filter-actions"><button class="button button-primary">Apply Filters</button><a class="button button-ghost" href="{{ route('admin.enterprise.content.index',$type) }}">Clear</a></div>
    </form>
</section>

<section class="enterprise-surface no-pad">
    <div class="enterprise-section-head padded">
        <div><h2>Editorial register</h2><p>{{ number_format($items->total()) }} matching records · ordered for operational review</p></div>
        <span class="report-period-chip">{{ strtoupper($type) }} CMS</span>
    </div>
    <div class="enterprise-table-wrap">
        <table class="enterprise-table cms-record-table">
            <thead><tr><th>Record</th><th>{{ $isEvents ? 'Impact / Market' : 'Status / Classification' }}</th><th>{{ $isEvents ? 'Scheduled' : 'Publishing' }}</th><th>Last maintained</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                @php
                    $status = $item->status ?? ($item->impact ?? 'active');
                    $stateClass = in_array($status,['published','live','low'],true) ? 'good' : (in_array($status,['high','archived'],true) ? 'danger' : 'warn');
                @endphp
                <tr>
                    <td>
                        <b>{{ $item->title ?? $item->name }}</b>
                        <small>#{{ $item->id }} · {{ $item->slug ?? ($item->country ?? 'ABS record') }}</small>
                    </td>
                    <td>
                        <span class="admin-status {{ $stateClass }}">{{ ucwords(str_replace('_',' ',$status)) }}</span>
                        <small>
                            @if(!empty($item->category))
                                {{ $item->category }}
                            @elseif(!empty($item->currency))
                                {{ $item->country }} · {{ $item->currency }}
                            @else
                                Operational record
                            @endif
                        </small>
                    </td>
                    <td>
                        @if($isEvents)
                            <b>{{ optional($item->event_at)->format('d M Y · H:i') ?? 'Not scheduled' }}</b>
                            <small>
                                @if($item->event_at)
                                    {{ $item->event_at->isFuture() ? $item->event_at->diffForHumans() : 'Past event' }}
                                @else
                                    Scheduling required
                                @endif
                            </small>
                        @else
                            <b>{{ optional($item->published_at)->format('d M Y · H:i') ?? ($status==='live' ? 'Live product' : 'Not published') }}</b>
                            <small>{{ ($item->is_featured ?? false) ? 'Featured placement' : 'Standard placement' }}</small>
                        @endif
                    </td>
                    <td><b>{{ optional($item->updated_at)->format('d M Y · H:i') }}</b><small>{{ optional($item->updated_at)->diffForHumans() }}</small></td>
                    <td>
                        <div class="admin-row-actions">
                            <a class="enterprise-row-action" href="{{ route('admin.enterprise.content.edit',[$type,$item->id]) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.enterprise.content.destroy',[$type,$item->id]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="danger-action" onclick="return confirm('Delete this record?')">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="admin-empty-report"><span>◇</span><h3>No matching content</h3><p>Create the first record or clear the current editorial filters.</p><a class="button button-primary" href="{{ route('admin.enterprise.content.create',$type) }}">Add New</a></div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="enterprise-pagination">{{ $items->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
</section>
@endsection
