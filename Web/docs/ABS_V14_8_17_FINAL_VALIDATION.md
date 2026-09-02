# ABS V14.8.17 — Final Release Validation

Release: **ABS V14.8.17 — Central Price, Signal Intelligence, Reporting & Mobile API Build**

## Architecture contract

- V14.8.17 central-price/signal-intelligence contract: **66/66 PASS**.
- Scanner reads centrally stored prices and closed 15M/4H candles; no per-user Binance public ticker/kline calls remain in scanner/page-data services.
- Future 1M validation enforces entry-before-TP/SL, excludes the entry minute from outcome ordering, and records post-entry same-minute TP+SL as ambiguous.
- Signal snapshot/version/levels/scores are frozen at generation.
- Daily strategy learning is fingerprint-deduplicated and evidence protected.

## Static release audit

- PHP syntax: **PASS — 178 files**.
- JavaScript syntax: **PASS** for ABS/Pulse direct frontend bundles used by this release.
- Blade files inspected: **86**.
- Named routes discovered: **158**.
- Named route references checked: **151**.
- Mobile/API operations matched to OpenAPI: **113**.
- Static release audit: **PASS**.
- OpenAPI YAML parse: **PASS**, version **14.8.17**, **100 paths**.

## Reporting delivered

- User Pulse Reports: signal validation summary, entry rate, decisive win rate, ambiguity, MFE/MAE, strategy/version/timeframe/direction performance, learning evidence/state, recent detailed validation and central feed health.
- Admin Pulse Signal Intelligence: platform-level signal quality deduplicated by fingerprint, strategy performance, learning evidence, recent validation and central market-data run health.
- Mobile API: central prices/feed health, signal validation, signal performance, strategy performance and learning insights.

## Deployment requirement

V14.8.17 adds database structures and scheduled processing. After upload:

```bash
php artisan abs:repair --seed
php artisan config:clear
php artisan cache:clear
php artisan abs:pulse-market-data
php artisan abs:pulse-validate-signals
```

Configure hosting cron to run `php artisan schedule:run` **once per minute**. The exact PHP CLI path depends on the hosting account.

## Validation scope note

The release package intentionally does not include `vendor/`; therefore environment-specific database migrations, live Binance connectivity and Laravel runtime acceptance must be executed on the target installation after Composer dependencies and production `.env` are available. The package-level PHP/JS/routes/OpenAPI/architecture contract and release integrity checks are performed before distribution.
