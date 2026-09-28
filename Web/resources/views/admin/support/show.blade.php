@extends('admin.layout')
@section('title','Support #'.$conversation->id.' · ABS Admin')
@section('heading','Support Conversation #'.$conversation->id)
@section('description',$conversation->name.' · '.($categories[$conversation->category] ?? $conversation->subject))

@section('content')
<div class="admin-support-detail">
    <section class="admin-support-conversation">
        <header><div><span class="support-status is-{{ $conversation->status }}">{{ $conversation->status==='waiting_support'?'Needs reply':ucwords(str_replace('_',' ',$conversation->status)) }}</span><h2>{{ $conversation->subject }}</h2></div><small>{{ strtoupper($conversation->channel) }} · {{ $conversation->last_message_at?->format('d M Y H:i') }}</small></header>
        <div class="admin-support-thread" data-admin-support-thread data-url="{{ route('admin.support.messages',$conversation) }}">
            @foreach($conversation->messages as $message)
                <article class="admin-support-message is-{{ $message->sender_type }}" data-message-id="{{ $message->id }}"><div><strong>{{ $message->sender_type==='customer'?$conversation->name:($message->sender_type==='admin'?'ABS Support':($message->sender_type==='assistant'?'Pulse Assistant':'ABS Pulse')) }}</strong><span>{{ $message->created_at?->format('d M · H:i') }}</span></div><p>{{ $message->body }}</p></article>
            @endforeach
        </div>
        <form class="admin-support-reply" method="POST" action="{{ route('admin.support.message',$conversation) }}">@csrf<textarea name="message" rows="3" required maxlength="5000" placeholder="Reply to {{ $conversation->name }}…"></textarea><div><span>Reply continues the same conversation on web and mobile.</span><button>Send reply →</button></div></form>
    </section>

    <aside class="admin-support-context">
        <section><small>MEMBER</small><h3>{{ $conversation->name }}</h3><p>{{ $conversation->email }}</p>@if($conversation->user)<a href="{{ route('admin.users.show',$conversation->user) }}">Open member account →</a>@endif</section>
        <section><small>PULSE ACCESS</small>@php($access=$conversation->user?->pulseAccess)<h3>{{ $access?->plan?->name ?? 'No active package' }}</h3><p>{{ $access?->isActive() ? 'Active'.($access?->ends_at ? ' until '.$access->ends_at->format('d M Y') : '') : 'Not active' }}</p></section>
        <form method="POST" action="{{ route('admin.support.update',$conversation) }}">@csrf @method('PUT')<small>CONVERSATION</small><label>Topic<select name="category">@foreach($categories as $key=>$label)<option value="{{ $key }}" @selected($conversation->category===$key)>{{ $label }}</option>@endforeach</select></label><label>Priority<select name="priority">@foreach(['low','normal','high','urgent'] as $value)<option value="{{ $value }}" @selected($conversation->priority===$value)>{{ ucfirst($value) }}</option>@endforeach</select></label><label>Status<select name="status">@foreach(['waiting_support'=>'Needs reply','waiting_customer'=>'Waiting customer','resolved'=>'Resolved','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected($conversation->status===$value)>{{ $label }}</option>@endforeach</select></label><button>Update conversation</button></form>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(function(){
 const thread=document.querySelector('[data-admin-support-thread]'); if(!thread)return;
 let lastId=Math.max(0,...Array.from(thread.querySelectorAll('[data-message-id]')).map(x=>Number(x.dataset.messageId||0)));
 const customer=@json($conversation->name); const labels={customer:customer,admin:'ABS Support',assistant:'Pulse Assistant',system:'ABS Pulse'};
 function add(m){if(document.querySelector(`[data-message-id="${m.id}"]`))return;const a=document.createElement('article');a.className=`admin-support-message is-${m.sender}`;a.dataset.messageId=m.id;const d=document.createElement('div');const b=document.createElement('strong');b.textContent=labels[m.sender]||'ABS Pulse';const s=document.createElement('span');s.textContent=new Date(m.created_at).toLocaleString();const p=document.createElement('p');p.textContent=m.body;d.append(b,s);a.append(d,p);thread.append(a);lastId=Math.max(lastId,Number(m.id));thread.scrollTop=thread.scrollHeight;}
 async function poll(){try{const r=await fetch(thread.dataset.url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});if(!r.ok)return;const j=await r.json();(j.conversation?.messages||[]).filter(m=>Number(m.id)>lastId).forEach(add);}catch(e){}}
 setInterval(poll,4000);thread.scrollTop=thread.scrollHeight;
})();
</script>
@endpush
