@extends('pulse.layout')
@section('title','Pulse Signals')
@section('heading','Pulse Signals')
@section('content')
<?php
    $number = fn ($value, $decimals = 2) => number_format((float) $value, $decimals);
    $price = fn ($value) => number_format((float) $value, (float) $value < 1 ? 4 : 0);
    $percent = fn ($value, $decimals = 1) => number_format((float) $value, $decimals).'%';
    $rows = collect($page['signals']);
    $selected = $page['selected_signal'];
    $statusFilter = strtolower((string) request('status', 'active'));
    $historyMode = $statusFilter === 'history';
    $queueHeading = $historyMode ? 'Signal History' : ($statusFilter === 'all' ? 'All Signals' : 'Active Signal Queue');
    $queueCount = $historyMode ? $rows->count().' historical' : ($statusFilter === 'all' ? $rows->count().' total' : $page['active_count'].' active');
    $can = fn (string $capability): bool => (bool) data_get($page, "capabilities.{$capability}.enabled", false);
    $lastScanAt = data_get($page, 'last_scan_at');
    $lastScanIso = $lastScanAt?->toIso8601String();
    $lastScanText = $lastScanAt?->diffForHumans() ?? 'after next scan';
    $positionSide = $page['settings']->position_mode ?: 'BOTH';
    $defaultLeverage = max(1, (int) ($page['settings']->default_leverage ?: 1));
    $sizingMode = (string) ($page['settings']->sizing_mode ?: 'fixed_notional');
    $fixedNotional = max(0, (float) ($page['settings']->fixed_notional ?: 0));
    $fixedQuantity = max(0, (float) ($page['settings']->fixed_quantity ?: 0));
    $accountEquity = max(0, (float) data_get($page, 'connection.permissions.account_equity', 0));
    $availableBalance = max(0, (float) data_get($page, 'connection.permissions.available_balance', 0));
    $actionHrefFor = function (array $action, int $signalId): string {
        $target = $action['target'] ?? 'guided';
        if ($target === 'trade' && ! empty($action['trade_id'])) return route('pulse.trades.show', $action['trade_id']);
        if ($target === 'binance') return route('pulse.binance.index');
        if ($target === 'risk') return route('pulse.risk.index');
        if ($target === 'environment') return route('pulse.settings.edit', ['focus' => 'environment']);
        if ($target === 'plans') return route('pulse.plans');
        if ($target === 'unavailable') return route('pulse.signals.index', ['selected' => $signalId]);
        if ($target === 'signal_detail') return route('pulse.signals.show', $signalId);
        if ($target === 'monitor_signal') return route('pulse.signals.index', ['selected' => $signalId]);
        return route('pulse.signals.index', ['selected' => $signalId, 'open_trade' => $signalId]);
    };
