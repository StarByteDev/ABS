# ABS V14.6.11 Static Validation Report

**Build:** ABS V14.6.11 — Futures Positioning Metrics Build  
**Date:** 22 August 2026

## Passed checks

- PHP syntax: **211 files passed**.
- JavaScript syntax: **5 files passed**.
- Source/public homepage CSS synchronization: **passed**.
- Source/public application JavaScript synchronization: **passed**.
- Futures data service contains Binance global Long/Short Ratio support: **passed**.
- Market overview exposes `long_short_ratio`: **passed**.
- Market overview exposes `perp_premium_basis`: **passed**.
- Homepage includes the compact post-Futures-Insights metrics strip: **passed**.
- Live JavaScript selectors for both new metrics are present: **passed**.
- Existing Futures Insights OI + Funding Rate structure remains present: **passed**.
- ZIP layout prepared with application files at archive root: **passed**.

## Runtime note

The packaging container does not include Composer `vendor/`, so Laravel runtime/provider calls were not executed here. On Laragon, run `php artisan abs:market-test`; V14.6.11 extends that diagnostic to Long/Short Ratio and Perp Premium Basis in addition to the existing price/chart/movers/liquidations/OI/funding/Fear & Greed/industry checks.
