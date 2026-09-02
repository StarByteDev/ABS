@extends('pulse.layout')
@section('title','Pulse Open Positions')
@section('heading','Open Positions')
@section('content')
@php($snapshotEnvironment = $snapshot['environment'] ?? $environment)
<div class="pulse-page-hero">
    <div><span>EXCHANGE SNAPSHOT</span><h1>Open Positions</h1><p>Review the latest account, position, normal-order and conditional-order data returned by the connected Binance USD‑M Futures account.</p></div>
    <form method="GET" class="environment-switch"><label for="position-environment">Environment</label><select id="position-environment" name="environment"><option value="testnet" @selected($environment==='testnet')>Practice / Testnet</option><option value="live" @selected($environment==='live')>Live</option></select><button class="pulse-button secondary">Refresh Snapshot</button></form>
</div>

<?php if ($error): ?>
<article class="pulse-work-card"><div class="content-empty"><b>Exchange snapshot unavailable</b><span>{{ $error }}</span><div class="pulse-actions"><a class="pulse-button secondary" href="{{ route('pulse.binance.index') }}">Review Binance Connection</a><a class="pulse-button secondary" href="{{ route('pulse.trades.index') }}">Open Local Trade History</a></div></div></article>
<?php else: ?>
<div class="pulse-metric-row four">
    <article><span class="metric-icon">◈</span><div><small>WALLET BALANCE</small><strong>{{ number_format((float)data_get($snapshot,'account.total_wallet_balance',0),4) }}</strong><em>USD equivalent*</em></div></article>
    <article><span class="metric-icon">◫</span><div><small>AVAILABLE BALANCE</small><strong>{{ number_format((float)data_get($snapshot,'account.available_balance',0),4) }}</strong><em>USD equivalent*</em></div></article>
    <article><span class="metric-icon">▣</span><div><small>OPEN POSITIONS</small><strong>{{ count($snapshot['positions'] ?? []) }}</strong></div></article>
    <article><span class="metric-icon">◇</span><div><small>UNREALIZED P&amp;L</small><strong class="{{ (float)data_get($snapshot,'account.total_unrealized_profit',0)>=0?'positive':'negative' }}">{{ number_format((float)data_get($snapshot,'account.total_unrealized_profit',0),4) }}</strong><em>USD equivalent*</em></div></article>
</div>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Current Exchange Positions</h2><p>Non-zero positions returned by Binance Position Information V3.</p></div><span class="pulse-badge environment-{{ $snapshotEnvironment }}">{{ $snapshotEnvironment === 'live' ? 'LIVE' : 'PRACTICE' }}</span></div>
    <div class="position-live-grid">
        <?php $__absForelseEmpty1 = true; foreach ($snapshot['positions'] ?? [] as $position): $__absForelseEmpty1 = false; ?>
            <section>
                <div class="position-live-head"><div><h3>{{ str_replace('USDT','/USDT',$position['symbol'] ?? '—') }}</h3><small>{{ $position['positionSide'] ?? 'BOTH' }} · {{ strtoupper($position['marginType'] ?? 'unknown') }}</small></div><span class="{{ (float)($position['positionAmt']??0)>=0?'positive':'negative' }}">{{ (float)($position['positionAmt']??0)>=0?'LONG':'SHORT' }}</span></div>
                <dl><div><dt>Position amount</dt><dd>{{ $position['positionAmt'] ?? '—' }}</dd></div><div><dt>Entry price</dt><dd>{{ $position['entryPrice'] ?? '—' }}</dd></div><div><dt>Break-even price</dt><dd>{{ $position['breakEvenPrice'] ?? '—' }}</dd></div><div><dt>Mark price</dt><dd>{{ $position['markPrice'] ?? '—' }}</dd></div><div><dt>Liquidation price</dt><dd>{{ $position['liquidationPrice'] ?? '—' }}</dd></div><div><dt>Leverage</dt><dd>{{ $position['leverage'] ?? '—' }}×</dd></div><div><dt>Unrealized P&amp;L</dt><dd class="{{ (float)($position['unRealizedProfit']??0)>=0?'positive':'negative' }}">{{ $position['unRealizedProfit'] ?? '—' }}</dd></div><div><dt>Updated</dt><dd>{{ !empty($position['updateTime']) ? \Carbon\Carbon::createFromTimestampMs((int)$position['updateTime'])->diffForHumans() : 'Not provided' }}</dd></div></dl>
            </section>
        <?php endforeach; if ($__absForelseEmpty1): ?>
            <div class="pulse-card-empty">Binance returned no non-zero position for this account and environment.</div>
        <?php endif; ?>
    </div>
