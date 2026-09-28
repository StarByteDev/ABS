@extends('admin.layout')
@section('title',$account->user->name.' — Requests')
@section('heading',$account->user->name)
@section('description','Review this investor’s additional investment, withdrawal and portfolio review requests.')
@section('page-actions')<a class="pi-btn" href="{{ route('admin.private-investors.show',$account) }}">Account Summary</a>@endsection
@section('content')
<section class="pi-shell pi-admin-investor-v1569">
    @include('admin.private-investors._account-tabs')
    <div class="pi-note"><strong>Simple workflow:</strong> review the request, confirm funds or payout, record the matching transaction, then mark the request completed.</div>
    @if($requests->isEmpty())
        <div class="pi-empty">No requests for this investor.</div>
    @else
        @foreach($requests as $r)
            <article class="pi-card">
                <div class="pi-card-head"><div><h2>{{ ucwords(str_replace('_',' ',$r->type)) }}</h2><span>{{ $r->created_at->format('d M Y · H:i') }}</span></div><span class="pi-status {{ $r->status }}">{{ str_replace('_',' ',$r->status) }}</span></div>
                <div class="pi-summary-band"><div><small>Amount</small><strong>{{ $r->amount ? $r->currency.' '.number_format((float)$r->amount,2) : 'Not applicable' }}</strong></div><div><small>Status</small><strong>{{ ucwords(str_replace('_',' ',$r->status)) }}</strong></div><div><small>Last update</small><strong>{{ $r->updated_at->format('d M Y · H:i') }}</strong></div></div>
                @if($r->message)<div class="pi-note">{{ $r->message }}</div>@endif
                <form method="POST" action="{{ route('admin.private-investors.requests.update',$r) }}" class="pi-form">
                    @csrf @method('PUT')
                    <div class="pi-form-row"><label>Status<select name="status">@foreach(['submitted','under_review','approved','declined','completed'] as $v)<option value="{{ $v }}" @selected($r->status===$v)>{{ ucwords(str_replace('_',' ',$v)) }}</option>@endforeach</select></label><label>Admin note<input name="admin_note" value="{{ $r->admin_note }}" placeholder="Visible to investor"></label></div>
                    <button class="pi-btn primary">Update Request</button>
                </form>
            </article>
        @endforeach
        {{ $requests->links() }}
    @endif
</section>
@endsection
