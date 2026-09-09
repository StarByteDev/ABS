@extends('admin.layout')
@section('title','Binance Futures Markets | ABS Admin')
@section('heading','Binance Futures Markets')
@section('content')
<div class="enterprise-command-bar admin-market-command">
    <div><span class="release-eyebrow">PULSE MARKET CATALOG</span><h2>Exchange-synchronized trading market control</h2><p>Keep the Pulse catalog aligned with currently tradable Binance USD‑M perpetual Futures markets. Users only see enabled markets that are also permitted by their Pulse package.</p></div>
    <form method="POST" action="{{ route('admin.pulse.pairs.sync') }}">@csrf<button class="button button-primary">↻ Sync Binance Futures Markets</button></form>
</div>

<div class="enterprise-kpi-grid">
    <div class="enterprise-kpi"><small>Catalog Markets</small><strong>{{ number_format($summary['total']) }}</strong></div>
    <div class="enterprise-kpi"><small>Enabled</small><strong>{{ number_format($summary['enabled']) }}</strong></div>
    <div class="enterprise-kpi"><small>Exchange Synced</small><strong>{{ number_format($summary['synced']) }}</strong></div>
    <div class="enterprise-kpi"><small>Quote Assets</small><strong>{{ $quotes->count() }}</strong></div>
</div>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Market Catalog</h2><p>Search the full synchronized catalog. Disabling a market removes it from new user selection and scanning without deleting historical signal/trade data.</p></div></div>
    <form method="GET" class="admin-market-filter-bar">
        <label><span>Search</span><input type="search" name="q" value="{{ request('q') }}" placeholder="BTC, ETH, SOL, USDT..."></label>
        <label><span>Quote asset</span><select name="quote"><option value="">All quotes</option>@foreach($quotes as $quote)<option value="{{ $quote }}" @selected(request('quote')===$quote)>{{ $quote }}</option>@endforeach</select></label>
        <label><span>Status</span><select name="status"><option value="">All</option><option value="enabled" @selected(request('status')==='enabled')>Enabled</option><option value="disabled" @selected(request('status')==='disabled')>Disabled</option></select></label>
        <button class="button button-primary">Apply Filters</button><a class="button button-ghost" href="{{ route('admin.pulse.pairs') }}">Reset</a>
    </form>

    <div class="enterprise-table-wrap"><table class="enterprise-table admin-market-table">
        <thead><tr><th>Market</th><th>Quote</th><th>Tick / Step</th><th>Minimums</th><th>Last Sync</th><th>Availability</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($pairs as $pair)
            <tr>
                <td><b>{{ $pair->symbol }}</b><small>{{ $pair->base_asset }} / {{ $pair->quote_asset }}</small></td>
                <td><span class="admin-market-quote">{{ $pair->quote_asset }}</span></td>
                <td><code>{{ $pair->tick_size ?: '—' }}</code><small>Step {{ $pair->step_size ?: '—' }}</small></td>
                <td><span>Qty {{ $pair->minimum_quantity ?: '—' }}</span><small>Notional {{ $pair->minimum_notional ?: '—' }}</small></td>
                <td>{{ $pair->last_synced_at?->diffForHumans() ?? 'Never' }}</td>
                <td><span class="admin-status {{ $pair->is_enabled?'good':'warn' }}">{{ $pair->is_enabled?'Enabled':'Disabled' }}</span></td>
                <td>
                    <form method="POST" action="{{ route('admin.pulse.pairs.update',$pair) }}" class="admin-market-inline-form">@csrf @method('PUT')
                        <input type="hidden" name="symbol" value="{{ $pair->symbol }}"><input type="hidden" name="base_asset" value="{{ $pair->base_asset }}"><input type="hidden" name="quote_asset" value="{{ $pair->quote_asset }}"><input type="hidden" name="sort_order" value="{{ $pair->sort_order }}"><input type="hidden" name="price_precision" value="{{ $pair->price_precision }}"><input type="hidden" name="quantity_precision" value="{{ $pair->quantity_precision }}"><input type="hidden" name="tick_size" value="{{ $pair->tick_size }}"><input type="hidden" name="step_size" value="{{ $pair->step_size }}"><input type="hidden" name="minimum_quantity" value="{{ $pair->minimum_quantity }}"><input type="hidden" name="minimum_notional" value="{{ $pair->minimum_notional }}">
                        <input type="hidden" name="is_enabled" value="{{ $pair->is_enabled ? 0 : 1 }}">
                        <button class="button button-ghost">{{ $pair->is_enabled ? 'Disable' : 'Enable' }}</button>
                    </form>
                </td>
            </tr>
        @empty<tr><td colspan="7">No markets match these filters.</td></tr>@endforelse
        </tbody>
    </table></div>
    <div class="admin-market-pagination">{{ $pairs->onEachSide(1)->links('vendor.pagination.abs-admin') }}</div>
</section>

<details class="enterprise-surface enterprise-plan-editor"><summary><div><h2>Add market manually</h2><p>Normally use Binance Sync. Manual entry is available for controlled testing or exchange-listing preparation.</p></div><span>Open editor</span></summary><form method="POST" action="{{ route('admin.pulse.pairs.store') }}" class="admin-form-grid">@csrf @include('admin.pulse.partials.pair-fields',['pair'=>null])<div class="full admin-page-actions"><button class="button button-primary">Add Market</button></div></form></details>
@endsection
