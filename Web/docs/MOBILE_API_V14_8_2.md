# ABS V14.8.2 Mobile API Contract

Base path: `/api/v1`
Authentication: Laravel Sanctum bearer token.

The authoritative operation list and request/response contract is `docs/openapi.yaml`. Pulse functional endpoints require an active Pulse membership and the plan's `mobile_api` capability. Individual endpoints additionally enforce Scanner, Signals, Orders, Trades, Reports, Binance, Alerts, Settings and Trading permissions as applicable.

## Mobile parity areas

- Account: bootstrap, auth, sessions, profile, notifications, device registration, watchlist.
- Membership: Pulse access, plans, current membership, quote, submit/cancel request.
- Pulse Dashboard.
- Markets: plan-aware Binance USD-M perpetual pair catalog; selected markets are validated against the user's plan.
- Strategies: complete 15-engine catalog with package inclusion/lock status and screen overview.
- Scanner: history, screen overview, run scanner.
- Signals: list, screen overview, detail, dismiss, execute.
- Execution: readiness and ticket calculation.
- Trading: open positions, exchange orders, trade history/detail, close, sync, emergency stop.
- Risk/Settings: read/update risk controls and Pulse configuration.
- Performance: reports and P&L.
- Binance: list/save encrypted connections, test permissions, activate Testnet or Live, delete.
- Alerts: list, read one, read all.

## Safety

Live USD-M Futures orders are never enabled solely by an API request. System, plan, connection, environment and risk gates are enforced server-side for web and mobile clients equally.
