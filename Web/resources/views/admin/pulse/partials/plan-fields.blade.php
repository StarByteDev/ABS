@php
    $p = $plan;
    $workspaceCapabilities = ['scanner','signals','orders','trades','reports','binance','alerts','plan_view','settings','mobile_api'];
    $executionCapabilities = ['testnet_trading','manual_trading','live_trading','auto_trading'];
    $capabilityEnabled = static function (string $key) use ($p) {
        if (request()->old('capabilities') !== null) {
            return in_array($key, (array) old('capabilities', []), true);
        }
        return $p ? $p->allows($key, true) : true;
    };
@endphp

<div class="admin-plan-section full">
    <div class="admin-plan-section-head">
        <div><h3>Plan identity</h3><p>Name the plan exactly as customers should see it. The slug is used internally and can be generated automatically.</p></div>
    </div>
    <div class="admin-form-grid compact-grid">
        <label>Name<input name="name" required value="{{ old('name',$p?->name) }}" placeholder="e.g. Pulse Trial"></label>
        <label>Slug<input name="slug" value="{{ old('slug',$p?->slug) }}" placeholder="auto-generated"></label>
        <label class="full">Customer description<textarea name="description" rows="3" placeholder="Explain who this plan is for and what it includes.">{{ old('description',$p?->description) }}</textarea></label>
        <label>Package price (USDT)<input type="number" name="monthly_price" min="0" step="0.01" value="{{ old('monthly_price',$p?->monthly_price ?? 0) }}"><small>The exact amount the member transfers before Admin verification.</small></label>
        <input type="hidden" name="currency" value="USDT">
        <label>Access duration (days)<input type="number" name="access_days" min="1" max="3650" value="{{ old('access_days',$p?->access_days ?? 30) }}"></label>
        <label>Customer badge<input name="badge" maxlength="50" value="{{ old('badge',$p?->badge) }}" placeholder="e.g. Most popular"></label>
        <label>Display order<input type="number" name="sort_order" min="0" value="{{ old('sort_order',$p?->sort_order ?? 0) }}"></label>
        <label class="admin-check admin-check-inline"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$p?->is_active ?? true))><span>Active / assignable</span></label>
        <label class="admin-check admin-check-inline"><input type="checkbox" name="is_public" value="1" @checked(old('is_public',$p?->is_public ?? true))><span>Show on customer plan page</span></label>
        <label class="admin-check admin-check-inline"><input type="checkbox" name="request_enabled" value="1" @checked(old('request_enabled',$p?->request_enabled ?? true))><span>Allow USDT payment requests</span></label>
        <label class="admin-check admin-check-inline"><input type="checkbox" name="requires_payment" value="1" @checked(old('requires_payment',$p?->requires_payment ?? true))><span>Payment required</span></label>
        <label class="admin-check admin-check-inline"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$p?->is_featured ?? false))><span>Highlight this plan</span></label>
        <label class="admin-check admin-check-inline"><input type="checkbox" name="is_trial" value="1" @checked(old('is_trial',$p?->is_trial ?? false))><span>Trial plan (banner only, never a paid plan card)</span></label>
    </div>
</div>

<div class="admin-plan-section full">
    <div class="admin-plan-section-head">
        <div><h3>Trading & market safeguards</h3><p>Best Signal access is included with an active package and has no per-signal wallet charge. These controls remain for trading safety and package market scope.</p></div>
    </div>
    <div class="admin-form-grid compact-grid admin-limit-grid">
        <label>Default minimum signal score<input type="number" name="minimum_signal_score" min="0" max="100" step="1" value="{{ old('minimum_signal_score',$p?->minimum_signal_score ?? 70) }}"><small>Admin/package qualification threshold. Users cannot override this in V15.</small></label>
        <label>Manual trades/day<input type="number" name="manual_trades_per_day" min="0" value="{{ old('manual_trades_per_day',$p?->manual_trades_per_day ?? 25) }}"></label>
        <label>Automatic trades/day<input type="number" name="auto_trades_per_day" min="0" value="{{ old('auto_trades_per_day',$p?->auto_trades_per_day ?? 10) }}"></label>
        <label>Max open positions<input type="number" name="max_open_trades" min="0" value="{{ old('max_open_trades',$p?->max_open_trades ?? 5) }}"></label>
        <label>Market safety ceiling<input type="number" name="max_selected_pairs" min="1" value="{{ old('max_selected_pairs',$p?->max_selected_pairs ?? 20) }}"></label>
    </div>
</div>

