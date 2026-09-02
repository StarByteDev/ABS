@extends('pulse.layout')
@section('title',$signal->symbol.' Pulse Signal')
@section('heading',$signal->symbol.' Signal Review')
@section('content')
@php
    $pulseAccessService = app(\App\Services\PulseAccessService::class);
    $canManualTrade = $pulseAccessService->allows(auth()->user(), 'manual_trading', false);
    $canSettings = $pulseAccessService->allows(auth()->user(), 'settings', false);
    $canPractice = $pulseAccessService->systemEnabled('testnet_trading_enabled', true) && $pulseAccessService->allows(auth()->user(), 'testnet_trading', false);
    $canLive = config('pulse.allow_live_trading', false) && $pulseAccessService->systemEnabled('live_trading_enabled', false) && $pulseAccessService->allows(auth()->user(), 'live_trading', false);
@endphp
<div class="pulse-grid pulse-grid-hero">
    <article class="pulse-panel">
        <div class="pulse-panel-head"><div><span class="pulse-badge direction-{{ strtolower($signal->direction) }}">{{ $signal->direction }}</span><h2 style="margin-top:8px">{{ $signal->symbol }} · {{ $signal->timeframe }}</h2><p>Generated {{ $signal->generated_at?->format('d M Y H:i:s') }} · expires {{ $signal->expires_at?->format('d M Y H:i:s') ?? 'not set' }}.</p></div><div class="signal-score" style="--score:{{ $signal->score }}"><span>{{ number_format($signal->score,1) }}</span></div></div>
        <div class="pulse-stat-grid"><div class="pulse-stat"><small>ENTRY</small><strong>{{ $signal->entry_price }}</strong></div><div class="pulse-stat"><small>STOP LOSS</small><strong>{{ $signal->stop_loss }}</strong></div><div class="pulse-stat"><small>TAKE PROFIT</small><strong>{{ $signal->take_profit }}</strong></div><div class="pulse-stat"><small>STATUS</small><strong class="status-{{ $signal->status }}">{{ strtoupper($signal->status) }}</strong></div></div>
        <div class="pulse-panel-head"><div><h2>Strategy score breakdown</h2></div></div>
        <div class="strategy-list"><?php $__absForelseEmpty1 = true; foreach ($signal->strategy_breakdown ?? [] as $strategy): $__absForelseEmpty1 = false; ?><div class="strategy-item"><b>{{ $strategy['name'] ?? 'Strategy' }}</b><span class="direction-{{ strtolower($strategy['bias'] ?? 'neutral') }}">{{ $strategy['bias'] ?? 'NEUTRAL' }}</span><strong>{{ number_format((float)($strategy['points'] ?? 0),1) }}</strong><p>{{ $strategy['reason'] ?? '' }}</p></div><?php endforeach; if ($__absForelseEmpty1): ?><div class="content-empty compact-empty"><b>Strategy details are unavailable.</b><span>Review the signal price levels and current market conditions before taking any action.</span></div><?php endif; ?></div>
    </article>
    <aside class="pulse-panel">
        <h2>Signal Monitoring</h2>
        <div class="pulse-warning"><b>Professional signal lifecycle</b><span>Pulse tracks the setup from live Binance price as Entry Ready, Move in Progress, Entry Watch, Trade Open or Signal Closed.</span></div>
        <div class="pulse-stat-grid" style="grid-template-columns:1fr 1fr">
            <div class="pulse-stat"><small>SIGNAL STATUS</small><strong class="status-{{ $signal->status }}">{{ strtoupper($signal->status) }}</strong></div>
            <div class="pulse-stat"><small>DIRECTION</small><strong>{{ $signal->direction }}</strong></div>
        </div>
        <p style="color:#8ea5af;line-height:1.6">Trade execution is kept on the main Pulse Signals screen. Any still-valid signal can open the <b style="color:#d9c6c0">Open Trade</b> panel; Pulse then recommends the appropriate Binance LIMIT approach from the current price stage and warns against chasing or duplicate entries.</p>
        <div class="pulse-actions" style="flex-direction:column">
            <a class="pulse-button" style="width:100%" href="{{ route('pulse.signals.index', ['selected' => $signal->id]) }}">Return to Signal Monitor</a>
            <a class="pulse-button secondary" style="width:100%" href="{{ route('pulse.signals.index') }}">All Signals</a>
        </div>
        <div class="pulse-warning"><b>Execution discipline</b><span>Use the live price stage and the recommended Binance execution shown in Open Trade. Pulse keeps the original Entry, SL and TP as the protected signal plan and revalidates risk before submission.</span></div>
    </aside>
</div>
@endsection
