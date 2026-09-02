# ABS V14.9.2 Validation Report

Release: **ABS V14.9.2 — Mobile/Web Backend Parity & Admin Event Notifications Build**

Baseline: **ABS V14.9.1 — User Lifecycle Schema Repair Hotfix**

## Verified in build environment

- Full PHP syntax lint across application/config/database/routes/tests PHP files: PASS.
- Static Laravel release audit for named routes, Blade view targets and OpenAPI/API-operation coverage: PASS.
- V14.9.2 mobile/web backend release contract: PASS.
- Website registration -> administrator event notification wiring: PASS.
- Mobile API registration -> administrator event notification wiring: PASS.
- Website package subscription -> administrator event notification wiring: PASS.
- Mobile API package subscription -> administrator event notification wiring: PASS.
- Admin Email Communications recipient + independent alert switches: PASS.
- Default admin recipient `i@armansabir.com` packaged without exposing it through public mobile bootstrap: PASS.
- V14.9.1 `users.deleted_at` protected repair coverage retained: PASS.
- Protected browser recovery continues to call non-destructive `abs:repair` without `--fresh` or `--seed`: PASS.
- Recovery key constant-time comparison retained: PASS.
- HostGator/shared central market target remains 60 seconds / 1-minute cron capable: PASS.
- Mobile API contract includes authentication, account, content, market, packages, scanner, signals, Binance connection, execution readiness, positions/orders/trades, reports, alerts, devices/notifications and Private Member endpoints: PASS.
- `GET /api/v1/bootstrap` identifies V14.9.2 and declares mobile readiness / ABS central market source: PASS.
- phpMyAdmin SQL contains `CREATE TABLE IF NOT EXISTS` and conditional missing-column repair: PASS.
- phpMyAdmin SQL contains no `DROP TABLE`, `TRUNCATE`, `DELETE FROM`, `UPDATE`, or existing-column `MODIFY/CHANGE/DROP`: PASS.
- phpMyAdmin SQL does not overwrite the configured administrator notification settings: PASS.

## Runtime limitation

The supplied ABS release baseline does not contain Composer `vendor/` dependencies, and Composer/network package retrieval is not available in this build environment. Therefore Laravel could not be booted here for PHPUnit, real MySQL migration execution, SMTP delivery, or live Binance/Testnet calls.

For a live deployment, keep the existing production `.env`, make a database/files restore point, deploy V14.9.2, run normal migrations when Terminal is available (or use the included safe phpMyAdmin repair SQL / protected recovery flow), then verify Admin -> Email Communications and Admin -> Market Data & Cron Health before enabling normal trading use.
