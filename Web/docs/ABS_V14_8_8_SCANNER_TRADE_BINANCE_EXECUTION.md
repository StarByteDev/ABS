# ABS V14.8.8 — Scanner Trade Action & Binance Protected Execution

V14.8.8 keeps Market Scanner as the discovery/evaluation surface while restoring a deliberate path from a qualified generated signal into protected Binance Futures execution.

## Scanner UX

- Minimum Score remains bound to the effective user/package threshold, but the redundant `Profile threshold` helper text is removed.
- Scanner Results includes an Action column. It never displays the old `No Signal` action.
- A generated, still-actionable signal can open `Trade Execution` directly.
- The action reflects account state: Trade, Connect Binance, Enable Trading, or Upgrade.

## Protected trade ticket

The existing Trade Execution page is reused rather than bypassed. The user reviews a pre-filled order ticket containing order type, entry/limit price, quantity, leverage, time-in-force, stop loss, take profit and client reference before submission.

The submission path enforces active Pulse access, plan manual-trading permission, saved Manual execution mode, selected Testnet/Live environment permission, verified Binance trading permission, package pair access, leverage/risk limits, margin availability, open-position/daily limits and emergency-stop controls.

For filled entries Pulse places Binance exchange-side TAKE_PROFIT_MARKET and STOP_MARKET protection. Protection failure is treated as a safety incident and triggers the existing emergency-close path. Pending LIMIT orders are reconciled by `abs:pulse-sync`, now scheduled every minute when the Laravel scheduler is configured.

## Environments

The same execution service supports both environments. Testnet uses `PULSE_BINANCE_TESTNET_URL` (default `https://demo-fapi.binance.com`) and Live uses `PULSE_BINANCE_LIVE_URL` (default `https://fapi.binance.com`). Live remains gated by installation, admin, plan and verified-connection controls.

## Mobile API

No new route is required. Mobile continues to use the existing execution-ticket and execute-signal APIs. Execution validation failures now return HTTP 422 with a readable error instead of an unhandled server error.

No database migration is required.
