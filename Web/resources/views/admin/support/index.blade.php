@extends('admin.layout')
@section('title','Pulse Support Inbox · ABS Admin')
@section('heading','Pulse Support Inbox')
@section('description','Live member conversations, assistant hand-offs and unresolved service requests in one queue.')

@section('page-actions')
<form method="POST" action="{{ route('admin.support.presence') }}" class="support-presence-form">@csrf @method('PUT')
    <select name="presence" onchange="this.form.submit()">
        <option value="online" @selected($presence==='online')>Online</option>
        <option value="away" @selected($presence==='away')>Away</option>
        <option value="offline" @selected($presence==='offline')>Offline</option>
    </select>
</form>
@endsection

@section('content')
<div class="admin-support-page">
    <section class="admin-support-kpis">
        <article><small>Unread</small><strong>{{ number_format($summary['unread']) }}</strong><span>Member messages</span></article>
        <article><small>Needs reply</small><strong>{{ number_format($summary['waiting_support']) }}</strong><span>Waiting for ABS</span></article>
        <article><small>Member response</small><strong>{{ number_format($summary['waiting_customer']) }}</strong><span>Waiting for customer</span></article>
        <article><small>Open</small><strong>{{ number_format($summary['open']) }}</strong><span>Active conversations</span></article>
    </section>

    <section class="admin-support-panel">
        <form class="admin-support-filters" method="GET">
            <input name="q" value="{{ request('q') }}" placeholder="Search member, email or topic">
            <select name="status"><option value="">All statuses</option>@foreach(['waiting_support'=>'Needs reply','waiting_customer'=>'Waiting customer','resolved'=>'Resolved','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
            <select name="category"><option value="">All topics</option>@foreach($categories as $value=>$label)<option value="{{ $value }}" @selected(request('category')===$value)>{{ $label }}</option>@endforeach</select>
            <button>Apply</button><a href="{{ route('admin.support.index') }}">Clear</a>
        </form>

        <div class="admin-support-list">
            @forelse($items as $item)
                <a href="{{ route('admin.support.show',$item) }}" class="admin-support-row {{ $item->status==='waiting_support' ? 'needs-reply' : '' }}">
                    <div class="admin-support-avatar">{{ strtoupper(substr($item->name,0,1)) }}</div>
                    <div class="admin-support-primary"><b>{{ $item->name }}</b><span>{{ $item->email }}</span></div>
                    <div><small>TOPIC</small><b>{{ $categories[$item->category] ?? $item->subject }}</b></div>
                    <div><small>STATUS</small><span class="support-status is-{{ $item->status }}">{{ $item->status==='waiting_support'?'Needs reply':ucwords(str_replace('_',' ',$item->status)) }}</span></div>
                    <div><small>LAST ACTIVITY</small><b>{{ $item->last_message_at?->diffForHumans() ?? '—' }}</b></div>
                    <span class="admin-support-open">Open →</span>
                </a>
            @empty
                <div class="admin-support-empty"><span>✓</span><h3>Support queue clear</h3><p>No conversations match the selected filters.</p></div>
            @endforelse
        </div>
        <div class="enterprise-pagination">{{ $items->links() }}</div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function(){
 const token=document.querySelector('meta[name="csrf-token"]')?.content;
 async function heartbeat(){try{await fetch(@json(route('admin.support.heartbeat')),{method:'POST',headers:{'X-CSRF-TOKEN':token||'','Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});}catch(e){}}
 setInterval(heartbeat,60000);
})();
</script>
@endpush
