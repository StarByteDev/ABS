# ABS V15.0.1 — phpMyAdmin Missing Schema Repair

For HostGator/shared hosting without Terminal, prefer the protected **Fix Missing Tables & Columns** browser flow using the private `ABS_RECOVERY_KEY`.

Fallback: import `ABS_V15_0_1_CREATE_MISSING_TABLES_OR_COLUMNS_ONLY.sql` in phpMyAdmin after taking a database backup.

The V15.0.1 repair is non-destructive: it creates missing V15 PP/gamification structures, adds missing columns, seeds only missing default records, preserves the immutable PP ledger/purchases/signals, and reconciles the commerce boundary so legacy direct-USDT plan requests remain disabled. It does not drop application tables or clear existing business data.
