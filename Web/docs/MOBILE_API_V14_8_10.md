# Mobile API Notes — ABS V14.8.10

V14.8.10 does not add or remove routed API operations. `GET /api/v1/pulse/signals/overview` now exposes the same enriched signal view-model used by web: qualifying strategy names, professional trade-action state, and `last_scan_at`.

Existing `POST /api/v1/pulse/signals/{signal}/execute` remains the protected Testnet/Live Binance Futures execution endpoint and supports LIMIT entries with stop loss and take profit.
