# ABS V14.9.0 Validation Report

Validation date: 2026-08-29
Baseline: ABS V14.8.22 Protected Database Self-Repair & Shared-Hosting Recovery Build

## Passed

- 183 PHP source files linted with PHP 8.4: no syntax errors.
- `git diff --check`: no whitespace/error markers.
- V14.9.0 build/version assertion passed.
- HostGator/shared cron configured for one-minute scheduler cadence.
- Central price target configured to 60 seconds.
- Old shared-hosting 15-minute market-data schedule removed.
- Central market feed health status and stale/offline scanner guard present.
- Public/core market presentation prefers ABS centralized Binance Futures data.
- Admin Market Data & Cron Health controller/view/routes present.
- User deactivate, soft-delete, restore and guarded permanent-delete routes/actions present.
- User soft-delete migration and Eloquent SoftDeletes support present.
- Guided Trading Setup readiness flow present.
- V14.8.22 protected recovery documentation retained.
- Core live execution service `PulseTradeService.php` unchanged from V14.8.22 baseline.
- Core Binance trading transport `BinanceFuturesService.php` unchanged from V14.8.22 baseline.
- Core schema recovery support `AbsSchemaRepair.php` unchanged from V14.8.22 baseline.

## Runtime-test limitation

The uploaded V14.8.22 distribution does not include Composer `vendor/` dependencies. The validation environment contains PHP but no Composer binary and outbound network/DNS access is unavailable, so Laravel could not be booted and PHPUnit/Artisan route/view/runtime tests could not be executed in this environment. This build must therefore be deployed through a staging/restore-point workflow first, with the live `.env` preserved, then `php artisan abs:production-check` and the Admin Market Data health screen should be verified before production traffic/trading is allowed to use the update.

## Live deployment requirement

Create a HostGator file/database backup or ABS restore point first. Do not overwrite the production `.env`. Verify the one-minute cron remains configured. After migration/repair, confirm Admin > Market Data & Cron Health reports HEALTHY and fresh prices before using scanner/live execution.