</article>

<div class="pulse-grid pulse-grid-two">
    <article class="pulse-work-card"><div class="pulse-card-heading"><h2>Open Normal Orders</h2><small>{{ count($snapshot['open_orders'] ?? []) }}</small></div><div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Pair</th><th>Side</th><th>Type</th><th>Price</th><th>Quantity</th><th>Status</th></tr></thead><tbody><?php $__absForelseEmpty2 = true; foreach ($snapshot['open_orders'] ?? [] as $order): $__absForelseEmpty2 = false; ?><tr><td>{{ $order['symbol'] ?? '—' }}</td><td>{{ $order['side'] ?? '—' }}</td><td>{{ $order['type'] ?? '—' }}</td><td>{{ $order['price'] ?? '—' }}</td><td>{{ $order['origQty'] ?? '—' }}</td><td>{{ $order['status'] ?? '—' }}</td></tr><?php endforeach; if ($__absForelseEmpty2): ?><tr><td colspan="6">No open normal order was returned by Binance.</td></tr><?php endif; ?></tbody></table></div></article>
    <article class="pulse-work-card"><div class="pulse-card-heading"><h2>Open Conditional Orders</h2><small>{{ count($snapshot['open_algo_orders'] ?? []) }}</small></div><div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Pair</th><th>Side</th><th>Order Type</th><th>Trigger</th><th>Position Side</th><th>Status</th></tr></thead><tbody><?php $__absForelseEmpty3 = true; foreach ($snapshot['open_algo_orders'] ?? [] as $order): $__absForelseEmpty3 = false; ?><tr><td>{{ $order['symbol'] ?? '—' }}</td><td>{{ $order['side'] ?? '—' }}</td><td>{{ $order['orderType'] ?? $order['type'] ?? '—' }}</td><td>{{ $order['triggerPrice'] ?? '—' }}</td><td>{{ $order['positionSide'] ?? 'BOTH' }}</td><td>{{ $order['algoStatus'] ?? $order['status'] ?? '—' }}</td></tr><?php endforeach; if ($__absForelseEmpty3): ?><tr><td colspan="6">No open conditional TP/SL or trailing order was returned by Binance.</td></tr><?php endif; ?></tbody></table></div></article>
</div>
<?php endif; ?>

<article class="pulse-work-card">
    <div class="pulse-card-heading"><div><h2>Pulse Reconciliation Records</h2><p>Local records that Pulse still considers pending, open, closing or in need of protection review.</p></div><form method="POST" action="{{ route('pulse.trades.sync') }}">@csrf<button class="pulse-button secondary">Sync Local Records</button></form></div>
    <div class="pulse-data-table-wrap"><table class="pulse-data-table"><thead><tr><th>Pair</th><th>Side</th><th>Status</th><th>Protection</th><th>Entry</th><th>Current</th><th>Unrealized</th><th>Last Sync</th><th></th></tr></thead><tbody><?php $__absForelseEmpty4 = true; foreach ($localTrades as $trade): $__absForelseEmpty4 = false; ?><tr><td><b>{{ $trade->symbol }}</b></td><td>{{ $trade->side }}</td><td><span class="pulse-badge status-{{ $trade->status }}">{{ strtoupper(str_replace('_',' ',$trade->status)) }}</span></td><td>{{ strtoupper(str_replace('_',' ',$trade->protection_status ?: 'not recorded')) }}</td><td>{{ $trade->entry_price ?: '—' }}</td><td>{{ $trade->current_price ?: '—' }}</td><td class="{{ (float)$trade->unrealized_pnl>=0?'positive':'negative' }}">{{ number_format((float)$trade->unrealized_pnl,4) }}</td><td>{{ $trade->last_synced_at?->diffForHumans() ?? 'Not yet' }}</td><td><a class="review-link" href="{{ route('pulse.trades.show',$trade) }}">Review</a></td></tr><?php endforeach; if ($__absForelseEmpty4): ?><tr><td colspan="9">No local Pulse trade currently requires reconciliation.</td></tr><?php endif; ?></tbody></table></div>
</article>
<p class="pulse-accuracy-note">* Binance describes totals as USDT in single-asset mode and USD-denominated values in multi-assets mode. Binance remains the authoritative source for balances, positions, margin, liquidation price and order status.</p>
@endsection
