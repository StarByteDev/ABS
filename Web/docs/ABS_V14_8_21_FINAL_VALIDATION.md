# ABS V14.8.21 — Final Validation

**Build:** ABS V14.8.21 — Full Application QA & HostGator Shared Scheduler Build  
**Release date:** 2026-08-29

## Release validation completed

- V14.8.21 full QA + HostGator scheduler contract: **23/23 PASS**.
- PHP syntax: **180 PHP files PASS** across application, bootstrap, config, database, routes and tests.
- JavaScript/MJS syntax: **33 files PASS**.
- Static full-site audit: **PASS**.
  - Blade files inspected: **86**.
  - Named routes discovered: **158**.
  - Named route references checked: **150**.
  - Mobile/API operations matched to OpenAPI: **113**.
  - Static controller view targets checked: **70**.
  - Blade template references checked: **182**.
- Premium visual contract: **245 checks PASS** for Dashboard, Market Scanner, Signals and Strategies.
- Migration audit: **17 migration files**, **0 duplicate Schema::create table definitions**.
- Literal DB/schema table-reference audit: **52 referenced tables**, all **52 have a packaged creation definition**.
- OpenAPI YAML parses as OpenAPI **3.1.0**, release **14.8.21**, with **100 documented paths**.
- V14.8.20 behavior regression contract: all **20 non-version behavior checks PASS**; its 2 historical metadata pins correctly report the superseded 14.8.20 version as changed.

## Runtime acceptance packaged for the deployment server

`php artisan abs:production-check` now runs the environment/database doctor, Blade/named-route runtime compilation audit, scheduler profile check, public website market connectivity, Binance Futures public connectivity, central Pulse market-data ingestion and signal validation. It does not submit a trade. SMTP is tested only when `--email=` is explicitly provided.

## HostGator Shared/Baby correction

The HostGator profile is intentionally designed for a **15-minute host cron**, rather than a one-minute cron. Central current-price snapshots therefore follow the hosting-tier cadence, while the signal validator downloads the missed closed 1-minute candles on the next cycle and processes them chronologically so Entry/TP/SL validation retains the approved 1-minute candle logic.

## Limit of this release-workspace validation

The source ZIP intentionally does not include Composer `vendor/` or a configured production MySQL database. The release workspace therefore cannot boot Laravel end-to-end here. Environment/database/network runtime acceptance must be completed after deployment with the packaged `abs:doctor`, `abs:scheduler-check`, `abs:view-audit` and `abs:production-check` commands. This limitation is explicit; no claim is made that HostGator-specific credentials, MySQL contents, SMTP, or Binance network access were executed in this offline release workspace.

## Database

No new V14.8.21 migration is required. Existing installations should run:

```bash
php artisan abs:repair --seed
php artisan optimize:clear
```
