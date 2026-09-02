@extends('admin.layout')
@section('title','Contact & Support Inbox — ABS Admin')
@section('heading','Contact & Support Inbox')
@section('content')
<section class="panel admin-page-intro"><div><span class="admin-kicker">SUPPORT OPERATIONS</span><h2>User enquiries</h2><p>Track account, billing, technical, market-data and security enquiries in one queue.</p></div></section>
<section class="panel admin-table-wrap"><div class="market-table">
<div class="table-head"><span>Reference</span><span>Sender</span><span>Category</span><span>Status</span><span>Received</span></div>
@forelse($items as $item)<a class="table-row" href="{{ route('admin.enterprise.contacts.show',$item) }}">
<span>#{{ $item->id }}</span><span><b>{{ $item->subject }}</b><small>{{ $item->name }} · {{ $item->email }}</small></span><span>{{ ucfirst($item->category) }} · {{ ucfirst($item->priority) }}</span><span>{{ ucfirst($item->status) }}</span><span>{{ $item->created_at?->format('d M Y H:i') }}</span>
</a>@empty<div class="empty-state"><h2>No support messages.</h2></div>@endforelse
</div>{{ $items->links() }}</section>
@endsection
