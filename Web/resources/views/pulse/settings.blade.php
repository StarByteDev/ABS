@extends('pulse.layout')
@section('title','Trading Setup')
@section('heading','Trading Setup')
@section('content')
@php
    $capabilityEnabled = fn (string $key) => (bool) data_get($capabilities ?? [], $key.'.enabled', false);
    $canAlerts = $capabilityEnabled('alerts');
    $canExecute = ($manualAllowed ?? false) || ($automaticAllowed ?? false);
    $pairLocked = (bool) data_get($pairSelectionLock ?? [], 'locked', false);
    $pairLockHours = (int) data_get($pairSelectionLock ?? [], 'lock_hours', 50);
    $eligibleSelectedPairs = array_values(array_intersect((array) ($settings->selected_pairs ?? []), $pairs->pluck('symbol')->all()));
@endphp
<div class="pulse-page-hero">
    <div>
        <span>TRADING SETUP</span>
        <h1>Set up Pulse in one place</h1>
        <p>Follow the steps below from practice/live mode through Binance, risk and markets. Advanced controls remain available for professional traders without making the normal setup difficult.</p>
    </div>
    <div class="pulse-actions"><?php if ($plan): ?><a class="pulse-button secondary" href="{{ route('pulse.plans') }}">{{ $plan->name }}</a><?php endif; ?><a class="pulse-button secondary" href="{{ route('pulse.risk.index') }}">Risk Controls</a></div>
</div>

<section class="pulse-setup-overview">
    <div class="pulse-setup-status">
        @foreach($setupSteps ?? [] as $step)
        <a href="{{ $step['href'] }}" class="pulse-setup-step {{ $step['ready'] ? 'ready' : '' }}"><span>{{ $loop->iteration }}</span><div><b>{{ $step['label'] }}</b><small>{{ $step['ready'] ? 'Ready' : 'Needs attention' }}</small></div></a>
        @endforeach
    </div>
    <div class="pulse-feed-card {{ ($marketHealth['feed_status'] ?? 'offline') }}">
        <small>ABS MARKET FEED</small><b>{{ strtoupper($marketHealth['feed_status'] ?? 'offline') }}</b><span>Binance Futures → ABS database · {{ (int)($marketHealth['target_price_refresh_seconds'] ?? 60) }} sec target</span>
    </div>
</section>

