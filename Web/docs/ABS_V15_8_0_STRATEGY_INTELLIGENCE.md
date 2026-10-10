# ABS V15.8.0 — Strategy Intelligence, TP Milestone Learning & Full-Market Scan

## Purpose
Make Strategy Engine & Evaluation understandable as an evidence workflow: scans create signals, entry creates research trades, target milestones are observed, final outcomes are resolved, and strategy learning gradually influences future confidence.

## Learning rule
The existing signal confidence model remains 75% technical + 25% learned reliability. V15.8.0 enriches learned reliability with a capped partial-target bonus: TP1, TP2 and TP3 milestone rates add a small positive bonus while final TP/SL Bayesian win evidence remains dominant. This avoids falsely classifying a strategy as strong merely because trades touched an early target.

## Reporting
Trade Results shows entered trades, final TP, SL, Net R, expired/ambiguous outcomes, TP1/TP2/TP3 counts, progression funnel, and a filterable evidence register. Strategy Intelligence shows signals, entries, TP milestones, final TP/SL, win rate, Net R and learned score.

## Market scanner
For an authenticated member with scanner access, Find Best Signal now evaluates every enabled Binance USD-M Futures pair synchronized into `pulse_pairs`, independent of package pair assignments. Package membership still controls whether the scanner capability is available.

## Strategy configuration
Admin can configure role, weight, preferred R:R, stop ATR multiplier and TP1/TP2/TP3 R levels through normal controls. Advanced JSON is retained only as an optional developer section. Three new executable evaluators are added: Trend + Volume Confluence, MACD + RSI Confluence, and Breakout Momentum.

## Theme
A persisted Dark/Light theme toggle is added to Admin, Pulse member UI and public application layout. Dark remains the default.

## Compatibility
No existing route is removed. Existing mobile response fields are preserved. Database changes are additive and preserve all customer, payment, investor, trade, signal and historical strategy data.
