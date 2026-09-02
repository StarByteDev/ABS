# ABS V14.8.3 Mobile API Additions

V14.8.3 preserves the complete V14.8.2 Pulse mobile contract and brings the six web UX corrections to mobile clients.

## Daily plan usage

`GET /api/v1/pulse/usage` returns the signed-in user's current plan, scanner runs used/remaining, signals generated/remaining, unlimited flags and the daily reset time. Failed scanner runs do not consume scanner quota.

Scanner overview/run responses and the Pulse dashboard also expose current usage so Flutter can refresh counters without reloading the whole screen.

## Pair-selection cooldown

Pulse Settings responses include `pair_selection_lock` with `locked`, `saved_at`, `locked_until`, `remaining_minutes`, `remaining_human` and `lock_hours`. A changed pair selection starts a 50-hour lock by default. Attempts to change the pair set before expiry return HTTP `423`; settings updates that do not alter the selected pair set remain allowed.

## Scanner behavior

`POST /api/v1/pulse/scanner/run` returns JSON usage with the scan result. Quota failures return a normal API error payload rather than consuming a run. Mobile clients should update scanner/signals allowance from the returned usage object.

## Compatibility

Existing authentication, market catalog, 15 package-controlled strategies, Testnet/Live Binance Futures connections, execution readiness, risk controls, trades, positions, alerts, reports and admin plan/pair APIs remain compatible.

V14.8.3 OpenAPI parity: **107 routed operations**.
