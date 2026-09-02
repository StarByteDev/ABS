# ABS V14.8.12 — Simple Signal Phase Workflow

Pulse Signals now uses one consistent four-phase lifecycle. A signal created by a fresh scan always begins at **Opportunity Spotted**. A later scan refresh is required before it can advance based on the refreshed price context.

| Phase | Meaning | User action |
|---|---|---|
| Opportunity Spotted | A qualified signal exists, but price is not yet in the planned entry zone. | Monitor Signal |
| Entry Ready | Current scan price is inside the planned entry zone. | Open Trade |
| Trade In Progress | The user already has an active Pulse trade/order, or price has moved beyond the entry zone in the signal direction. | Monitor Trade |
| Expired | The signal is inactive/time-expired or the current scan price has reached/passed the planned TP/SL boundary. | View Signal |

## Execution

Only **Entry Ready** can open the compact execution drawer. The drawer is a single review surface, not a wizard. It shows pair/direction, score, current price, entry, SL, TP, qualified strategies, environment, leverage, configured trade size, estimated maximum SL loss, potential reward and R:R. The user acknowledges the levels and presses **Open Trade**.

PulseTradeService remains authoritative for account permission, Binance connection, environment permission, emergency-stop, leverage, exchange minimums, quantity/notional, account equity, available margin, risk-per-trade and protective TP/SL validation.

## Monitoring

**Monitor Signal** keeps the user on the premium Signals screen and focuses the selected-signal monitor. **Monitor Trade** opens the user's existing Pulse trade record when one exists; otherwise it keeps the user on the selected signal so a late market move is monitored rather than chased.

The standalone signal-analysis page no longer contains a second execution form. It is analysis/monitoring only.

## Scanner consistency

Scanner Results uses the same shared phase metadata as Pulse Signals. A fresh signal starts as Opportunity Spotted. Existing refreshed signals can advance to Entry Ready, Trade In Progress or Expired. If an Entry Ready action is opened from Scanner Results, Pulse navigates to Signals and automatically opens the compact Open Trade panel once.