?>
<div class="pp-page pp-signals">
    <header class="pp-page-head">
        <div class="pp-title">
            <h1>Pulse Signals</h1>
            <p>Track each qualified signal from opportunity detection through entry, active trade and expiry.</p>
        </div>
        <div class="pp-head-actions">
            <a class="pp-button" href="{{ route('pulse.signals.index', ['status' => 'history']) }}">@include('pulse.partials.icon', ['name' => 'history']) Signal History</a>
            <?php if ($can('scanner')): ?>
                <a class="pp-button primary" href="{{ route('pulse.scanner') }}">@include('pulse.partials.icon', ['name' => 'pulse']) Find Best Signal</a>
            <?php else: ?>
                <span class="pp-button disabled" aria-disabled="true">@include('pulse.partials.icon', ['name' => 'pulse']) Scanner Not Included</span>
            <?php endif; ?>
            <div class="pp-environment">
                <b>BINANCE {{ strtoupper($page['settings']->environment) }}</b>
                <span class="{{ $page['connection_ready'] ? 'connected' : '' }}">{{ $page['connection_ready'] ? 'Connected' : 'Connection check required' }}</span>
                <small>Last update: <span data-relative-time data-timestamp="{{ $lastScanIso }}">{{ $lastScanText }}</span></small>
            </div>
        </div>
    </header>

    <section class="pp-metrics four pp-metrics-simple">
        <article class="pp-metric"><span class="pp-metric-icon cyan">@include('pulse.partials.icon', ['name' => 'pulse'])</span><div class="pp-metric-copy"><small>Active Signals</small><strong>{{ $page['active_count'] }}</strong><em>Across {{ $page['active_pairs'] }} pairs</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon cyan">@include('pulse.partials.icon', ['name' => 'crosshair'])</span><div class="pp-metric-copy"><small>High-Conviction</small><strong>{{ $page['high_conviction_count'] }}</strong><em>Score 80 or higher</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon green">@include('pulse.partials.icon', ['name' => 'arrow-up'])</span><div class="pp-metric-copy"><small>Long Setups</small><strong>{{ $page['long_count'] }}</strong><em>{{ $page['active_count'] > 0 ? $percent(($page['long_count'] / $page['active_count']) * 100) : '0.0%' }} of active</em></div></article>
        <article class="pp-metric"><span class="pp-metric-icon red">@include('pulse.partials.icon', ['name' => 'arrow-down'])</span><div class="pp-metric-copy"><small>Short Setups</small><strong>{{ $page['short_count'] }}</strong><em>{{ $page['active_count'] > 0 ? $percent(($page['short_count'] / $page['active_count']) * 100) : '0.0%' }} of active</em></div></article>
    </section>

    <section class="pp-card pp-signals-filters">
        <div class="pp-section-head"><h2>Signal View</h2><span>Signal generation settings are Admin controlled in ABS V15</span></div>
        <div class="pp-filter-chips"><a class="pp-chip" href="{{ route('pulse.signals.index',['status'=>'active']) }}">Active</a><a class="pp-chip" href="{{ route('pulse.signals.index',['status'=>'all']) }}">All Signals</a><a class="pp-chip" href="{{ route('pulse.signals.index',['status'=>'history']) }}">History</a></div>
    </section>

    <section class="pp-card pp-signals-queue">
        <div class="pp-section-head">
            <h2>{{ $queueHeading }}</h2>
            <span>{{ $queueCount }}&nbsp; · &nbsp;Updated <span data-relative-time data-timestamp="{{ $lastScanIso }}">{{ $lastScanText }}</span></span>
        </div>
        <div class="pp-results-scroll">
            <table class="pp-table pp-signal-table">
                <thead><tr><th>Pair</th><th>Direction</th><th>Score</th><th>Entry Price</th><th>Stop Loss</th><th>Take Profit</th><th>Timeframe</th><th>Status</th><th class="pp-action-col">Action</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                        $tradeAction = $row['trade_action'] ?? ['phase' => 'Entry Watch', 'kind' => 'opportunity', 'direct' => false, 'label' => 'Open Trade', 'detail' => 'Review the live price against the signal entry before execution.','target' => 'signal'];
                        $actionHref = $actionHrefFor($tradeAction, (int) $row['id']);
                    ?>
                    <tr class="{{ $selected && $selected['id'] === $row['id'] ? 'selected' : '' }}">
                        <td><a href="{{ route('pulse.signals.index', array_merge(request()->except('selected', 'open_trade'), ['selected' => $row['id']])) }}">{{ $row['pair'] }}</a></td>
                        <td><span class="pp-pill {{ strtolower($row['direction']) === 'neutral' ? 'watch' : strtolower($row['direction']) }}">{{ $row['direction'] === 'NEUTRAL' ? 'WATCH' : $row['direction'] }}</span></td>
                        <td>{{ number_format($row['score'], 0) }}</td>
                        <td><strong class="pp-price-entry">{{ $price($row['entry_price']) }}</strong></td>
                        <td>{{ $price($row['stop_loss']) }}</td>
                        <td>{{ $price($row['take_profit']) }}</td>
                        <td>{{ $row['timeframe'] }}</td>
                        <td><span class="pp-signal-phase {{ $tradeAction['kind'] ?? 'opportunity' }}">{{ $tradeAction['phase'] ?? 'Entry Watch' }}</span></td>
                        <td class="pp-action-col">
                            <div class="pp-signal-action-stack">
                                <?php if (! $historyMode): ?>
                                    <button
                                        class="pp-signal-action {{ $tradeAction['kind'] ?? 'opportunity' }}"
                                        type="button"
                                        title="{{ $tradeAction['detail'] }}"
                                        data-guided-trade
                                        data-action-url="{{ route('pulse.signals.execute', $row['id']) }}"
                                        data-target-url="{{ $actionHref }}"
                                        data-setup-target="{{ $tradeAction['target'] ?? 'guided' }}"
                                        data-can-execute="{{ ($tradeAction['direct'] ?? false) ? '1' : '0' }}"
                                        data-managed-setup="{{ ($tradeAction['managed_setup'] ?? false) ? '1' : '0' }}"
                                        data-action-phase="{{ $tradeAction['phase'] ?? 'Entry Watch' }}"
                                        data-signal-id="{{ $row['id'] }}"
                                        data-pair="{{ $row['pair'] }}"
                                        data-direction="{{ $row['direction'] }}"
                                        data-score="{{ number_format($row['score'], 0, '.', '') }}"
                                        data-timeframe="{{ $row['timeframe'] }}"
                                        data-action-label="{{ $tradeAction['label'] }}"
                                        data-action-detail="{{ $tradeAction['detail'] }}"
                                        data-action-kind="{{ $tradeAction['kind'] ?? 'opportunity' }}"
                                        data-execution-profile="{{ $tradeAction['execution_profile'] ?? 'entry_watch' }}"
                                        data-last-price="{{ $row['last_price'] }}"
                                        data-entry-low="{{ $row['entry_low'] }}"
                                        data-entry-high="{{ $row['entry_high'] }}"
                                        data-entry-price="{{ $row['entry_price'] }}"
                                        data-stop-loss="{{ $row['stop_loss'] }}"
                                        data-take-profit="{{ $row['take_profit'] }}"
                                    >{{ $tradeAction['label'] }}</button>
                                <?php else: ?>
                                    <a class="pp-signal-action {{ $tradeAction['kind'] ?? 'opportunity' }}" href="{{ $actionHref }}" title="{{ $tradeAction['detail'] }}">{{ $tradeAction['label'] }}</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows->isEmpty()): ?><tr><td colspan="9" class="pp-empty">No signals match the current filters. Run the scanner or review signal history.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="pp-card pp-signal-selected" id="signal-monitor">
        <?php if ($selected): ?>
            <?php
                $selectedAction = $selected['trade_action'] ?? ['phase' => 'Entry Watch', 'kind' => 'opportunity', 'direct' => false, 'label' => 'Open Trade', 'detail' => 'Review the live price against the signal entry before execution.', 'target' => 'signal'];
                $selectedActionHref = $actionHrefFor($selectedAction, (int) $selected['id']);
            ?>
            <div class="pp-selected-head">
                <div><h2 class="pp-selected-title">Selected Signal — {{ $selected['pair'] }}</h2><p>{{ implode(' · ', $selected['strategies'] ?? [$selected['strategy']]) }}</p></div>
                <span class="pp-selected-score">Score <strong>{{ number_format($selected['score'], 0) }}</strong>/100</span>
            </div>
            <div class="pp-selected-grid">
                <article class="pp-evidence">
                    <h3>Strategy Evidence <small>{{ count($selected['strategies'] ?? []) }} confirmations</small></h3>
                    <div class="pp-evidence-list">
                        <?php foreach (collect($selected['evidence'])->take(3) as $evidence): ?>
                            <div class="pp-evidence-row">
                                @include('pulse.partials.icon', ['name' => $evidence['matched'] ? 'check' : 'alert'])
                                <span><b>{{ $evidence['label'] }}</b><?php if (! empty($evidence['detail'])): ?><small>{{ $evidence['detail'] }}</small><?php endif; ?></span>
                                <strong class="{{ $evidence['matched'] ? 'pp-positive' : 'pp-warning' }}">{{ $evidence['value'] }}</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($selected['evidence_total'] ?? 0) > 3): ?><div class="pp-evidence-more">+{{ (int) $selected['evidence_total'] - 3 }} additional strategy confirmations</div><?php endif; ?>
                </article>

                <article class="pp-risk-snapshot">
                    <h3>Risk Snapshot <small>Protected LIMIT plan</small></h3>
                    <div class="pp-snapshot-list">
                        <div><span>Entry Zone</span><strong>{{ $price($selected['entry_low']) }}–{{ $price($selected['entry_high']) }}</strong></div>
                        <div><span>Limit Entry</span><strong>{{ $price($selected['entry_price']) }}</strong></div>
                        <div><span>Stop Loss</span><strong>{{ $price($selected['stop_loss']) }}</strong></div>
                        <div><span>Take Profit</span><strong>{{ $price($selected['take_profit']) }}</strong></div>
                        <div><span>Configured Risk</span><strong class="pp-positive">{{ number_format((float) $page['settings']->risk_per_trade_percent, 2) }}%</strong></div>
                    </div>
                    <div class="pp-snapshot-note">@include('pulse.partials.icon', ['name' => 'shield']) TP/SL protection is attached after the Binance entry fill is confirmed.</div>
                </article>

                <div class="pp-selected-side">
                    <article class="pp-decision">
                        <div class="pp-decision-heading"><h3>Signal Status</h3><span class="pp-decision-state {{ $selectedAction['kind'] ?? 'opportunity' }}">{{ $selectedAction['phase'] ?? 'Entry Watch' }}</span></div>
                        <p>{{ $selectedAction['detail'] }}</p>
                        <div class="pp-decision-actions">
                            <?php if ($selected['record_status'] === 'active'): ?><form method="POST" action="{{ route('pulse.signals.dismiss', $selected['id']) }}">@csrf @method('PATCH')<button class="pp-button" type="submit">Dismiss</button></form><?php endif; ?>
                            <a class="pp-button" href="{{ route('pulse.signals.show', $selected['id']) }}">Full Analysis</a>
                            <?php if (! $historyMode): ?>
                                <button
                                    class="pp-button primary"
                                    type="button"
                                    data-guided-trade
                                    data-action-url="{{ route('pulse.signals.execute', $selected['id']) }}"
                                    data-target-url="{{ $selectedActionHref }}"
                                    data-setup-target="{{ $selectedAction['target'] ?? 'guided' }}"
                                    data-can-execute="{{ ($selectedAction['direct'] ?? false) ? '1' : '0' }}"
                                    data-managed-setup="{{ ($selectedAction['managed_setup'] ?? false) ? '1' : '0' }}"
                                    data-action-phase="{{ $selectedAction['phase'] ?? 'Entry Watch' }}"
                                    data-signal-id="{{ $selected['id'] }}"
                                    data-pair="{{ $selected['pair'] }}"
                                    data-direction="{{ $selected['direction'] }}"
                                    data-score="{{ number_format($selected['score'], 0, '.', '') }}"
                                    data-timeframe="{{ $selected['timeframe'] }}"
                                    data-action-label="{{ $selectedAction['label'] }}"
                                    data-action-detail="{{ $selectedAction['detail'] }}"
                                    data-action-kind="{{ $selectedAction['kind'] ?? 'stage' }}"
                                    data-execution-profile="{{ $selectedAction['execution_profile'] ?? 'entry_watch' }}"
                                    data-last-price="{{ $selected['last_price'] }}"
                                    data-entry-low="{{ $selected['entry_low'] }}"
                                    data-entry-high="{{ $selected['entry_high'] }}"
                                    data-entry-price="{{ $selected['entry_price'] }}"
                                    data-stop-loss="{{ $selected['stop_loss'] }}"
                                    data-take-profit="{{ $selected['take_profit'] }}"
                                >@include('pulse.partials.icon', ['name' => 'send']) {{ $selectedAction['label'] }}</button>
                            <?php else: ?>
                                <a class="pp-button primary" href="{{ $selectedActionHref }}">{{ $selectedAction['label'] }}</a>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            </div>
            <article class="pp-card" style="margin-top:16px">
                <div class="pp-section-head"><h3>V15 Signal Tools</h3><span>Included with package · {{ number_format((int)($selected['share_count'] ?? 0)) }} shares</span></div>
                <div class="pp-decision-actions">
                    <button class="pp-button" type="button" data-pulse-share data-url="{{ route('pulse.signals.share',$selected['id']) }}">@include('pulse.partials.icon',['name'=>'share']) Share Signal</button>
                    <button class="pp-button" type="button" data-pulse-explain data-url="{{ route('pulse.signals.explain',$selected['id']) }}">AI Explain</button>
                </div>
                <div class="pulse-legal-note" data-pulse-ai-output style="margin-top:12px">{{ $selected['ai_explanation'] ?: 'AI explanation is optional and controlled by Admin. It explains the existing signal; it never changes scoring, confidence or trade levels.' }}</div>
                <div class="pulse-legal-note" data-pulse-share-output hidden></div>
            </article>
        <?php else: ?>
            <div class="pp-empty">Select an active signal to review its evidence, protected entry plan and execution action.</div>
        <?php endif; ?>
    </section>

    <footer class="pp-page-note"><span>Signals support decision-making and do not guarantee outcomes. Review risk before execution.</span><a class="pp-link" href="{{ route('pulse.signals.index', ['status' => 'history']) }}">View signal history @include('pulse.partials.icon', ['name' => 'chevron-right'])</a></footer>
