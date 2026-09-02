@extends('pulse.layout')
@section('title','Pulse Alerts & Watchlists')
@section('heading','Alerts & Watchlists')
@section('content')
@php
    $unreadCount = $alerts->getCollection()->where('is_read', false)->count();
    $formatPrice = fn ($value) => is_numeric($value) ? number_format((float)$value, (float)$value >= 1 ? 2 : 4) : 'Unavailable';
@endphp
<div class="pulse-page-hero">
    <div><span>ACCOUNT MONITORING</span><h1>Alerts &amp; Watchlists</h1><p>Review account-specific signal, trade, risk and system notices alongside your saved market list.</p></div>
    <form method="POST" action="{{ route('pulse.alerts.read-all') }}">@csrf @method('PATCH')<button class="pulse-button secondary">Mark All Alerts Read</button></form>
</div>

<div class="pulse-grid pulse-grid-two alert-watch-grid">
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><div><h2>Market Watchlist</h2><p>Cached public-market values are shown only when a provider snapshot is available.</p></div><small>Updated: {{ $marketUpdatedAt ? \Carbon\Carbon::parse($marketUpdatedAt)->diffForHumans() : 'not available' }}</small></div>
        <div class="watchlist-table">
            <div class="head"><span>Pair</span><span>Price</span><span>24h Change</span><span></span></div>
            <?php $__absForelseEmpty1 = true; foreach ($watchlist as $row): $__absForelseEmpty1 = false; ?>
                <div class="row"><b>{{ $row['pair'] }}</b><span>{{ $formatPrice($row['price']) }}</span><em class="{{ is_numeric($row['change_percent']) ? ((float)$row['change_percent']>=0?'positive':'negative') : '' }}">{{ is_numeric($row['change_percent']) ? (((float)$row['change_percent']>=0?'+':'').number_format((float)$row['change_percent'],2).'%') : 'Unavailable' }}</em><span><?php if ($row['is_stored']): ?><form method="POST" action="{{ route('watchlist.destroy',$row['symbol']) }}">@csrf @method('DELETE')<button class="icon-action" aria-label="Remove {{ $row['pair'] }} from watchlist">×</button></form><?php else: ?><small>Selected market</small><?php endif; ?></span></div>
            <?php endforeach; if ($__absForelseEmpty1): ?><div class="pulse-card-empty">No watchlist or selected Pulse market is available.</div><?php endif; ?>
        </div>
        <form method="POST" action="{{ route('watchlist.store') }}" class="watchlist-add-form">@csrf<div class="pulse-field"><label for="watch-symbol">Add Binance symbol</label><input id="watch-symbol" name="symbol" placeholder="BTCUSDT" maxlength="30" required></div><div class="pulse-field"><label for="watch-label">Display name (optional)</label><input id="watch-label" name="display_name" placeholder="Bitcoin / USDT" maxlength="100"></div><button class="pulse-button secondary">Add to Watchlist</button></form>
    </article>
    <article class="pulse-work-card">
        <div class="pulse-card-heading"><h2>Notification Preferences</h2><a href="{{ route('pulse.settings.edit') }}">Manage</a></div>
        <div class="notification-preference-list">
            <?php foreach (['signals'=>'Qualified signal alerts','trades'=>'Trade and order updates','risk'=>'Risk and protection failures','system'=>'Pulse system notices'] as $key=>$label): ?>
                <div><span><b>{{ $label }}</b><small>{{ data_get($settings->notification_preferences,$key,true) ? 'Enabled in Pulse settings' : 'Disabled in Pulse settings' }}</small></span><em class="{{ data_get($settings->notification_preferences,$key,true) ? 'positive' : '' }}">{{ data_get($settings->notification_preferences,$key,true) ? 'On' : 'Off' }}</em></div>
            <?php endforeach; ?>
        </div>
        <div class="pulse-warning"><b>Delivery scope</b><span>This page records in-platform alerts. Email delivery also depends on installation-wide communication settings and successful mail configuration.</span></div>
    </article>
</div>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Alert Inbox</h2><p>Alerts link to the relevant account record when a destination was stored.</p></div><small>{{ $alerts->total() }} total · {{ $unreadCount }} unread on this page</small></div>
    <div class="alert-inbox">
        <?php $__absForelseEmpty2 = true; foreach ($alerts as $alert): $__absForelseEmpty2 = false; ?>
            <section class="{{ $alert->is_read ? 'read' : 'unread' }} severity-{{ $alert->severity }}">
                <i></i>
                <div class="alert-inbox-content"><span><b>{{ $alert->title }}</b><small>{{ strtoupper($alert->type) }} · {{ $alert->created_at->format('d M Y H:i T') }}</small></span><p>{{ $alert->message }}</p></div>
                <span class="pulse-badge status-{{ $alert->severity==='danger'?'failed':($alert->severity==='warning'?'pending':'active') }}">{{ strtoupper($alert->severity) }}</span>
                <div class="pulse-actions"><?php if ($alert->action_url): ?><a class="review-link" href="{{ $alert->action_url }}">Open</a><?php endif; ?> <?php if (! ($alert->is_read)): ?><form method="POST" action="{{ route('pulse.alerts.read',$alert) }}">@csrf @method('PATCH')<button class="icon-action" aria-label="Mark alert read">✓</button></form><?php endif; ?></div>
            </section>
        <?php endforeach; if ($__absForelseEmpty2): ?><div class="pulse-card-empty">There are no Pulse alerts for this account.</div><?php endif; ?>
    </div>
    {{ $alerts->links() }}
</article>
<p class="pulse-accuracy-note">Watchlist prices are cached public-market data and can be delayed or unavailable. Exchange positions, balances and executable prices must be confirmed directly with Binance.</p>
@endsection
