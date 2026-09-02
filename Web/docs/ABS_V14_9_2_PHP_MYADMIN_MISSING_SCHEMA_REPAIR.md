# ABS V14.9.2 — phpMyAdmin Missing Tables / Columns Repair

File:

`database/ABS_V14_9_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql`

## Purpose

Use this file when an existing ABS MySQL database is missing tables or columns and Terminal/SSH is unavailable.

## Safe behavior

The script:

- uses `CREATE TABLE IF NOT EXISTS` for missing ABS/Pulse tables;
- checks `INFORMATION_SCHEMA` before each missing-column repair;
- uses conditional `ALTER TABLE ... ADD COLUMN` only when a column does not exist;
- includes the V14.9.1 `users.deleted_at` repair;
- creates the V14.9.2 administrator notification defaults only if the setting keys do not already exist;
- does not drop tables;
- does not truncate tables;
- does not delete rows;
- does not overwrite existing administrator notification settings;
- does not reseed plans/users/trades or Binance credentials.

## phpMyAdmin use

1. Back up the live ABS database.
2. Open phpMyAdmin and select the existing ABS database.
3. Open **Import**.
4. Choose `ABS_V14_9_2_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql`.
5. Import it once.
6. At the end, check that `missing_required_columns` is `0`.
7. Clear Laravel caches when Terminal is available, or use the protected ABS recovery workflow when it is not.

This file is for non-destructive schema repair. Do not use a fresh/drop workflow on the live ABS database.
