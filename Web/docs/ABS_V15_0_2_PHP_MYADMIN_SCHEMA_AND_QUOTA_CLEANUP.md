# ABS V15.0.2 — phpMyAdmin Schema Repair & Legacy Quota Cleanup

ABS V15.0.2 completes the move to Pulse Points by removing the old daily scanner-run and generated-signal allowance columns from active use.

## Normal missing-schema repair

Use `ABS_V15_0_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql` when V15 PP/gamification tables or columns are missing and Laravel migrations/Terminal are not available. The same file is also stored under `database/`.

This repair is create/add-only for ABS business structures and does **not** recreate `pulse_plans.scanner_runs_per_day` or `pulse_plans.signals_per_day`.

## One-time V15.0.1 → V15.0.2 quota cleanup

Use `ABS_V15_0_2_REMOVE_LEGACY_SCAN_SIGNAL_QUOTAS.sql` once in phpMyAdmin if the production database was previously running V14/V15.0.1 and still has the two obsolete columns.

The cleanup checks `information_schema` first and conditionally runs only:

- `ALTER TABLE pulse_plans DROP COLUMN scanner_runs_per_day`
- `ALTER TABLE pulse_plans DROP COLUMN signals_per_day`

It does not drop tables, truncate tables, delete rows, reset PP balances, change plan PP prices, or modify signal/trade history.

If Laravel Terminal/migrations are available, the migration `2026_09_04_000200_remove_legacy_scan_signal_daily_quotas.php` performs the same normal upgrade cleanup and the standalone phpMyAdmin cleanup file is not required.

Always back up the production database and preserve the production `.env` before an upgrade.
