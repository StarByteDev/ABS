@extends('pulse.layout')
@section('title','Pulse Help · Alpha Block Solutions')

@section('content')
<div class="pulse-help-page">
    <section class="pulse-help-hero">
        <div>
            <span class="pulse-help-kicker">ABS PULSE HELP</span>
            <h1>How can we help?</h1>
            <p>Ask a question in plain language. Pulse Assistant answers account and product questions immediately and passes anything that needs review to ABS Support.</p>
        </div>
        <div class="pulse-help-status is-{{ $presence }}" data-support-presence>
            <i></i><span>{{ $presenceLabel }}</span>
        </div>
    </section>

    <section class="pulse-help-shell">
        <div class="pulse-help-chat">
            <header class="pulse-help-chat-head">
                <div class="pulse-help-agent">
                    <div class="pulse-help-agent-icon">P</div>
                    <div><strong>Pulse Assistant</strong><span>{{ $conversation?->status === 'waiting_support' ? 'ABS Support has your conversation' : 'Instant account & product help' }}</span></div>
                </div>
                <div class="pulse-help-head-actions">
                    @if($conversation)<span class="pulse-help-ref">Case #{{ $conversation->id }}</span>@endif
                    @if($conversation)
                        <form method="POST" action="{{ route('pulse.support.close',$conversation) }}">@csrf @method('PATCH')<button class="pulse-help-quiet" type="submit">Close</button></form>
                    @endif
                </div>
            </header>

            @php
                $helpSuggestions = [
                    ['Why is my payment still pending?','payment'],
                    ['What is included in my package?','package'],
                    ['How does Free Signal work?','free_signal'],
                    ['Why is the scanner not finding a signal?','scanner'],
                ];
                if (auth()->user()?->isPrivateInvestor()) $helpSuggestions[] = ['I need help with my investor portfolio','portfolio'];
                $helpSuggestions[] = ['Talk to ABS Support','other'];
            @endphp
            <div class="pulse-help-suggestions" aria-label="Popular help questions">
                @foreach($helpSuggestions as [$question,$topic])
                    <button type="button" data-help-question="{{ $question }}" data-help-topic="{{ $topic }}">{{ $question }}</button>
                @endforeach
            </div>

            <div class="pulse-help-thread" data-support-thread @if($conversation) data-url="{{ route('pulse.support.messages',$conversation) }}" @endif>
                @if(!$conversation)
                    <article class="pulse-help-welcome">
                        <div class="pulse-help-botmark">P</div>
                        <div><strong>Pulse Assistant</strong><p>Ask about payments, packages, Free Signal, scanner access, mobile app or account access. If the answer needs a team review, your conversation is transferred automatically.@if(auth()->user()?->isPrivateInvestor()) You can also ask about your investor portfolio and requests.@endif</p></div>
                    </article>
                @endif
                @if($conversation)
                    @foreach($conversation->messages as $message)
                        <article class="pulse-help-message is-{{ $message->sender_type }}" data-message-id="{{ $message->id }}">
                            <div class="pulse-help-message-avatar">{{ $message->sender_type === 'customer' ? strtoupper(substr(auth()->user()->name,0,1)) : ($message->sender_type === 'admin' ? 'A' : 'P') }}</div>
                            <div class="pulse-help-message-body">
                                <div class="pulse-help-message-meta"><strong>{{ $message->sender_type === 'customer' ? 'You' : ($message->sender_type === 'admin' ? 'ABS Support' : ($message->sender_type === 'assistant' ? 'Pulse Assistant' : 'ABS Pulse')) }}</strong><span>{{ $message->created_at?->format('H:i') }}</span></div>
                                <p>{{ $message->body }}</p>
                            </div>
                        </article>
                    @endforeach
                @endif
            </div>

            @if($conversation?->status === 'waiting_support')
                <div class="pulse-help-handoff"><span>✓</span><div><strong>Sent to ABS Support</strong><p>Your conversation is in the support queue. Replies will appear here on web and mobile.</p></div></div>
            @endif

            <form class="pulse-help-compose" method="POST" action="{{ route('pulse.support.message') }}">
                @csrf
                @if($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
                <input type="hidden" name="category" value="{{ $conversation?->category ?? 'other' }}" data-support-category>
                <div class="pulse-help-input-wrap">
                    <textarea name="message" rows="2" required maxlength="5000" placeholder="Ask Pulse Assistant…">{{ old('message') }}</textarea>
                    <button type="submit" aria-label="Send message">➜</button>
                </div>
                <div class="pulse-help-compose-foot"><span>Do not share passwords, recovery keys or exchange API secrets.</span>@if($history->isNotEmpty())<span>{{ $history->count() }} recent support {{ $history->count() === 1 ? 'case' : 'cases' }}</span>@endif</div>
            </form>
        </div>

        <aside class="pulse-help-side">
            <section>
                <small>SUPPORT FLOW</small>
                <div class="pulse-help-flow"><b>1</b><p><strong>Ask Pulse Assistant</strong><span>Instant help for common ABS Pulse questions.</span></p></div>
                <div class="pulse-help-flow"><b>2</b><p><strong>Automatic hand-off</strong><span>Questions needing review move to ABS Support.</span></p></div>
                <div class="pulse-help-flow"><b>3</b><p><strong>Continue anywhere</strong><span>The same conversation follows your account on web and mobile.</span></p></div>
            </section>
            @if($history->isNotEmpty())
            <section>
                <small>RECENT CASES</small>
                <div class="pulse-help-recent">
                    @foreach($history->take(5) as $item)
                        <div><p><strong>#{{ $item->id }} · {{ $item->subject }}</strong><span>{{ $item->last_message_at?->diffForHumans() }}</span></p><em class="is-{{ $item->status }}">{{ $item->status==='waiting_support'?'Needs reply':ucwords(str_replace('_',' ',$item->status)) }}</em></div>
                    @endforeach
                </div>
            </section>
            @endif
        </aside>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const category = document.querySelector('[data-support-category]');
    const textarea = document.querySelector('.pulse-help-compose textarea');
    document.querySelectorAll('[data-help-question]').forEach(btn => btn.addEventListener('click', () => {
        if(category) category.value = btn.dataset.helpTopic || 'other';
        if(textarea){ textarea.value = btn.dataset.helpQuestion || btn.textContent.trim(); textarea.focus(); }
    }));

    const thread = document.querySelector('[data-support-thread][data-url]');
    if(!thread) return;
    let lastId = Math.max(0, ...Array.from(thread.querySelectorAll('[data-message-id]')).map(x => Number(x.dataset.messageId || 0)));
    const labels = {customer:'You',admin:'ABS Support',assistant:'Pulse Assistant',system:'ABS Pulse'};
    const avatar = {customer:@json(strtoupper(substr(auth()->user()->name,0,1))),admin:'A',assistant:'P',system:'P'};
    function addMessage(message){
        if(document.querySelector(`[data-message-id="${message.id}"]`)) return;
        const article=document.createElement('article'); article.className=`pulse-help-message is-${message.sender}`; article.dataset.messageId=message.id;
        const av=document.createElement('div'); av.className='pulse-help-message-avatar'; av.textContent=avatar[message.sender]||'P';
        const body=document.createElement('div'); body.className='pulse-help-message-body';
        const meta=document.createElement('div'); meta.className='pulse-help-message-meta';
        const strong=document.createElement('strong'); strong.textContent=labels[message.sender]||'ABS Pulse';
        const span=document.createElement('span'); span.textContent=new Date(message.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
        const p=document.createElement('p'); p.textContent=message.body;
        meta.append(strong,span); body.append(meta,p); article.append(av,body); thread.append(article); lastId=Math.max(lastId,Number(message.id)); thread.scrollTop=thread.scrollHeight;
    }
    async function poll(){
        try{
            const response=await fetch(thread.dataset.url,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            if(!response.ok)return; const json=await response.json();
            (json.conversation?.messages||[]).filter(m=>Number(m.id)>lastId).forEach(addMessage);
            const presence=document.querySelector('[data-support-presence] span'); if(presence&&json.presence_label)presence.textContent=json.presence_label;
        }catch(e){}
    }
    setInterval(poll,5000); thread.scrollTop=thread.scrollHeight;
})();
</script>
@endpush