</div>

<div
    class="pp-trade-overlay"
    data-trade-execution-overlay
    data-environment="{{ strtoupper($page['settings']->environment) }}"
    data-environment-value="{{ strtolower($page['settings']->environment) }}"
    data-leverage="{{ $defaultLeverage }}"
    data-position-side="{{ $positionSide }}"
    data-sizing-mode="{{ $sizingMode }}"
    data-fixed-notional="{{ $fixedNotional }}"
    data-fixed-quantity="{{ $fixedQuantity }}"
    data-account-equity="{{ $accountEquity }}"
    data-available-balance="{{ $availableBalance }}"
    data-execution-mode="{{ strtolower((string) $page['settings']->execution_mode) }}"
    hidden
>
    <button class="pp-trade-scrim" type="button" data-trade-execution-close aria-label="Close trade execution"></button>
    <aside class="pp-trade-drawer pp-trade-drawer-simple" role="dialog" aria-modal="true" aria-labelledby="pp-trade-title">
        <header class="pp-trade-drawer-head">
            <div>
                <span class="pp-trade-kicker">PULSE ORDER</span>
                <h2 id="pp-trade-title">Open Trade</h2>
                <p data-trade-detail>Review the signal levels and confirm the Binance order.</p>
            </div>
            <button class="pp-trade-close" type="button" data-trade-execution-close aria-label="Close">×</button>
        </header>

        @if($errors->has('execution'))
            <div class="pp-trade-server-error">@include('pulse.partials.icon', ['name' => 'alert']) <span>{{ $errors->first('execution') }}</span></div>
        @endif

        <form method="POST" action="" data-guided-trade-form>
            @csrf
            <input type="hidden" name="environment" value="{{ strtolower($page['settings']->environment) }}">
            <input type="hidden" name="order_type" value="LIMIT">
            <input type="hidden" name="leverage" value="{{ $defaultLeverage }}">
            <input type="hidden" name="price" value="">
            <input type="hidden" name="stop_loss" value="">
            <input type="hidden" name="take_profit" value="">
            <input type="hidden" name="position_side" value="{{ $positionSide }}">
            <input type="hidden" name="time_in_force" value="GTC">
            <input type="hidden" name="client_reference" value="">
            <input type="hidden" name="confirmed_review" value="1">

            <section class="pp-trade-simple-body">
                <div class="pp-trade-hero">
                    <div class="pp-trade-symbol"><span data-trade-asset>BTC</span><div><strong data-trade-pair>BTC/USDT</strong><small><span data-trade-direction>LONG</span> · <span data-trade-timeframe>15M</span></small></div></div>
                    <div class="pp-trade-confidence"><small>Signal Score</small><strong data-trade-score>0</strong><span>/100</span></div>
                </div>

                <div class="pp-trade-simple-status">
                    <span data-trade-status>ENTRY CONFIRMED</span>
                    <p data-trade-guidance>Price is at the planned entry and the signal is ready for execution.</p>
                </div>

                <div class="pp-binance-recommendation" data-binance-recommendation>
                    <div class="pp-binance-recommendation-head"><span>RECOMMENDED BINANCE EXECUTION</span><b data-binance-order-badge>LIMIT ORDER</b></div>
                    <h3 data-binance-recommendation-title>Place Limit Order at Signal Entry</h3>
                    <p data-binance-recommendation-detail>Use the planned signal entry as a Binance Futures LIMIT order. Pulse will revalidate risk and protection before submission.</p>
                </div>

                <div class="pp-trade-levels pp-trade-levels-simple">
                    <div><small>Current</small><strong data-trade-last>—</strong></div>
                    <div><small>Entry</small><strong data-trade-entry>—</strong></div>
                    <div><small>Stop Loss</small><strong class="pp-negative" data-trade-stop>—</strong></div>
                    <div><small>Take Profit</small><strong class="pp-positive" data-trade-target>—</strong></div>
                </div>

                <div class="pp-trade-simple-meta pp-trade-simple-meta-compact">
                    <div><small>Environment</small><strong data-trade-environment>{{ strtoupper($page['settings']->environment) }}</strong></div>
                    <div><small>Leverage</small><strong>{{ $defaultLeverage }}×</strong></div>
                    <div><small>Trade Size</small><strong data-trade-size>—</strong></div>
                    <div><small>Max Loss at SL</small><strong class="pp-negative" data-trade-risk>—</strong></div>
                </div>

                <div class="pp-live-warning" data-live-warning hidden>@include('pulse.partials.icon', ['name' => 'alert']) <span><b>LIVE account.</b> Opening this trade can use real funds on Binance Futures.</span></div>
                <div class="pp-testnet-note" data-testnet-note>@include('pulse.partials.icon', ['name' => 'info']) <span>Testnet mode is active. No real funds are used.</span></div>

                <div class="pp-managed-setup-note" data-managed-setup-note hidden>@include('pulse.partials.icon', ['name' => 'shield']) <span><b>Managed setup.</b> ABS will enable guided signal execution and use your saved safe defaults automatically when you confirm. No Settings step is required.</span></div>

                <div class="pp-trade-confirm-copy" data-trade-protection-note>@include('pulse.partials.icon', ['name' => 'shield']) <span>Pulse performs the final balance, risk and Binance permission checks before sending the protected order.</span></div>

                <div class="pp-trade-footer pp-trade-footer-simple pp-trade-footer-confirm">
                    <button class="pp-button" type="button" data-trade-execution-close>Cancel</button>
                    <a class="pp-button primary pp-trade-next" href="{{ route('pulse.signals.index') }}" data-trade-next-link hidden><span data-trade-next-label>Continue</span></a>
                    <button class="pp-button primary pp-trade-submit" type="submit" data-trade-submit>@include('pulse.partials.icon', ['name' => 'send']) <span data-trade-submit-label>Confirm &amp; Open Trade</span></button>
                </div>
                <small class="pp-trade-submit-note" data-trade-submit-note>The order is sent only after you confirm and press Confirm &amp; Open Trade.</small>
            </section>
        </form>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function relativeTime(iso) {
        if (!iso) return 'after next scan';
        var stamp = Date.parse(iso);
        if (!Number.isFinite(stamp)) return 'after next scan';
        var seconds = Math.max(0, Math.floor((Date.now() - stamp) / 1000));
        if (seconds < 45) return 'just now';
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + ' minute' + (minutes === 1 ? '' : 's') + ' ago';
        var hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
        var days = Math.floor(hours / 24);
        return days + ' day' + (days === 1 ? '' : 's') + ' ago';
    }

    function refreshRelativeTimes() {
        document.querySelectorAll('[data-relative-time]').forEach(function (node) {
            node.textContent = relativeTime(node.getAttribute('data-timestamp'));
        });
    }

    function number(value) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function price(value) {
        var amount = number(value);
        if (!amount) return '—';
        var decimals = amount < 1 ? 4 : (amount < 100 ? 2 : 0);
        return amount.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function money(value) {
        var amount = number(value);
        return '$' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setText(root, selector, value) {
        var node = root.querySelector(selector);
        if (node) node.textContent = value;
    }

    function closeTrade(overlay) {
        overlay.classList.remove('open');
        document.body.classList.remove('pp-trade-open');
        window.setTimeout(function () { overlay.hidden = true; }, 180);
    }

    function openTrade(overlay, trigger) {
        var form = overlay.querySelector('[data-guided-trade-form]');
        var environment = (overlay.dataset.environment || 'TESTNET').toUpperCase();
        var environmentValue = (overlay.dataset.environmentValue || 'testnet').toLowerCase();
        var leverage = Math.max(1, number(overlay.dataset.leverage));
        var entry = number(trigger.dataset.entryPrice);
        var stop = number(trigger.dataset.stopLoss);
        var target = number(trigger.dataset.takeProfit);
        var last = number(trigger.dataset.lastPrice);
        var fixedNotional = number(overlay.dataset.fixedNotional);
        var fixedQuantity = number(overlay.dataset.fixedQuantity);
        var sizingMode = overlay.dataset.sizingMode || 'fixed_notional';
        var quantity = sizingMode === 'fixed_quantity' && fixedQuantity > 0 ? fixedQuantity : (entry > 0 && fixedNotional > 0 ? fixedNotional / entry : 0);
        var notional = entry * quantity;
        var risk = Math.abs(entry - stop) * quantity;
        var pair = trigger.dataset.pair || 'Market';
        var direction = trigger.dataset.direction || 'LONG';
        var phase = trigger.dataset.actionPhase || 'Entry Watch';
        var canExecute = trigger.dataset.canExecute === '1';
        var managedSetup = trigger.dataset.managedSetup === '1';
        var targetUrl = trigger.dataset.targetUrl || '#';
        var setupTarget = trigger.dataset.setupTarget || 'guided';

        form.action = trigger.dataset.actionUrl || '';
        form.querySelector('[name="environment"]').value = environmentValue;
        form.querySelector('[name="price"]').value = entry;
        form.querySelector('[name="stop_loss"]').value = stop;
        form.querySelector('[name="take_profit"]').value = target;
        form.querySelector('[name="client_reference"]').value = ('PS' + (trigger.dataset.signalId || '') + '-' + Date.now().toString().slice(-12)).slice(0, 36);
        setText(overlay, '#pp-trade-title', phase === 'Trade Open' ? 'Trade Monitor' : (phase === 'Signal Closed' ? 'Signal Review' : 'Open Trade'));
        setText(overlay, '[data-trade-detail]', trigger.dataset.actionDetail || 'Review the protected trade plan before submission.');
        setText(overlay, '[data-trade-asset]', pair.split('/')[0] || pair.replace('USDT', ''));
        setText(overlay, '[data-trade-pair]', pair);
        setText(overlay, '[data-trade-direction]', direction);
        setText(overlay, '[data-trade-timeframe]', trigger.dataset.timeframe || '—');
        setText(overlay, '[data-trade-score]', trigger.dataset.score || '0');
        setText(overlay, '[data-trade-status]', phase.toUpperCase());
        setText(overlay, '[data-trade-guidance]', trigger.dataset.actionDetail || 'Review this signal and the recommended Binance action.');
        setText(overlay, '[data-trade-last]', price(last));
        setText(overlay, '[data-trade-entry]', price(entry));
        setText(overlay, '[data-trade-stop]', price(stop));
        setText(overlay, '[data-trade-target]', price(target));
        setText(overlay, '[data-trade-size]', sizingMode === 'fixed_quantity' ? (quantity.toLocaleString(undefined, {maximumFractionDigits: 8}) + ' ' + (pair.split('/')[0] || 'units')) : money(notional));
        setText(overlay, '[data-trade-risk]', money(risk));
        setText(overlay, '[data-trade-environment]', environment);
        var recommendationBadge = 'LIMIT ENTRY';
        var recommendationTitle = 'Place Binance Limit Entry';
        var recommendationDetail = 'Recommended execution: use the original signal entry as a Binance Futures LIMIT order with the signal stop loss and take profit. Pulse revalidates risk before submission.';
        var nextLabel = canExecute ? 'Confirm & Open Trade' : (setupTarget === 'binance' ? 'Connect Binance' : (setupTarget === 'plans' ? 'View Trading Plans' : (setupTarget === 'risk' ? 'Review Trading Pause' : (setupTarget === 'environment' ? 'Choose Trading Environment' : (setupTarget === 'unavailable' ? 'Trading Temporarily Unavailable' : 'Continue')))));
        if (phase === 'Entry Ready') {
            recommendationBadge = 'ENTRY READY';
            recommendationTitle = 'Open at the Planned Entry';
            recommendationDetail = 'Live Binance price is inside the signal entry zone. A protected LIMIT entry at the planned signal price is the preferred execution.';
        } else if (phase === 'Move in Progress') {
            recommendationBadge = 'PULLBACK LIMIT';
            recommendationTitle = 'Avoid Chasing — Use the Signal Entry';
            recommendationDetail = 'Price has already moved in the signal direction. The professional approach is to keep the original LIMIT entry and wait for a pullback rather than chase the market.';
        } else if (phase === 'Entry Watch') {
            recommendationBadge = 'REVIEW ENTRY';
            recommendationTitle = 'Signal Valid — Review Before Entry';
            recommendationDetail = 'Price has moved away from the entry against the signal but has not reached the stop. You may place the planned LIMIT entry, but review the live price and risk before confirming.';
        } else if (phase === 'Trade Open') {
            recommendationBadge = 'ACTIVE TRADE';
            recommendationTitle = 'Manage the Existing Binance Trade';
            recommendationDetail = 'An order or position already exists for this signal. Use the trade monitor rather than creating a duplicate position.';
            nextLabel = 'Manage Trade';
        } else if (phase === 'Signal Closed') {
            recommendationBadge = 'CLOSED';
            recommendationTitle = 'Signal Closed — Fresh Entry Not Recommended';
            recommendationDetail = 'The signal has expired or live price reached its TP/SL boundary. Review the result and run a fresh scan before opening a new trade.';
            nextLabel = 'View Signal';
        }
        if (!canExecute && setupTarget === 'binance') {
            recommendationBadge = 'ONE-TIME SETUP';
            recommendationTitle = 'Connect Binance Once';
            recommendationDetail = 'Add and verify your Binance Futures API connection once. After that, Pulse handles the execution defaults and trade checks for you.';
        } else if (!canExecute && setupTarget === 'plans') {
            recommendationBadge = 'PLAN ACCESS';
            recommendationTitle = 'Trading Access Is Not Included';
            recommendationDetail = 'Your current plan can view signals but cannot submit Binance orders. Choose a trading-enabled Pulse plan to continue.';
        } else if (!canExecute && setupTarget === 'risk') {
            recommendationBadge = 'TRADING PAUSED';
            recommendationTitle = 'Account Trading Pause Is Active';
            recommendationDetail = 'Your Emergency Stop is protecting the account. Review Risk Controls before allowing any new order.';
        } else if (!canExecute && setupTarget === 'environment') {
            recommendationBadge = 'ENVIRONMENT';
            recommendationTitle = 'Choose an Available Trading Environment';
            recommendationDetail = 'The selected environment is not currently available for this account. Choose Practice/Testnet or an eligible Live environment once.';
        } else if (!canExecute && setupTarget === 'unavailable') {
            recommendationBadge = 'PAUSED';
            recommendationTitle = 'Trading Is Temporarily Unavailable';
            recommendationDetail = 'ABS has paused new execution at platform level. Signal monitoring remains available and no customer setting needs to be changed.';
        }

        setText(overlay, '[data-binance-order-badge]', recommendationBadge);
        setText(overlay, '[data-binance-recommendation-title]', recommendationTitle);
        setText(overlay, '[data-binance-recommendation-detail]', recommendationDetail);
        setText(overlay, '[data-trade-submit-label]', 'Confirm & Open Trade');

        var submit = overlay.querySelector('[data-trade-submit]');
        var nextLink = overlay.querySelector('[data-trade-next-link]');
        if (submit) submit.hidden = !canExecute;
        if (nextLink) {
            nextLink.hidden = canExecute;
            nextLink.href = targetUrl;
            nextLink.setAttribute('aria-disabled', setupTarget === 'unavailable' ? 'true' : 'false');
            nextLink.classList.toggle('disabled', setupTarget === 'unavailable');
            setText(nextLink, '[data-trade-next-label]', nextLabel);
        }
        var submitNote = overlay.querySelector('[data-trade-submit-note]');
        if (submitNote) submitNote.hidden = !canExecute;
        var protectionNote = overlay.querySelector('[data-trade-protection-note]');
        if (protectionNote) protectionNote.hidden = !canExecute;
        var managedNote = overlay.querySelector('[data-managed-setup-note]');
        if (managedNote) managedNote.hidden = !(canExecute && managedSetup);

        var liveWarning = overlay.querySelector('[data-live-warning]');
        var testnetNote = overlay.querySelector('[data-testnet-note]');
        if (liveWarning) liveWarning.hidden = !canExecute || environment !== 'LIVE';
        if (testnetNote) testnetNote.hidden = !canExecute || environment === 'LIVE';
        overlay.classList.toggle('live', environment === 'LIVE');

        overlay.hidden = false;
        requestAnimationFrame(function () { overlay.classList.add('open'); });
        document.body.classList.add('pp-trade-open');
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshRelativeTimes();
        window.setInterval(refreshRelativeTimes, 30000);

        var overlay = document.querySelector('[data-trade-execution-overlay]');
        if (!overlay) return;

        document.querySelectorAll('[data-guided-trade]').forEach(function (trigger) {
            trigger.addEventListener('click', function () { openTrade(overlay, trigger); });
        });
        var autoOpenSignal = new URLSearchParams(window.location.search).get('open_trade');
        if (autoOpenSignal) {
            var autoTrigger = document.querySelector('[data-guided-trade][data-signal-id="' + autoOpenSignal.replace(/[^0-9]/g, '') + '"]');
            if (autoTrigger) {
                openTrade(overlay, autoTrigger);
                var cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('open_trade');
                window.history.replaceState({}, '', cleanUrl.pathname + (cleanUrl.search ? cleanUrl.search : ''));
            }
        }
        overlay.querySelectorAll('[data-trade-execution-close]').forEach(function (button) {
            button.addEventListener('click', function () { closeTrade(overlay); });
        });
        var form = overlay.querySelector('[data-guided-trade-form]');
        if (form) form.addEventListener('submit', function () {
            var submit = overlay.querySelector('[data-trade-submit]');
            if (submit) {
                submit.disabled = true;
                submit.classList.add('loading');
                setText(overlay, '[data-trade-submit-label]', 'Opening Trade…');
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !overlay.hidden) closeTrade(overlay);
        });
    });
})();
</script>
@endpush

