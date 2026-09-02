# ABS V14.8.10 — Signal Execution Workflow

## Signal queue

The Pulse Signals queue is now decision-oriented rather than status-oriented. The table removes Expires and Status and adds a single Action column. All strategies that positively support the final LONG/SHORT direction are displayed for the signal.

## Action states

- **Execute Limit** — Binance execution prerequisites are ready and the latest scan price is inside the planned entry zone.
- **Stage Limit Entry** — execution prerequisites are ready but price is outside the planned entry zone; the protected LIMIT entry can wait at the signal entry price.
- **Review Setup** — the signal is active, but Binance connection, Manual execution mode, plan/environment permission, or platform safeguards require review.
- **Review Signal** — the record is historical or no longer actionable.

Ready actions post through the existing Pulse signal execution route. The order type is LIMIT, time-in-force is GTC, and the signal's entry, stop loss and take profit are supplied with the user's saved environment, leverage, sizing and position-mode settings. PulseTradeService remains the final authority for account/risk validation and Binance submission.

For a LIMIT entry that fills immediately, TP/SL protection is attached immediately. For a pending LIMIT entry, the existing scheduled `abs:pulse-sync` reconciliation attaches exchange-side TP/SL protection after Binance confirms the fill. If protection cannot be confirmed, the existing emergency-close safeguards remain in force.

## Relative scan time

The Signal page uses the latest completed scanner run, not the signal's original creation timestamp, and updates the relative-time label in the browser every 30 seconds without requiring a page refresh.
