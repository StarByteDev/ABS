# ABS Mobile API V14.8.18 — Stability & Compatibility Notes

V14.8.18 keeps every V14.8.17 mobile endpoint and the central-market-data rule: mobile clients call ABS only and never trigger per-user Binance public market-data requests.

## Compatibility correction

`GET /api/v1/pulse/reports` now returns both:

- `data.summary` — legacy trading summary retained for existing mobile clients;
- `data.trading` — equivalent clearer alias for new clients;
- `data.signal_intelligence` — V14.8.17 signal validation summary.

No existing route is removed.

## Schema-readiness behavior

While the V14.8.17 architecture schema is not yet reconciled:

- `/pulse/market-data/health` returns `schema_ready: false`, `missing_tables` and recovery guidance;
- `/pulse/market-data/prices` returns an empty central snapshot rather than a database exception;
- `/pulse/signals/{signal}/validation` returns `data: null` with `meta.schema_ready: false`;
- signal/strategy/learning report endpoints return controlled empty datasets with schema-readiness metadata.

After `php artisan abs:repair --seed` and the first scheduled central market-data run, these endpoints populate normally.

## Build

API build metadata: `14.8.18`.
Strategy/scoring engine identity remains `engine-14.8.17-*` because V14.8.18 is a runtime/compatibility repair and does not alter strategy logic.
