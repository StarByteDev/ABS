# ABS V14.8.18 — Final Source & Release Validation

Release: **ABS V14.8.18 — Full-Site Stability, Dashboard Runtime & API Compatibility Build**

## Reported dashboard failure

The reported `/pulse/dashboard` 500 was traced to `PulsePageDataService::dashboard()`: the returned payload referenced `$scannerCapabilities` before initialization. The same payload also referenced `$scannerConnectionReady` without initialization, which would have caused a second failure after the first variable was fixed.

Both values are now initialized before the dashboard payload is built:

- scanner capabilities from the current user/package configuration;
- Binance connection readiness from the resolved Pulse connection.

## Broader stability work

- Central market-data reads now handle an unreconciled V14.8.17 intelligence schema with a controlled readiness state instead of avoidable raw table-query failures.
- User Pulse Reports and Admin Signal Intelligence guard architecture-specific tables and can render controlled empty/setup states while repair is pending.
- Mobile signal validation/performance/strategy/learning APIs expose schema readiness rather than returning avoidable architecture-table 500 errors.
- `GET /api/v1/pulse/reports` preserves the pre-existing `data.summary` object and also exposes `data.trading`, protecting existing mobile clients.
- V14.8.17 strategy-engine identity remains unchanged so the V14.8.18 runtime/stability release does not split learning evidence into a false strategy version.
- Main README cumulative version history was restored.

## Validation completed in the release workspace

- PHP syntax: **171 files passed** across `app`, `bootstrap`, `config`, `database`, and `routes`.
- Blade files inspected by static release audit: **86**.
- Named routes discovered: **158**.
- Named route references checked: **151**.
- Mobile/API operations matched to OpenAPI: **113**.
- Static controller `view(...)` targets checked: **71 references, 0 missing**.
- Blade `@extends` / static include references checked: **198 references, 0 missing**.
- V14.8.18 stability + V14.8.17 architecture-preservation contract: **91/91 checks passed**.
- JavaScript/MJS syntax: **24 files passed**.
- OpenAPI YAML parsed successfully: version **14.8.18**, **100 paths**.

## Runtime boundary

The source release intentionally does not package `vendor/`. Because Composer dependencies and a configured runtime database are not present in the release workspace, this validation does not claim a live Laravel/database/browser integration test. After deployment, use the packaged ABS diagnostics below against the actual environment.

## Required deployment / runtime verification

Back up the database and `.env`, deploy the build, then run:

```bash
php artisan abs:repair --seed
php artisan optimize:clear
php artisan abs:doctor
php artisan abs:production-check
php artisan abs:pulse-market-data
```

Then configure one cron job to run Laravel `schedule:run` once per minute. This is required for the V14.8.17 central price / validation architecture retained by V14.8.18.

No new V14.8.18 migration is required. `abs:repair --seed` reconciles the V14.8.17 central-price/signal-intelligence schema on upgraded installations.
