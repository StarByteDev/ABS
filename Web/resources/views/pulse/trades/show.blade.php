@extends('pulse.layout')
@section('title',$trade->symbol.' Trade')
@section('heading',$trade->symbol.' Trade Record')
@section('content')
@php
    $pulseAccessService = app(\App\Services\PulseAccessService::class);
    $canManualTrade = $pulseAccessService->allows(auth()->user(), 'manual_trading', false);
    $canOrders = $pulseAccessService->allows(auth()->user(), 'orders', false);
    $commissionBreakdown = data_get($trade->meta, 'commission_breakdown', []);
@endphp
<div class="pulse-grid pulse-grid-hero">
    <article class="pulse-panel">
        <div class="pulse-panel-head"><div><span class="pulse-badge status-{{ $trade->status }}">{{ strtoupper(str_replace('_',' ',$trade->status)) }}</span><h2 style="margin-top:8px">{{ $trade->side }} {{ $trade->symbol }}</h2><p>{{ $trade->environment === 'live' ? 'LIVE' : 'PRACTICE' }} · {{ $trade->order_type }} · {{ $trade->leverage }}× · {{ $trade->exchange_position_side }}</p></div><div><small>REALIZED P&amp;L</small><h2 class="{{ $trade->realized_pnl>=0?'positive':'negative' }}">{{ number_format($trade->realized_pnl,4) }} USDT</h2><small>UNREALIZED: <span class="{{ $trade->unrealized_pnl>=0?'positive':'negative' }}">{{ number_format($trade->unrealized_pnl,4) }}</span></small></div></div>
        <div class="pulse-stat-grid"><div class="pulse-stat"><small>QUANTITY</small><strong>{{ $trade->quantity }}</strong></div><div class="pulse-stat"><small>ENTRY</small><strong>{{ $trade->entry_price ?: '—' }}</strong></div><div class="pulse-stat"><small>CURRENT</small><strong>{{ $trade->current_price ?: '—' }}</strong></div><div class="pulse-stat"><small>COMMISSION</small><strong>{{ $trade->commission_asset === 'MIXED' ? 'See breakdown' : number_format($trade->fees,8).' '.($trade->commission_asset ?: 'unknown') }}</strong></div><div class="pulse-stat"><small>STOP</small><strong>{{ $trade->stop_loss ?: '—' }}</strong></div><div class="pulse-stat"><small>TARGET</small><strong>{{ $trade->take_profit ?: '—' }}</strong></div><div class="pulse-stat"><small>PROTECTION</small><strong class="status-{{ $trade->protection_status }}">{{ strtoupper(str_replace('_',' ',$trade->protection_status ?? '—')) }}</strong></div><div class="pulse-stat"><small>LAST SYNC</small><strong style="font-size:13px">{{ $trade->last_synced_at?->diffForHumans() ?? 'Never' }}</strong></div></div>
        <?php if ($commissionBreakdown !== []): ?><div class="pulse-warning"><b>Commission breakdown</b><span>{{ collect($commissionBreakdown)->map(fn ($amount, $asset) => $asset.' '.number_format((float)$amount,8))->implode(' · ') }}</span></div><?php endif; ?>
        <div class="pulse-panel-head"><div><h2>Binance references</h2></div></div>
        <div class="pulse-form-grid"><div class="pulse-field"><label>Entry order</label><input readonly value="{{ $trade->exchange_order_id ?: 'Not recorded' }}"></div><div class="pulse-field"><label>Close order</label><input readonly value="{{ $trade->exchange_close_order_id ?: 'Not recorded' }}"></div><div class="pulse-field"><label>Take-profit algo</label><input readonly value="{{ $trade->exchange_tp_order_id ?: 'Not confirmed' }}"></div><div class="pulse-field"><label>Stop-loss algo</label><input readonly value="{{ $trade->exchange_sl_order_id ?: 'Not confirmed' }}"></div></div>
        <?php if ($trade->close_reason): ?><div class="pulse-warning"><b>Close or error reason</b><span>{{ str_replace('_',' ',$trade->close_reason) }}</span></div><?php endif; ?>
    </article>
    <aside class="pulse-panel">
        <h2>Trade controls</h2>
        <div class="pulse-actions" style="flex-direction:column"><form method="POST" action="{{ route('pulse.trades.sync') }}">@csrf<button class="pulse-button" style="width:100%">Sync Orders & Position</button></form><?php if ($canManualTrade && in_array($trade->status,['pending','open','closing','protection_failed'])): ?><form method="POST" action="{{ route('pulse.trades.close',$trade) }}">@csrf<button class="pulse-button danger" style="width:100%" data-confirm="Send a cancellation or reduce-only close request to Binance?">Cancel / Close on Binance</button></form><?php endif; ?><?php if ($canOrders): ?><a class="pulse-button secondary" href="{{ route('pulse.positions') }}">Open Exchange Snapshot</a><?php endif; ?></div><?php if (! ($canManualTrade)): ?><div class="pulse-warning"><b>Manual execution is restricted</b><span>This plan allows trade-history review but does not allow new manual close requests.</span></div><?php endif; ?>
        <?php if ($trade->protection_status==='confirmed'): ?><div class="pulse-warning" style="border-color:rgba(32,229,139,.3);background:rgba(32,229,139,.06)"><b class="positive">Protection IDs recorded</b><span>Use Sync to keep checking conditional order status.</span></div><?php else: ?><div class="pulse-danger-zone"><b>Protection needs review</b><span>Binance remains the final source of truth. Review the exchange immediately when protection is not confirmed.</span></div><?php endif; ?>
        <p class="pulse-legal-note">Submitting an order is not the same as receiving a fill. A close request is not final until the exchange position is zero and the fill is synchronized.</p>
    </aside>
</div>
@endsection
