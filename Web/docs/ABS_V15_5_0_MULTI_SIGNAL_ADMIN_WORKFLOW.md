# ABS V15.5.0 — Multi-Signal Strategy Validation & Admin Workflow

## Clear Admin flow
The strategy area is deliberately separated into short task pages:

- **Strategy Overview** — high-level engine health and validation status.
- **Price Source & Schedule** — choose Production Cron, ABS Internal Scheduler, or Manual Only and set the supported cadence.
- **Latest Market Prices** — confirm current prices and freshness.
- **Price Sync History** — review previous sync runs and stored market snapshots.
- **Scan & Signals** — review each scanner run and every qualified signal from that run.
- **Paper Trades** — only signals whose entry was subsequently observed.
- **Trade Results** — TP, SL, ambiguous/expired outcomes, Win Rate, Net R and model return.
- **Strategy Performance** — strategy-by-strategy win/loss, Net R, model return and reliability evidence.
- **Audit & History** — detailed trace only when investigation is required.

## One scan can create several research signals
The system scanner ranks all qualified candidates. For Admin/system research, each qualified market/timeframe is persisted or linked to an equivalent unresolved setup. The highest-ranked signal remains the run's `best_signal_id` for backward compatibility. Member scans keep the existing one-Best-Signal user experience. Pre-V15.5 scans remain historical: if several candidates qualified but only the Best Signal was persisted at the time, the missing historical signal records are not fabricated.

## Paper-trade timing
A full research cycle runs in this order:

1. Synchronize current market data.
2. Reconcile signals/paper trades that existed before the cycle.
3. Run the strategy scanner and create/link the newly qualified signals.

This means a signal created in step 3 is first eligible for entry observation during the next synchronized cycle. Entry is recorded when a later 1-minute candle range includes the configured entry price. TP and SL are evaluated only after the entry candle.

## Duplicate protection
Fast local/VPS cadences can evaluate the same 15M/4H setup repeatedly. Equivalent unresolved system signals with the same symbol, timeframe, direction, entry, stop and target are reused instead of creating duplicate evidence.

## Members & Access
The first Admin category uses the same task-oriented structure: Members Overview, Member Accounts, Packages, Access & Renewals, and Payments & Approvals.

## Brand lock
The final Admin layer uses dark navy / near-black backgrounds, ABS gold as the main accent, controlled cyan for active/navigation states, and green/red only for positive/negative results. Legacy light package cards are normalized by the final V15.5 stylesheet.

## Compatibility
No schema migration is required. The 15 strategy calculation formulas are unchanged. Real Binance automatic execution remains separate from paper/research validation.
