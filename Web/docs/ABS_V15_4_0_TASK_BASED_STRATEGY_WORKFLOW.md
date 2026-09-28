# ABS V15.4.0 — Task-Based Strategy Workflow

V15.4.0 replaces the long all-in-one Strategy Lab page with a simple Admin-only workflow. The menu itself follows the validation lifecycle:

1. Price Source & Schedule
2. Latest Market Prices
3. Price Sync History
4. Scan & Signals
5. Paper Trades
6. Trade Results
7. Strategy Performance
8. Audit & History

`Strategy Overview` is the CEO-level landing page. It provides only the high-level engine state, paper win rate, Net R, strongest current strategy, latest cycle, and the status of all eight workflow stages.

## Market-price audit

Every V15.4.0 central market-data run stores an exact per-market price snapshot in the existing `pulse_market_data_runs.summary` JSON value. This enables run-by-run price-history drill-down without introducing another database migration.

## Branding

The Admin application now loads `admin-workflow-v1540.css`, which applies the established ABS Pulse dark navy / gold / controlled cyan design language to the workflow and overrides legacy light strategy/editor surfaces in the Admin area.

## Safety

The workflow remains paper/research validation. V15.4.0 does not enable real Binance automatic trading. Existing real exchange execution records remain separate from research validation.
