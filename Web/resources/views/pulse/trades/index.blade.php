@extends('pulse.layout')
@section('title','Pulse Trade History')
@section('heading','Trade History')
@section('content')
<div class="pulse-page-hero">
    <div><span>AUDITABLE ACTIVITY</span><h1>Trade History</h1><p>Review every local Pulse order and trade record, its exchange identifiers, synchronization state, protection status, commission asset and realized result.</p></div>
    <div class="pulse-actions"><form method="POST" action="{{ route('pulse.trades.sync') }}">@csrf<button class="pulse-button">Sync with Binance</button></form><a class="pulse-button secondary" href="{{ route('pulse.positions') }}">Open Positions</a></div>
</div>

<div class="pulse-metric-row four">
    <article><span class="metric-icon">▥</span><div><small>TOTAL RECORDS</small><strong>{{ $totalCount }}</strong></div></article>
    <article><span class="metric-icon">▣</span><div><small>OPEN / IN-FLIGHT</small><strong>{{ $openCount }}</strong></div></article>
    <article><span class="metric-icon">✓</span><div><small>CLOSED</small><strong>{{ $closedCount }}</strong></div></article>
    <article><span class="metric-icon">!</span><div><small>PROTECTION REVIEWS</small><strong class="{{ $protectionIssues>0?'negative':'' }}">{{ $protectionIssues }}</strong></div></article>
</div>

<article class="pulse-work-card">
    <form method="GET" class="pulse-filter-grid trade-filters">
        <div class="pulse-field"><label for="trade-symbol">Symbol</label><input id="trade-symbol" name="symbol" value="{{ request('symbol') }}" placeholder="BTCUSDT"></div>
        <div class="pulse-field"><label for="trade-status">Status</label><select id="trade-status" name="status"><option value="">All statuses</option><?php foreach (['submitting','pending','open','protection_failed','closing','closed','failed','cancelled'] as $status): ?><option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_',' ',ucfirst($status)) }}</option><?php endforeach; ?></select></div>
        <div class="pulse-field"><label for="trade-environment">Environment</label><select id="trade-environment" name="environment"><option value="">Both</option><option value="testnet" @selected(request('environment')==='testnet')>Practice / Testnet</option><option value="live" @selected(request('environment')==='live')>Live</option></select></div>
        <div class="pulse-field"><label for="trade-from">Created from</label><input id="trade-from" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="pulse-field"><label for="trade-to">Created to</label><input id="trade-to" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="pulse-filter-actions"><button class="pulse-button secondary">Apply Filters</button><a class="pulse-button ghost" href="{{ route('pulse.trades.index') }}">Reset</a></div>
    </form>
</article>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Pulse Trade Records</h2><p>Local status is refreshed through synchronization. Binance remains authoritative for order, position and fill state.</p></div><small>{{ $trades->total() }} matching</small></div>
    <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Created</th><th>Pair</th><th>Side</th><th>Environment</th><th>Order</th><th>Quantity</th><th>Entry</th><th>Status</th><th>Protection</th><th>Realized P&amp;L</th><th>Commission</th><th>Last Sync</th><th></th></tr></thead><tbody>
    <?php $__absForelseEmpty1 = true; foreach ($trades as $trade): $__absForelseEmpty1 = false; ?>
        <tr><td>{{ $trade->created_at?->format('d M Y H:i') }}</td><td><b>{{ str_replace('USDT','/USDT',$trade->symbol) }}</b></td><td>{{ $trade->side }}</td><td class="environment-{{ $trade->environment }}">{{ $trade->environment === 'live' ? 'LIVE' : 'PRACTICE' }}</td><td>{{ $trade->order_type }}<small>{{ $trade->leverage }}×</small></td><td>{{ $trade->quantity }}</td><td>{{ $trade->entry_price ?: '—' }}</td><td><span class="pulse-badge status-{{ $trade->status }}">{{ strtoupper(str_replace('_',' ',$trade->status)) }}</span></td><td>{{ strtoupper(str_replace('_',' ',$trade->protection_status ?: 'not recorded')) }}</td><td class="{{ (float)$trade->realized_pnl>=0?'positive':'negative' }}">{{ number_format((float)$trade->realized_pnl,4) }}</td><td>{{ number_format((float)$trade->fees,8) }} {{ $trade->commission_asset ?: 'unknown' }}</td><td>{{ $trade->last_synced_at?->diffForHumans() ?? 'Not yet' }}</td><td><a class="review-link" href="{{ route('pulse.trades.show',$trade) }}">Open</a></td></tr>
    <?php endforeach; if ($__absForelseEmpty1): ?><tr><td colspan="13">No trade record matches the selected filters.</td></tr><?php endif; ?>
    </tbody></table></div>{{ $trades->links() }}
</article>
<p class="pulse-accuracy-note">Realized P&amp;L and commissions are populated from synchronized Binance user-trade fills where available. Different commission assets are not converted or combined on this page.</p>
@endsection
