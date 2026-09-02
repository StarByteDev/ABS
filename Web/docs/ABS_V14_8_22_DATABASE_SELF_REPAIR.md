# ABS V14.8.22 — Protected Database Self-Repair

V14.8.22 adds a browser-based database structure repair path for shared-hosting deployments where Terminal/SSH is unavailable.

## Customer/admin recovery flow

1. ABS detects missing required tables or columns through `EnsureApplicationInstalled`.
2. The Setup Required page shows the configured database and missing-item counts.
3. Enter the private `ABS_RECOVERY_KEY` stored in `Laravel_ABS/.env`.
4. Press **Fix Missing Tables & Columns**.
5. `POST /api/recovery/repair` validates the key using constant-time comparison and rate limiting.
6. The application runs normal `abs:repair` without `--seed`.
7. `AbsSchemaRepair` and `PulseSchemaRepair` create missing required structures and add missing required columns without dropping tables or deleting rows.
8. Legacy create-table migrations already satisfied by the repaired schema are baselined safely.
9. ABS reruns schema diagnosis and reports success only when all required tables and columns are present.

## Shared-hosting recovery-key behavior

Laravel normally loads `ABS_RECOVERY_KEY` through `config/app.php`. If configuration was cached before the key was added, V14.8.22 can read only `ABS_RECOVERY_KEY` directly from the private project `.env` as a fallback. The recovery key is never rendered into HTML, JSON or logs by this feature.

## Safety

The web repair endpoint does not use `--fresh`, does not wipe tables and does not use `--seed`. Existing users, memberships, CMS content, Pulse signals/trades, Binance connection rows and business settings are preserved.

For manual phpMyAdmin recovery, the build also includes `database/ABS_V14_8_22_COMPLETE_DATABASE_SCHEMA_CREATE_REPAIR.sql`.
