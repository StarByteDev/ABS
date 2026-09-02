# ABS V14.8.15 — Live Price-Driven Trade Actions

## Purpose
V14.8.15 removes the static “New Signal” fallback and derives signal action state from the current Binance Futures ticker price.

## Scanner columns
Pair · 24H · Direction · Score · Entry Price · Stop Loss · Take Profit · Timeframe · Action

Risk / Reward is intentionally omitted from Scanner Results to keep the table focused on execution levels.

## Live stage logic
- **Entry Ready** — current price is inside the configured entry zone around the signal entry.
- **Move in Progress** — price has moved beyond the entry zone in the signal direction but has not reached TP/SL.
- **Entry Watch** — price has moved away from the entry against the signal but the signal is still actionable.
- **Trade Open** — the current user already has an active Pulse/Binance order or position for the signal.
- **Signal Closed** — the signal expired, became inactive, or current price reached the signal TP/SL boundary.

## Action behavior
Every saved scanner signal remains clickable. Active signal stages use **Open Trade** and open the left-side Trade Execution panel. The panel uses the live stage to provide a professional Binance execution recommendation:

- Entry Ready: protected LIMIT at the planned signal entry.
- Move in Progress: keep the original LIMIT entry and wait for pullback; avoid chasing current price.
- Entry Watch: review the live price/risk; planned LIMIT entry remains available if the user confirms.
- Trade Open: manage the existing trade; no duplicate entry.
- Signal Closed: review only; fresh entry is not recommended and submission stays unavailable.

## Live refresh
The scanner refreshes its rendered action state every 20 seconds through the existing scanner refresh endpoint. This refresh does not rerun strategies and does not consume scanner quota.

## Safety
PulseTradeService remains authoritative for execution. It revalidates signal actionability, environment permissions, manual-trading access, emergency stops, pair access, leverage, quantity, exchange minimums, account balance, margin, configured risk and TP/SL direction before Binance submission.
