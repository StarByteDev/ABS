@extends('admin.layout')
@section('title','Newsletter Subscribers — ABS Admin')
@section('heading','Newsletter Subscribers')
@section('content')
<section class="panel admin-page-intro"><div><span class="admin-kicker">COMMUNICATIONS</span><h2>Market-update subscribers</h2><p>Review subscription status and preferences without exposing application secrets.</p></div></section>
<section class="panel admin-table-wrap"><div class="market-table">
<div class="table-head"><span>Email</span><span>Status</span><span>Preferences</span><span>Confirmed</span><span>Action</span></div>
@forelse($items as $item)<div class="table-row">
<span><b>{{ $item->email }}</b></span><span>{{ ucfirst($item->status) }}</span><span><small>{{ implode(', ', collect($item->preferences ?: [])->mapWithKeys(fn($v,$k)=>[is_int($k)?$v:$k=>is_int($k)?true:$v])->filter()->keys()->map(fn($v)=>ucwords(str_replace('_',' ',$v)))->all()) ?: 'Default' }}</small></span><span>{{ $item->confirmed_at?->format('d M Y H:i') ?: '—' }}</span>
<span><form method="POST" action="{{ route('admin.enterprise.newsletters.update',$item) }}">@csrf @method('PUT')<select name="status">@foreach(['active','unsubscribed'] as $status)<option value="{{ $status }}" @selected($item->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select><button>Save</button></form></span>
</div>@empty<div class="empty-state"><h2>No newsletter subscribers yet.</h2></div>@endforelse
</div>{{ $items->links() }}</section>
@endsection
