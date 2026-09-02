@extends('admin.layout')
@section('title','Support #'.$contact->id.' — ABS Admin')
@section('heading','Support Enquiry #'.$contact->id)
@section('content')
<section class="panel admin-page-intro"><div><span class="admin-kicker">{{ strtoupper($contact->category) }}</span><h2>{{ $contact->subject }}</h2><p>{{ $contact->name }} · {{ $contact->email }} · {{ $contact->created_at?->format('d M Y H:i') }}</p></div><a class="button button-ghost" href="{{ route('admin.enterprise.contacts') }}">Back</a></section>
<section class="panel admin-form"><h2>Message</h2><p style="white-space:pre-wrap">{{ $contact->message }}</p></section>
<form method="POST" action="{{ route('admin.enterprise.contacts.update',$contact) }}" class="panel admin-form">@csrf @method('PUT')<div class="admin-form-grid">
<label>Status<select name="status">@foreach(['new','open','waiting','resolved','closed'] as $status)<option value="{{ $status }}" @selected($contact->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
<label>Priority<select name="priority">@foreach(['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}" @selected($contact->priority===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
<label class="full">Internal notes<textarea name="admin_notes" rows="7">{{ $contact->admin_notes }}</textarea></label></div><div class="admin-page-actions"><button class="button button-primary">Save Support Record</button></div></form>
@endsection
