# ABS V15.2.0 — Free Signal, Ads CMS & Strategy Intelligence Dashboard

## Scope

V15.2.0 is an additive upgrade on the deployed V15.1.6 Laravel web/backend. It deliberately preserves the existing `PulseScannerService` and its 15-strategy scoring logic. The new features consume that engine rather than replacing it.

## Free Signal decision flow

1. Select a random active qualified signal created by the same member/Professional scan flow.
2. If unavailable, select an active qualified system/research signal.
3. If still unavailable, run the same market-wide 15M + 4H scanner used by the Professional flow and use its qualified best signal when one is produced.
4. If no setup qualifies, explicitly state that no qualified Pulse signal is available and show a **BTC Market Outlook** instead.

The BTC outlook calls the existing analyzer for BTCUSDT on 15M and 4H, combines the directional evidence (60% 15M / 40% 4H), shows bullish-vs-bearish strategy balance and watch levels, and is labelled as market intelligence rather than a qualified trade signal.

## Ads CMS

Admin → Ads CMS controls Google display snippets for three predefined Free Signal placements:

- Above the Free Signal workspace
- Between the reveal/teaser area and membership CTA
- Above the risk notice

The master display switch can pause all placements without deleting saved snippets. Rewarded unlock inventory is intentionally separate under Admin → Rewarded Signal Ads.

## Strategy Intelligence Dashboard

The new Admin → Strategy Dashboard focuses on user-independent system research and separates two evidence layers:

- **Research / paper evidence:** generated system signals, observed entries, TP, SL, ambiguous/expired/void outcomes, win rate, Net R, profit factor, expectancy, daily cumulative R, and per-strategy attribution.
- **Actual exchange execution:** real `pulse_trades` records, closed/open counts, exchange-confirmed TP/SL exits, realized P&L and fees.

This separation means a future user-level Binance automation option can reuse the existing execution/trade reconciliation layer without presenting research profitability as realized account profit.

## Background cadence

Admin can select 30s, 60s, 2m or 5m. The selected value controls central market refresh, strategy research and validation in local/standard scheduler mode.

- Local/standard: `php artisan schedule:work` supports the 30-second scheduler tick and runtime due guards apply the configured interval.
- HostGator shared: existing once-per-minute cPanel cron remains valid. Effective cadence is automatically clamped to at least 60 seconds.

`RUN-ABS-LARAGON.bat` now opens the background `schedule:work` process automatically before starting the local web server.

## Validation queue protection

Frequent research generation can create many samples. Signal validation now selects only signals without a validation row or with an unresolved validation row before applying the processing limit. Already-resolved rows therefore cannot occupy the oldest 500 slots and starve new research evidence.

## Database impact

No new migration is required. V15.2.0 stores Ads CMS, research-engine state and cadence controls in the existing `pulse_system_settings` table and uses the existing scanner, signal, validation, market-data and trade tables.

## Verification

Static release contract:

```bash
node tests/Release/verify-v1520-free-signal-ads-strategy-dashboard.mjs
```

Core scanner and ABS production logo hashes are verified by the release test to detect accidental changes.
