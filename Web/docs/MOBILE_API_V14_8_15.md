# ABS Mobile API V14.8.15

No endpoint removals or breaking request changes are introduced in V14.8.15.

`GET /api/v1/pulse/scanner/overview` and `GET /api/v1/pulse/signals/overview` now expose scanner/signal rows whose `last_price` and `trade_action` are enriched from a fresh Binance Futures ticker snapshot when available.

`trade_action.phase` may be:
- `Entry Ready`
- `Move in Progress`
- `Entry Watch`
- `Trade Open`
- `Signal Closed`

For active signals, `trade_action.label` is `Open Trade`. Existing trades return `Manage Trade`; closed signals return `View Signal`.

Execution continues through the existing protected signal execution endpoint and existing server-side PulseTradeService validation.
