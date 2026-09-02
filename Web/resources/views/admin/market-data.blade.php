@extends('admin.layout')
@section('title','Market Data — ABS Admin')
@section('heading','Central Market Data')
@section('content')
@php
$status = $health['feed_status'] ?? 'offline';
$statusClass = $status === 'healthy' ? 'good' : ($status === 'delayed' ? 'warn' : 'danger');
$age = $health['price_age_seconds'] ?? null;
@endphp
<section class="enterprise-command-bar compact">
    <div><h2>ABS central price architecture</h2><p>Binance Futures is fetched centrally by ABS. Website, scanner, signals and mobile APIs read the shared ABS database instead of each user requesting prices from Binance.</p></div>
    <form method="POST" action="{{ route('admin.market-data.refresh') }}">@csrf<button class="button button-primary">Refresh Now</button></form>
</section>
<section class="enterprise-mini-kpis">
    <div><small>Feed status</small><strong><span class="admin-status {{ $statusClass }}">{{ strtoupper($status) }}</span></strong></div>
    <div><small>Target frequency</small><strong>{{ (int)($health['target_price_refresh_seconds'] ?? 60) }} sec</strong></div>
    <div><small>Latest price age</small><strong>{{ $age === null ? 'No data' : $age.' sec' }}</strong></div>
    <div><small>Symbols stored</small><strong>{{ number_format((int)($health['latest_price_symbols'] ?? 0)) }}</strong></div>
    <div><small>Cron profile</small><strong>{{ $health['scheduler_profile'] ?? 'standard' }}</strong></div>
    <div><small>Cron interval</small><strong>{{ (int)($health['scheduler_cron_minutes'] ?? 1) }} min</strong></div>
</section>
@if(($health['stale_for_scanning'] ?? true))
<div class="flash flash-error"><b>Scanner protection active.</b> Central prices are stale/offline. Do not create new trading decisions from old market data until the feed is healthy.</div>
@elseif($status === 'delayed')
<div class="flash flash-warning"><b>Market feed delayed.</b> Prices are still available but the latest refresh is outside the normal one-minute target.</div>
@endif
<section class="enterprise-surface no-pad">
<div class="enterprise-section-head padded"><div><h2>Latest ABS prices</h2><p>These are the exact centralized values available to ABS services.</p></div></div>
<div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Symbol</th><th>Price</th><th>24h</th><th>Observed</th><th>Age</th><th>Source</th></tr></thead><tbody>
@forelse($prices as $price)<tr><td><b>{{ $price->symbol }}</b></td><td>{{ rtrim(rtrim(number_format((float)$price->price,8,'.',''), '0'), '.') }}</td><td>{{ $price->change_percent_24h === null ? '—' : number_format((float)$price->change_percent_24h,2).'%' }}</td><td>{{ $price->observed_at?->format('d M Y H:i:s') ?? '—' }}</td><td>{{ $price->observed_at?->diffForHumans() ?? '—' }}</td><td>{{ $price->source }}</td></tr>
@empty<tr><td colspan="6">No centralized prices are stored yet.</td></tr>@endforelse
</tbody></table></div></section>
<section class="enterprise-surface no-pad">
<div class="enterprise-section-head padded"><div><h2>Recent synchronization runs</h2><p>Use this log to verify the HostGator one-minute cron is continuously feeding ABS.</p></div></div>
<div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>Run</th><th>Status</th><th>Prices</th><th>Candles</th><th>Validation</th><th>Completed</th><th>Error</th></tr></thead><tbody>
@forelse($runs as $run)<tr><td>#{{ $run->id }}</td><td><span class="admin-status {{ $run->status==='completed'?'good':($run->status==='failed'?'danger':'warn') }}">{{ ucfirst($run->status) }}</span></td><td>{{ $run->prices_updated }}</td><td>{{ $run->candle_symbols_updated }}</td><td>{{ $run->validation_symbols_updated }}</td><td>{{ $run->completed_at?->format('d M H:i:s') ?? '—' }}</td><td>{{ $run->error_message ? \Illuminate\Support\Str::limit($run->error_message,90) : '—' }}</td></tr>
@empty<tr><td colspan="7">No synchronization runs recorded yet.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