<div class="admin-plan-section full admin-plan-pair-scope" data-plan-pair-scope>
    <div class="admin-plan-section-head">
        <div><h3>Binance Futures market access</h3><p>Choose whether this package can use the full synchronized Binance USD-M perpetual catalog or only selected markets. The separate “Market safety ceiling” value still acts as a safety ceiling for automatic package-universe scanning.</p></div>
        <span class="admin-scope-count">{{ number_format($pairs->count()) }} synced markets</span>
    </div>
    @php
        $pairMode = old('pair_access_mode', $p?->pair_access_mode ?? 'all');
        $selectedPairIds = collect(old('pair_ids', $p?->pairs?->pluck('id')->all() ?? []))->map(fn($id)=>(int)$id)->all();
    @endphp
    <div class="admin-market-mode-grid">
        <label class="admin-market-mode-card {{ $pairMode==='all'?'active':'' }}"><input type="radio" name="pair_access_mode" value="all" @checked($pairMode==='all')><span><b>All Binance Futures Markets</b><small>Automatically includes new supported USD-M perpetual pairs after Admin sync. Best for Professional/full-access packages.</small></span></label>
        <label class="admin-market-mode-card {{ $pairMode==='selected'?'active':'' }}"><input type="radio" name="pair_access_mode" value="selected" @checked($pairMode==='selected')><span><b>Selected Markets Only</b><small>Use this for lower-tier packages where only specific trading pairs should be available.</small></span></label>
    </div>
    <div class="admin-pair-scope-selected" data-plan-pair-selected-panel @if($pairMode!=='selected') hidden @endif>
        <div class="admin-pair-picker-toolbar"><input type="search" placeholder="Search BTC, ETH, USDT..." data-plan-pair-search><div class="admin-pair-picker-actions"><button type="button" class="button button-ghost" data-plan-pair-select-all>Select All</button><button type="button" class="button button-ghost" data-plan-pair-clear-all>Clear All</button></div><span><b data-plan-pair-count>{{ count($selectedPairIds) }}</b> included in package</span></div>
        <div class="admin-pair-picker-grid">
            @foreach($pairs as $pair)
                <label class="admin-pair-chip" data-plan-pair-option data-search="{{ strtoupper($pair->symbol.' '.$pair->base_asset.' '.$pair->quote_asset) }}"><input type="checkbox" name="pair_ids[]" value="{{ $pair->id }}" @checked(in_array($pair->id,$selectedPairIds,true))><span><b>{{ $pair->base_asset }}</b>/{{ $pair->quote_asset }}<small>{{ $pair->symbol }}</small></span></label>
            @endforeach
        </div>
    </div>
</div>

<div class="admin-plan-section full">
    <div class="admin-plan-section-head">
        <div><h3>Pulse feature access</h3><p>These switches decide which sections appear in the user's Pulse navigation and which routes/API features the plan can use.</p></div>
    </div>
    <div class="admin-capability-grid">
        @foreach($workspaceCapabilities as $key)
            <label class="admin-capability-toggle">
                <input type="checkbox" name="capabilities[]" value="{{ $key }}" @checked($capabilityEnabled($key))>
                <span><b>{{ $capabilityLabels[$key] }}</b><small>{{ match($key) {
                    'scanner' => 'Run the strategy-based market scanner.',
                    'signals' => 'Review generated signal evidence and details.',
                    'orders' => 'View exchange orders and open positions.',
                    'trades' => 'Review and manage Pulse trade records.',
                    'reports' => 'Access trading and performance reports.',
                    'binance' => 'Save, verify and manage Binance API connections.',
                    'alerts' => 'View Pulse account, market and risk alerts.',
                    'plan_view' => 'View the assigned package, payment status and availability.',
                    'settings' => 'Configure user risk and execution preferences; signal intelligence remains Admin controlled.',
                    'mobile_api' => 'Use authenticated Pulse endpoints from the future mobile app.',
                    default => ''
                } }}</small></span>
            </label>
        @endforeach
    </div>
</div>

<div class="admin-plan-section full">
    <div class="admin-plan-section-head">
        <div><h3>Trading permissions</h3><p>Plan access is only one layer. Live and automatic execution still require the platform-wide safety settings and server configuration to be enabled.</p></div>
    </div>
    <div class="admin-capability-grid admin-capability-grid-compact">
        @foreach($executionCapabilities as $key)
            <label class="admin-capability-toggle">
                <input type="checkbox" name="capabilities[]" value="{{ $key }}" @checked($capabilityEnabled($key))>
                <span><b>{{ $capabilityLabels[$key] }}</b><small>{{ match($key) {
                    'testnet_trading' => 'Allow Practice Binance Futures execution.',
                    'manual_trading' => 'Allow user-confirmed order execution.',
                    'live_trading' => 'Make Live execution eligible when global safety gates are enabled.',
                    'auto_trading' => 'Make automatic execution eligible when global safety gates are enabled.',
                    default => ''
                } }}</small></span>
            </label>
        @endforeach
    </div>
</div>

<div class="admin-plan-section full">
    <div class="admin-plan-section-head">
        <div><h3>Strategy access</h3><p>Select the strategies available to this plan. A disabled strategy is not used by the plan scanner.</p></div>
    </div>
    <div class="admin-check-grid admin-strategy-grid">
        @foreach($strategies as $strategy)
            <label class="admin-check"><input type="checkbox" name="strategy_ids[]" value="{{ $strategy->id }}" @checked(in_array($strategy->id,old('strategy_ids',$p?->strategies?->pluck('id')->all() ?? $strategies->pluck('id')->all())))><span>{{ $strategy->name }}</span></label>
        @endforeach
    </div>
</div>
