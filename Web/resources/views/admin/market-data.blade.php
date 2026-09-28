@extends('admin.layout')
@section('title','Market Data — ABS Admin')
@section('heading','Pulse Strategy Lab · Market Data')
@section('description','Stage 1 · Confirm that ABS has fresh prices before any strategy scan runs.')
@section('page-actions')
<form method="POST" action="{{ route('admin.market-data.refresh') }}">@csrf<button class="button button-primary" type="submit">Refresh Prices Now</button></form>
@endsection
@section('content')
@include('admin.pulse.partials.strategy-lab-nav')
@php
    $status = strtolower((string)($health['feed_status'] ?? 'offline'));
    $statusClass = $status === 'healthy' ? 'good' : ($status === 'delayed' ? 'warn' : 'danger');
    $age = $health['price_age_seconds'] ?? null;
    $mode = strtolower((string)($health['cycle_mode'] ?? 'cron'));
    $modeLabel = match($mode) { 'internal'=>'ABS Scheduler', 'manual'=>'Manual Only', default=>'Production Cron' };
    $interval = (int)($health['target_price_refresh_seconds'] ?? 60);
@endphp

<section class="lab-stage-report enterprise-surface">
    <div class="lab-section-head"><div><span class="lab-eyebrow">STAGE 01 · MARKET DATA</span><h2>Are prices ready for the next scan?</h2></div><span class="admin-status {{ $statusClass }}">{{ strtoupper($status) }}</span></div>
    <div class="lab-stage-report-grid">
        <div><small>Mode</small><b>{{ $modeLabel }}</b></div>
        <div><small>Effective interval</small><b>{{ $mode === 'manual' ? 'On demand' : $interval.' sec' }}</b></div>
        <div><small>Markets with prices</small><b>{{ number_format((int)($health['latest_price_symbols'] ?? 0)) }}</b></div>
        <div><small>Latest price age</small><b>{{ $age === null ? 'No data' : number_format((int)$age).' sec' }}</b></div>
        <div><small>Last update</small><b>{{ !empty($health['latest_price_observed_at']) ? \Illuminate\Support\Carbon::parse($health['latest_price_observed_at'])->format('d M · H:i:s') : 'Waiting' }}</b></div>
    </div>
    @if(($health['stale_for_scanning'] ?? true))
        <div class="lab-note warn"><b>Scan protection active:</b> refresh prices before evaluating new strategy opportunities.</div>
    @else
        <div class="lab-note"><b>Ready:</b> the Strategy Lab can use these stored prices for the next evaluation cycle.</div>
    @endif
</section>

<section class="enterprise-surface no-pad lab-market-surface">
    <div class="enterprise-section-head padded"><div><h2>Latest stored prices</h2><p>Most recently observed ABS market values.</p></div><span class="report-period-chip">{{ number_format($prices->count()) }} SHOWN</span></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Market</th><th>Price</th><th>24h</th><th>Observed</th><th>Source</th></tr></thead><tbody>
    @forelse($prices as $price)
        <tr><td><b>{{ $price->symbol }}</b></td><td>{{ rtrim(rtrim(number_format((float)$price->price,8,'.',''), '0'), '.') }}</td><td>{{ $price->change_percent_24h === null ? '—' : number_format((float)$price->change_percent_24h,2).'%' }}</td><td>{{ $price->observed_at?->format('d M · H:i:s') ?? '—' }}<small>{{ $price->observed_at?->diffForHumans() ?? '' }}</small></td><td>{{ strtoupper((string)$price->source) }}</td></tr>
    @empty<tr><td colspan="5">No centralized prices are stored yet. Use Refresh Prices Now.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="enterprise-surface no-pad lab-market-surface">
    <div class="enterprise-section-head padded"><div><h2>Price refresh history</h2><p>Each row is one completed or attempted Stage 1 update.</p></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Run</th><th>Status</th><th>Prices updated</th><th>Candle markets</th><th>Completed</th><th>Error</th></tr></thead><tbody>
    @forelse($runs as $run)
        <tr><td><b>#{{ $run->id }}</b></td><td><span class="admin-status {{ $run->status==='completed'?'good':($run->status==='failed'?'danger':'warn') }}">{{ strtoupper($run->status) }}</span></td><td>{{ number_format((int)$run->prices_updated) }}</td><td>{{ number_format((int)$run->candle_symbols_updated) }}</td><td>{{ $run->completed_at?->format('d M · H:i:s') ?? 'Running' }}</td><td>{{ $run->error_message ? \Illuminate\Support\Str::limit($run->error_message,80) : '—' }}</td></tr>
    @empty<tr><td colspan="6">No price refresh runs recorded yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
