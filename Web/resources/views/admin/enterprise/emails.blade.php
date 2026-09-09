@extends('admin.layout')
@section('title','Alerts & Emails — ABS Admin')
@section('heading','Alerts & Emails')
@section('description','Manage customer emails, administrator alerts and delivery health from one simple communications center.')
@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/admin-communications-v1512.css') }}?v={{ @filemtime(public_path('assets/css/admin-communications-v1512.css')) ?: '15.1.2' }}">
@endpush
@section('content')
@php
    $emailTypes = [
        'transactional_emails_enabled' => ['Account & payment emails','Registration, verification, package payment and essential account messages.','Essential'],
        'pulse_alert_emails_enabled' => ['Pulse alerts','Important Pulse account and service alerts.','Recommended'],
        'signal_email_alerts_enabled' => ['Signal alerts','Email notifications related to qualifying Pulse signals.','Optional'],
        'trade_email_alerts_enabled' => ['Trade alerts','Trade execution and lifecycle notifications.','Optional'],
        'promotion_emails_enabled' => ['Promotions & updates','Marketing, product and campaign messages.','Optional'],
        'daily_market_brief_enabled' => ['Daily Market Brief','Scheduled market-intelligence summary emails.','Optional'],
    ];
@endphp

<section class="comm-overview">
    <article><span class="comm-icon success">✓</span><div><small>Sent · 24 hours</small><strong>{{ number_format($stats['sent_24h']) }}</strong><em>Successful deliveries</em></div></article>
    <article><span class="comm-icon {{ $stats['failed_24h'] ? 'danger' : 'success' }}">!</span><div><small>Failed · 24 hours</small><strong>{{ number_format($stats['failed_24h']) }}</strong><em>{{ $stats['failed_24h'] ? 'Needs review' : 'No delivery failures' }}</em></div></article>
    <article><span class="comm-icon gold">◷</span><div><small>Expiry reminders · 30 days</small><strong>{{ number_format($expirySent30) }}</strong><em>Customer reminders sent</em></div></article>
    <article><span class="comm-icon blue">▣</span><div><small>Mobile devices</small><strong>{{ number_format($stats['devices']) }}</strong><em>Push-capable devices</em></div></article>
</section>

<form method="POST" action="{{ route('admin.enterprise.emails.settings') }}" class="comm-settings-form">
    @csrf
    @method('PUT')

    <section class="comm-card comm-primary-card">
        <div class="comm-card-head">
            <div><span>01</span><div><h2>Plan expiry reminders</h2><p>Automatically remind customers before their active Pulse package expires.</p></div></div>
            <label class="comm-switch"><input type="checkbox" name="expiry_emails_enabled" value="1" @checked($settings['expiry_emails_enabled'])><i></i><b>{{ $settings['expiry_emails_enabled'] ? 'Enabled' : 'Disabled' }}</b></label>
        </div>
        <div class="comm-expiry-grid">
            <label class="comm-field">Send reminders on these days before expiry
                <input name="expiry_reminder_days" value="{{ old('expiry_reminder_days',$expiryDays->implode(',')) }}" placeholder="7,3,1,0" required>
                <small>Example: <b>7,3,1,0</b> sends at 7 days, 3 days, 1 day and on expiry day.</small>
            </label>
            <div class="comm-audience">
                @foreach($expiryAudience as $day=>$count)
                    <article><small>{{ (int)$day===0 ? 'Expires today' : $day.' days' }}</small><strong>{{ number_format($count) }}</strong><span>customers</span></article>
                @endforeach
            </div>
        </div>
        <div class="comm-help">ABS checks once daily and sends each configured reminder only once to eligible active customers.</div>
    </section>

    <section class="comm-card">
        <div class="comm-card-head"><div><span>02</span><div><h2>Administrator alerts</h2><p>Choose where ABS should notify you when a new customer or package payment needs attention.</p></div></div></div>
        <div class="comm-admin-grid">
            <label class="comm-field">Send admin alerts to
                <input type="email" name="admin_notification_email" value="{{ old('admin_notification_email',$adminEventSettings['email']) }}" required>
            </label>
            <label class="comm-toggle-row"><input type="checkbox" name="admin_notify_new_registration" value="1" @checked($adminEventSettings['new_registration'])><span><b>New registrations</b><small>Email me when a new ABS account is created.</small></span></label>
            <label class="comm-toggle-row"><input type="checkbox" name="admin_notify_new_subscription" value="1" @checked($adminEventSettings['new_subscription'])><span><b>New package payments</b><small>Email me when a user submits a USDT payment for verification.</small></span></label>
        </div>
    </section>

    <section class="comm-card">
        <div class="comm-card-head"><div><span>03</span><div><h2>Customer email categories</h2><p>Turn each message category on or off. Essential account messages should normally remain enabled.</p></div></div></div>
        <div class="comm-category-grid">
            @foreach($emailTypes as $key => [$label,$description,$badge])
                <label class="comm-category {{ $settings[$key] ? 'is-on' : '' }}">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked($settings[$key])>
                    <span class="comm-category-mark">{{ $settings[$key] ? '✓' : '○' }}</span>
                    <span><b>{{ $label }}</b><small>{{ $description }}</small></span>
                    <em>{{ $badge }}</em>
                </label>
            @endforeach
        </div>
    </section>

    <div class="comm-savebar"><div><b>Communication settings</b><span>Changes apply immediately after saving.</span></div><button class="button button-primary">Save Email Settings</button></div>
</form>

<section class="comm-card comm-test-card">
    <div class="comm-card-head"><div><span>04</span><div><h2>Test email delivery</h2><p>Send one branded ABS test email before relying on production notifications.</p></div></div></div>
    <form method="POST" action="{{ route('admin.enterprise.emails.test') }}" class="comm-test-form">@csrf
        <label class="comm-field">Destination email<input type="email" name="email" value="{{ auth()->user()->email }}" required></label>
        <button class="button button-ghost">Send Test Email</button>
    </form>
</section>

<section class="comm-card comm-history-card">
    <div class="comm-card-head"><div><span>05</span><div><h2>Recent delivery history</h2><p>Use this only when you need to verify whether an email was sent or why it failed.</p></div></div><span class="report-period-chip">LATEST 40</span></div>
    <div class="enterprise-table-wrap">
        <table class="enterprise-table comm-history-table">
            <thead><tr><th>Time</th><th>Message</th><th>Recipient</th><th>Status</th><th>Details</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d M · H:i') }}</td>
                    <td>{{ ucwords(str_replace('_',' ',$log->event)) }}</td>
                    <td>{{ $log->recipient_email }}</td>
                    <td><span class="admin-status {{ $log->status==='sent'?'good':($log->status==='failed'?'danger':'muted') }}">{{ strtoupper($log->status) }}</span></td>
                    <td>
                        <b>{{ $log->subject }}</b>
                        @if($log->error_message)
                            <small>{{ \Illuminate\Support\Str::limit($log->error_message,140) }}</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="admin-empty-report"><h3>No email delivery records yet</h3><p>Send a test email to verify your configured mail transport.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="enterprise-pagination">{{ $logs->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
</section>
@endsection
