# ABS V15.6.2 — Live Patch & Data-Safe Rollback

## Objective

Provide a production-safe way to update ABS Pulse from the Admin panel while preserving the live database and keeping only one immediately previous application build for rollback.

## Admin flow

`Reporting & System -> Updates & Recovery`

1. **Back Up Current Build** — stores the currently deployed ABS application files in protected Laravel storage.
2. **Upload New Patch** — validates the complete ABS ZIP before deployment.
3. **Install Patch** — automatically refreshes the previous-build snapshot, validates the staged application PHP before touching live files, enters maintenance mode when supported, deploys managed application files, runs non-destructive schema repair only when needed, validates pending migrations, runs safe migrations, and clears caches.
4. **Restore Previous Build** — requires `ABS_RECOVERY_KEY` and restores application files only.

## One-build retention

Application rollback storage intentionally keeps one checkpoint only. Creating a new checkpoint removes older application checkpoints after the new archive has been successfully finalized.

## Live database protection

The release service does not package or restore the MySQL database as part of application rollback. It also does not run `db:seed` during patch installation.

Before pending migrations execute, ABS scans them for destructive/data-replacing patterns. The Admin patch workflow blocks pending migrations containing operations such as table/column drops, renames, truncation, row deletion, or raw destructive SQL.

Safe missing-schema repair and additive migrations remain supported so future releases can add required tables or columns without clearing existing records.

## Protected runtime state

Patch ZIPs are rejected if they attempt to include or replace:

- `.env`
- `storage/`
- `vendor/`
- `node_modules/`
- `.git/`

`public/storage` and Laravel runtime cache files are also excluded from file synchronization.

## Emergency recovery

`/api/recovery/build` remains available through the recovery path exemption when the normal Admin interface cannot be used. It uses the same `ABS_RECOVERY_KEY` as database recovery and restores the single previous-build snapshot without touching the database.

## Audit

Release staging, backup, install, automatic rollback and manual restore events are written to protected `storage/app/abs-release-audit.jsonl`. Current release state is stored under `storage/app/abs-release-state.json`. No new database table is required.

## Compatibility

- No V15.6.2 database migration.
- Existing V15.6.1 Market Intelligence and economic calendar retained.
- Existing V15.6.0 Best Signal progress retained.
- Existing V15.5.0 multi-signal strategy-validation workflow retained.
- `PulseScannerService.php` unchanged.
- ABS production master logo unchanged.

## Pre-deployment PHP validation

Before live files are synchronized, ABS parses PHP files under app, bootstrap, config, database/migrations and routes with the running PHP engine using `TOKEN_PARSE`. A syntax error blocks the patch before production files are replaced.

## Downgrade protection

The Admin patch installer rejects a package whose semantic version is older than the currently deployed build. Controlled rollback is performed only through the single Previous Build snapshot so the rollback path remains auditable and data-safe.
