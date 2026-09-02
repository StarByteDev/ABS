# ABS V14.9.0 — Premium Trading Workflow & Central Market Architecture

Built directly from ABS V14.8.22 Protected Database Self-Repair & Shared-Hosting Recovery Build.

## Production-safe changes

- HostGator/shared scheduler now honors the confirmed once-per-minute cron for central Binance Futures market ingestion.
- ABS market price target is 60 seconds; shared-hosting no longer silently forces a 15-minute price interval.
- Scanner blocks new decisions when the centralized ABS market feed is stale/offline.
- Public/core market presentation prefers the centralized ABS Futures database snapshot, with public fallback only for website continuity.
- Added Admin > Market Data & Cron Health with feed status, price age, symbol count, recent prices, sync history and manual refresh.
- Pulse navigation consolidated into one Trading Setup destination for mode, Binance, risk and market selection while retaining advanced controls.
- Trading Setup includes readiness steps and visible ABS Market Feed health.
- Added safe admin account lifecycle: deactivate, soft delete, restore, and guarded permanent delete.
- Soft deletion preserves trading, signal and audit history. Permanent deletion is blocked while active/pending trades exist.
- Existing V14.8.22 exchange-confirmed order, fill, TP/SL protection, close/reconciliation, mobile API and recovery behavior retained.
- Existing enterprise CMS, plans, memberships, news, research, learning, economic events, products/services, website/mobile settings, communications and backups remain intact.

## Deployment notes

1. Back up current live files and MySQL database before replacing files.
2. Preserve the live `.env`; do not overwrite it with `.env.example`.
3. Ensure the HostGator cron invokes Laravel scheduler once per minute.
4. Recommended values: `PULSE_SCHEDULER_PROFILE=hostgator_shared`, `PULSE_CRON_MINUTES=1`, `PULSE_MARKET_PRICE_REFRESH_SECONDS=60`, `PULSE_MARKET_READ_MAX_AGE_SECONDS=300`.
5. Run the normal migration/repair path. The new migration adds `users.deleted_at` non-destructively.
6. Verify Admin > Market Data & Cron Health shows HEALTHY and recent price age near the one-minute target before enabling/using live scanner execution.

## Validation boundary

This source package does not include Composer `vendor/`, matching the uploaded V14.8.22 baseline. The validation environment had PHP available but no Composer binary/network access, so full Laravel boot/PHPUnit execution could not be performed here. Static PHP lint, build integrity, scheduler/config assertions and source-level workflow checks were performed before packaging.
