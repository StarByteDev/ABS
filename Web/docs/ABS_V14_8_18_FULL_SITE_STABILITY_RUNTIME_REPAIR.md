# ABS V14.8.18 — Full-Site Stability, Dashboard Runtime & API Compatibility

## Reported runtime issue

`GET /pulse/dashboard` could fail with HTTP 500 because `PulsePageDataService::dashboard()` returned `$scannerCapabilities` and `$scannerConnectionReady` without initializing them on the dashboard code path.

V14.8.18 initializes both values immediately after resolving the user's Pulse settings/access/Binance connection, before the dashboard payload is assembled.

## Stability hardening

The V14.8.17 central price/signal-intelligence feature set adds new tables. V14.8.18 makes read/report surfaces tolerant while a deployment is waiting for `abs:repair`:

- central market-data health returns `schema_ready=false`, missing tables and recovery guidance;
- central price/candle reads return safe empty results rather than raw SQL exceptions;
- Pulse Reports keeps trade/P&L reporting available and shows an intelligence-setup notice;
- Admin → Pulse → Signal Intelligence shows an actionable setup state;
- mobile signal validation/performance/learning reads return controlled empty/meta responses while schema repair is pending.

The central ingestion command still refuses to run without its required schema and gives the exact recovery command. This avoids hiding a deployment problem while preventing normal read pages from crashing.

## Mobile/API compatibility

`GET /api/v1/pulse/reports` historically exposed trade totals in `data.summary`. V14.8.17 renamed that block to `data.trading`. V14.8.18 returns **both** names with the same trading payload, so existing mobile clients remain compatible while new clients can use the clearer `trading` key.

## Learning/version integrity

This release does not change signal scoring/strategy logic. `PulseScannerService` intentionally continues to freeze strategy bundle identity as `engine-14.8.17-*` so V14.8.18 stability changes do not split learning evidence into a false strategy version.

## Deployment

No new V14.8.18 database migration is added. Existing V14.8.17 architecture tables/columns are still required:

```bash
php artisan abs:repair --seed
php artisan optimize:clear
php artisan abs:doctor
```

Then run/verify the scheduler and central feed:

```bash
php artisan abs:pulse-market-data
php artisan abs:pulse-validate-signals
php artisan schedule:run
```

On shared hosting, configure one cron entry that invokes `php artisan schedule:run` every minute.
