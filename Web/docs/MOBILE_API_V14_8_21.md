# Mobile API — ABS V14.8.21

The `/api/v1` contract remains backward compatible with V14.8.20. App bootstrap metadata now reports build `14.8.21`.

Mobile clients continue to consume ABS central market-data, signal validation, reports and execution-readiness endpoints rather than triggering Binance public market-data fetches themselves.

`GET /api/v1/pulse/market-data/health` now also reflects the configured scheduler profile/cadence and profile-aware market freshness metadata supplied by `PulseMarketDataService::health()`.