<form method="POST" action="{{ route('pulse.settings.update') }}" class="pulse-form">@csrf @method('PUT')
    <div class="pulse-grid pulse-grid-two">
        <article class="pulse-panel" id="environment">
            <h2>Trading setup</h2>
            <div class="pulse-managed-banner"><b>Managed by ABS</b><span>For signal trades, Pulse uses the protected signal Entry / SL / TP, your saved trade size and risk limits, and automatically enables guided manual execution when you confirm a trade.</span></div>
            <div class="pulse-form-grid">
                <div class="pulse-field">
                    <label>Trading environment</label>
                    <select name="environment">
                        <option value="testnet" @selected($settings->environment==='testnet' || !($liveAllowed ?? false))>Practice / Testnet</option>
                        <?php if (($liveAllowed ?? false) && ($liveServerEnabled ?? false)): ?>
                            <option value="live" @selected($settings->environment==='live')>Live</option>
                        <?php endif; ?>
                    </select>
                    <small>Live appears only when both your plan and the ABS installation allow it.</small>
                </div>
                <details class="pulse-managed-advanced full">
                    <summary>Advanced execution preferences</summary>
                    <p>Most customers do not need to change these. Guided Open Trade works with ABS-managed defaults and your risk limits.</p>
                    <div class="pulse-form-grid">
                        <div class="pulse-field">
                            <label>Execution preference</label>
                            <select name="execution_mode">
                                <option value="signal_only" @selected($settings->execution_mode==='signal_only' || (!$manualAllowed && !$automaticAllowed))>Signals only until I confirm a trade</option>
                                <?php if ($manualAllowed ?? false): ?><option value="manual" @selected($settings->execution_mode==='manual')>Guided manual execution</option><?php endif; ?>
                                <?php if (($automaticAllowed ?? false) && ($automaticServerEnabled ?? false)): ?><option value="automatic" @selected($settings->execution_mode==='automatic')>Automatic execution</option><?php endif; ?>
                            </select>
                            <small>You do not need to change this to open a signal trade; Confirm &amp; Open Trade enables guided execution automatically when your plan and Binance connection allow it.</small>
                        </div>
                        <div class="pulse-field"><label>Default order type</label><select name="default_order_type"><option value="MARKET" @selected($settings->default_order_type==='MARKET')>Market</option><option value="LIMIT" @selected($settings->default_order_type==='LIMIT')>Limit</option></select><small>Signal Open Trade uses the protected signal LIMIT entry regardless of this general preference.</small></div>
                        <div class="pulse-field"><label>Default leverage</label><input type="number" name="default_leverage" min="1" max="{{ config('pulse.risk.max_leverage',20) }}" value="{{ $settings->default_leverage }}"><small>Installation maximum: {{ config('pulse.risk.max_leverage',20) }}×.</small></div>
                        <div class="pulse-field"><label>Margin type</label><select name="margin_type"><option value="ISOLATED" @selected($settings->margin_type==='ISOLATED')>Isolated</option><option value="CROSSED" @selected($settings->margin_type==='CROSSED')>Cross</option></select></div>
                        <div class="pulse-field full"><label>Binance position mode</label><select name="position_mode"><option value="BOTH" @selected($settings->position_mode==='BOTH')>BOTH — one-way account mode</option><option value="LONG" @selected($settings->position_mode==='LONG')>LONG — hedge account mode</option><option value="SHORT" @selected($settings->position_mode==='SHORT')>SHORT — hedge account mode</option></select><small>This must match the position mode already configured in Binance. Pulse does not change the account-wide Binance mode.</small></div>
                    </div>
                </details>
            </div>
            <input type="hidden" name="auto_trade_enabled" value="{{ ($automaticAllowed ?? false) && $settings->execution_mode==='automatic' ? 1 : 0 }}">
            <label class="pulse-checkbox"><input type="checkbox" name="emergency_stop" value="1" @checked($settings->emergency_stop)><span><b>Keep emergency stop active</b><br><small>Blocks new execution requests and automatic runs. Use it whenever you want Pulse execution stopped at account level.</small></span></label>
        </article>

        <article class="pulse-panel" id="signal-quality">
            <h2>Default order size and signal quality</h2>
            <div class="pulse-form-grid">
                <div class="pulse-field full"><label>Sizing mode</label><select name="sizing_mode"><option value="fixed_notional" @selected($settings->sizing_mode==='fixed_notional')>Fixed USDT notional</option><option value="fixed_quantity" @selected($settings->sizing_mode==='fixed_quantity')>Fixed asset quantity</option></select></div>
                <div class="pulse-field" data-sizing="fixed_notional"><label>Fixed notional (USDT)</label><input type="number" name="fixed_notional" min="1" step="0.01" value="{{ $settings->fixed_notional }}"></div>
                <div class="pulse-field" data-sizing="fixed_quantity"><label>Fixed quantity</label><input type="number" name="fixed_quantity" min="0" step="0.00000001" value="{{ $settings->fixed_quantity }}"></div>
                <div class="pulse-field"><label>Minimum signal score</label><input type="number" name="minimum_signal_score" min="0" max="100" step="0.1" value="{{ $settings->minimum_signal_score }}"></div>
                <div class="pulse-field"><label>Maximum open positions</label><?php if ((int)($plan?->max_open_trades ?? 0) > 0): ?><input type="number" name="max_open_positions" min="1" max="{{ (int)$plan->max_open_trades }}" value="{{ min((int)$settings->max_open_positions,(int)$plan->max_open_trades) }}"><?php else: ?><input type="hidden" name="max_open_positions" value="1"><input value="Not included in this plan" disabled><?php endif; ?></div>
            </div>
            <p class="pulse-legal-note">Plan maximum open positions: <b>{{ (int)($plan?->max_open_trades ?? 0) > 0 ? $plan->max_open_trades : 'Not included' }}</b>.</p>
        </article>
    </div>

    <div class="pulse-grid pulse-grid-two">
        <article class="pulse-panel" id="risk-controls">
            <h2>Protection and account limits</h2>
            <div class="pulse-form-grid">
                <div class="pulse-field"><label>Risk per trade (%)</label><input type="number" name="risk_per_trade_percent" min="0.1" max="10" step="0.1" value="{{ $settings->risk_per_trade_percent }}"></div>
                <div class="pulse-field"><label>Daily realized loss limit (USDT)</label><input type="number" name="daily_loss_limit" min="0" step="0.01" value="{{ $settings->daily_loss_limit }}"></div>
                <div class="pulse-field"><label>Default take profit (%)</label><input type="number" name="take_profit_percent" min="0.1" max="50" step="0.1" value="{{ $settings->take_profit_percent }}"></div>
                <div class="pulse-field"><label>Default stop loss (%)</label><input type="number" name="stop_loss_percent" min="0.1" max="25" step="0.1" value="{{ $settings->stop_loss_percent }}"></div>
            </div>
            <div class="pulse-warning"><b>Exchange-side protection</b><span>When execution is enabled and an entry fills, Pulse attempts to place protection orders. If protection cannot be confirmed, Pulse records the failure and can trigger its emergency-close workflow.</span></div>
        </article>

        <article class="pulse-panel pulse-market-selector {{ $pairLocked ? 'is-selection-locked' : '' }}" id="selected-markets" data-market-selector data-limit="{{ (int)($plan?->max_selected_pairs ?? 5) }}" data-locked="{{ $pairLocked ? '1' : '0' }}">
            <div class="pulse-market-selector-head">
                <div><span class="pulse-kicker">BINANCE USD-M FUTURES</span><h2>Trading Markets</h2><p>Search the Binance Futures catalog and choose the markets you want Pulse to monitor and scan. Your package controls availability and the maximum number you can save.</p></div>
                <div class="pulse-market-count"><strong data-market-selected-count>{{ count($eligibleSelectedPairs) }}</strong><span>/ {{ (int)($plan?->max_selected_pairs ?? 5) }} selected</span></div>
            </div>
            <div class="pulse-market-toolbar">
                <label class="pulse-market-search">@include('pulse.partials.icon',['name'=>'search'])<input type="search" placeholder="Search BTC, ETH, SOL..." autocomplete="off" data-market-search></label>
                <div class="pulse-market-quotes" data-market-quotes><button type="button" class="active" data-quote="all">All</button>@foreach(collect($pairs)->pluck('quote_asset')->filter()->unique()->sort() as $quote)<button type="button" data-quote="{{ $quote }}">{{ $quote }}</button>@endforeach</div>
                <div class="pulse-market-actions"><button type="button" class="pulse-button secondary" data-market-select-all @disabled($pairLocked)>Select All</button><button type="button" class="pulse-button secondary" data-market-clear @disabled($pairLocked)>Clear All</button><button type="button" class="pulse-button secondary" data-market-popular @disabled($pairLocked)>Popular</button></div>
            </div>
            @if($pairLocked)
                <div class="pulse-market-lock-notice">
                    @include('pulse.partials.icon',['name'=>'lock'])
                    <div><b>Market selection locked</b><span>Your saved pairs stay fixed for {{ $pairLockHours }} hours. You can change them again in {{ data_get($pairSelectionLock,'remaining_human','the remaining lock period') }} @if($settings->pair_selection_locked_until) ({{ $settings->pair_selection_locked_until->format('d M Y, H:i') }}) @endif.</span></div>
                </div>
                @foreach($eligibleSelectedPairs as $selectedSymbol)<input type="hidden" name="selected_pairs[]" value="{{ $selectedSymbol }}">@endforeach
            @endif
            <div class="pulse-market-feedback {{ $pairLocked ? 'locked' : '' }}" data-market-feedback>{{ $pairLocked ? 'You can search and review markets now; selection changes unlock automatically after the cooldown.' : 'Choose up to '.(int)($plan?->max_selected_pairs ?? 5).' execution/watch markets included in '.($plan?->name ?? 'your plan').'. Run Market Scan evaluates these selected markets. Saving a changed selection locks it for '.$pairLockHours.' hours.' }}</div>
            <div class="pulse-market-grid">
                <?php foreach ($pairs as $pair): $isSelected = in_array($pair->symbol,$settings->selected_pairs ?? []); ?>
                <label class="pulse-market-option" data-market-option data-symbol="{{ strtoupper($pair->symbol) }}" data-base="{{ strtoupper($pair->base_asset) }}" data-quote="{{ strtoupper($pair->quote_asset) }}">
                    <input type="checkbox" @if(! $pairLocked) name="selected_pairs[]" @endif value="{{ $pair->symbol }}" @checked($isSelected) @disabled($pairLocked)>
                    <span class="pulse-market-check">✓</span>
                    <span class="pulse-market-asset"><b>{{ $pair->base_asset }}</b><small>/ {{ $pair->quote_asset }}</small></span>
                    <span class="pulse-market-symbol">{{ $pair->symbol }}</span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="pulse-market-footer"><span><b>{{ number_format($pairs->count()) }}</b> plan-eligible Binance Futures markets available.</span><span>{{ $pairLocked ? 'Selection is protected by the 50-hour change cooldown.' : 'Catalog is synchronized from Binance exchange information by Admin.' }}</span></div>
        </article>
    </div>

    <article class="pulse-panel">
        <div class="pulse-grid pulse-grid-two" style="margin:0">
            <div>
                <h2>{{ $canAlerts ? 'Alert preferences' : 'Pulse plan availability' }}</h2>
                <?php if ($canAlerts): ?>
                    <?php foreach (['signals'=>'Qualified signal alerts','trades'=>'Trade and order updates','risk'=>'Risk and protection failures','system'=>'Pulse system notices'] as $key=>$label): ?><label class="pulse-checkbox"><input type="checkbox" name="notify_{{ $key }}" value="1" @checked(data_get($settings->notification_preferences,$key,true))><span>{{ $label }}</span></label><?php endforeach; ?>
                <?php else: ?>
                    <p class="pulse-legal-note">Alerts are not included in your current Pulse plan. Review available plans or contact support if you need Alerts access.</p>
                <?php endif; ?>
            </div>
            <div>
                <h2>Plan-controlled execution</h2>
                <div class="pulse-availability-list">
                    <span><b>Practice trading</b><em>{{ ($practiceAllowed ?? false) ? 'Included' : 'Not included' }}</em></span>
                    <span><b>Manual execution</b><em>{{ ($manualAllowed ?? false) ? 'Included' : 'Not included' }}</em></span>
                    <span><b>Live trading</b><em>{{ (($liveAllowed ?? false) && ($liveServerEnabled ?? false)) ? 'Available' : (($liveAllowed ?? false) ? 'Plan enabled · platform disabled' : 'Not included') }}</em></span>
                    <span><b>Automatic trading</b><em>{{ (($automaticAllowed ?? false) && ($automaticServerEnabled ?? false)) ? 'Available' : (($automaticAllowed ?? false) ? 'Plan enabled · platform disabled' : 'Not included') }}</em></span>
                </div>
                <button class="pulse-button" type="submit">Save Preferences</button>
            </div>
        </div>
    </article>
</form>
@endsection