@push('scripts')
<script>
(function(){
    function csrf(){ return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
    document.addEventListener('click', async function(event){
        var share=event.target.closest('[data-pulse-share]');
        if(share){
            share.disabled=true;
            try{
                var response=await fetch(share.dataset.url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf(),'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify({channel:navigator.share?'native':'copy'})});
                var json=await response.json(); if(!response.ok) throw new Error(json.message||'Unable to create share card.');
                var text=(json.data?.text||'')+'\n'+(json.data?.url||'');
                if(navigator.share) await navigator.share({title:json.data?.title||'ABS Pulse Signal',text:json.data?.text||'',url:json.data?.url||location.href});
                else await navigator.clipboard.writeText(text);
                var out=document.querySelector('[data-pulse-share-output]'); if(out){out.hidden=false;out.textContent='Share content ready. '+(navigator.share?'Share sheet opened.':'Copied to clipboard.');}
            }catch(e){ var out=document.querySelector('[data-pulse-share-output]'); if(out){out.hidden=false;out.textContent=e.message;} } finally{share.disabled=false;}
        }
        var ai=event.target.closest('[data-pulse-explain]');
        if(ai){
            ai.disabled=true; var out=document.querySelector('[data-pulse-ai-output]'); if(out) out.textContent='Generating explanation…';
            try{
                var response=await fetch(ai.dataset.url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf(),'Accept':'application/json'}}); var json=await response.json();
                if(!response.ok) throw new Error(json.message||'AI explanation unavailable.'); if(out) out.textContent=json.data?.explanation||'Explanation ready.';
            }catch(e){ if(out) out.textContent=e.message; } finally{ai.disabled=false;}
        }
    });
})();
</script>
@endpush
