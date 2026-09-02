@extends('admin.layout')
@section('title',$definition['title'].' — ABS Admin')
@section('heading',$definition['title'])
@section('content')
<section class="panel admin-page-intro">
    <div><span class="admin-kicker">ENTERPRISE CMS</span><h2>{{ $definition['title'] }}</h2><p>{{ $definition['description'] }}</p></div>
    <a class="button button-primary" href="{{ route('admin.enterprise.content.create',$type) }}">Add New</a>
</section>
<section class="panel admin-table-wrap">
    <div class="market-table">
        <div class="table-head"><span>ID</span><span>Record</span><span>Status / Classification</span><span>Updated</span><span>Actions</span></div>
        @forelse($items as $item)
            <div class="table-row">
                <span>#{{ $item->id }}</span>
                <span><b>{{ $item->title ?? $item->name }}</b><small>{{ $item->slug ?? ($item->country ?? '') }}</small></span>
                <span>
                    {{ ucwords(str_replace('_',' ',$item->status ?? ($item->impact ?? 'Active'))) }}
                    @if($item->category ?? null) · {{ $item->category }} @elseif($item->currency ?? null) · {{ $item->currency }} @endif
                </span>
                <span>{{ $item->updated_at?->format('d M Y H:i') }}</span>
                <span class="admin-row-actions">
                    <a href="{{ route('admin.enterprise.content.edit',[$type,$item->id]) }}">Edit</a>
                    <form method="POST" action="{{ route('admin.enterprise.content.destroy',[$type,$item->id]) }}">@csrf @method('DELETE')<button class="danger-action" onclick="return confirm('Delete this record?')">Delete</button></form>
                </span>
            </div>
        @empty
            <div class="empty-state"><h2>No records yet.</h2><p>{{ $definition['description'] }}</p></div>
        @endforelse
    </div>
    {{ $items->links() }}
</section>
@endsection
