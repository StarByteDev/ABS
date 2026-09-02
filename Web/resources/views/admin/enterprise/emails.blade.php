@extends('admin.layout')
@section('title','Email Communications — ABS Admin')
@section('heading','Email Communications')
@section('content')
<section class="panel admin-page-intro"><div><span class="admin-kicker">DELIVERY CONTROL CENTER</span><h2>Production email health and controls</h2><p>Transactional, Pulse, signal, expiry, promotion and Daily Market Brief messages are routed through the ABS branded delivery service and logged here.</p></div></section>
<section class="admin-kpi-grid"><article class="panel"><small>SENT / 24H</small><strong>{{ number_format($stats['sent_24h']) }}</strong></article><article class="panel"><small>FAILED / 24H</small><strong>{{ number_format($stats['failed_24h']) }}</strong></article><article class="panel"><small>ACTIVE MOBILE DEVICES</small><strong>{{ number_format($stats['devices']) }}</strong></article></section>
<form method="POST" action="{{ route('admin.enterprise.emails.settings') }}" class="panel admin-form">@csrf @method('PUT')
<h2>Administrator event notifications</h2>
<p>Receive an internal ABS email whenever a new user registers or a customer submits a Pulse package subscription from the website or mobile application.</p>
<div class="admin-form-grid">
<label>Notification recipient<small>Default: i@armansabir.com. You can change this at any time.</small><input type="email" name="admin_notification_email" value="{{ old('admin_notification_email',$adminEventSettings['email']) }}" required></label>
<label>New user registration alerts<select name="admin_notify_new_registration"><option value="1" @selected($adminEventSettings['new_registration'])>Enabled</option><option value="0" @selected(!$adminEventSettings['new_registration'])>Disabled</option></select></label>
<label>New package subscription alerts<select name="admin_notify_new_subscription"><option value="1" @selected($adminEventSettings['new_subscription'])>Enabled</option><option value="0" @selected(!$adminEventSettings['new_subscription'])>Disabled</option></select></label>
</div>
<hr>
<h2>Customer & operational delivery switches</h2><div class="admin-form-grid">
@foreach($settings as $key=>$enabled)<label>{{ ucwords(str_replace('_',' ',$key)) }}<select name="{{ $key }}"><option value="1" @selected($enabled)>Enabled</option><option value="0" @selected(!$enabled)>Disabled</option></select></label>@endforeach
</div><div class="admin-page-actions"><button class="button button-primary">Save Email Controls</button></div></form>
<form method="POST" action="{{ route('admin.enterprise.emails.test') }}" class="panel admin-form">@csrf<h2>Send branded delivery test</h2><div class="admin-form-grid"><label>Email address<input type="email" name="email" value="{{ auth()->user()->email }}" required></label></div><div class="admin-page-actions"><button class="button button-primary">Send Test Email</button></div></form>
<section class="panel admin-table-wrap"><h2>Recent delivery history</h2><div class="market-table"><div class="table-head"><span>Time</span><span>Event</span><span>Recipient</span><span>Status</span><span>Subject / Error</span></div>
@forelse($logs as $log)<div class="table-row"><span>{{ $log->created_at?->format('d M H:i') }}</span><span>{{ str_replace('_',' ',$log->event) }}</span><span>{{ $log->recipient_email }}</span><span>{{ strtoupper($log->status) }}</span><span><b>{{ $log->subject }}</b>@if($log->error_message)<small>{{ \Illuminate\Support\Str::limit($log->error_message,140) }}</small>@endif</span></div>@empty<div class="empty-state"><h2>No delivery records yet.</h2></div>@endforelse</div>{{ $logs->links() }}</section>
@endsection
