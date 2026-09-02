# ABS V14.6.1 — Laragon MySQL Fixed Build

**Date:** 11 August 2026  
**Baseline:** ABS V14.6 Complete Pulse Workspace Build

## Purpose

V14.6.1 is a corrective local-runtime package. It preserves the complete V14.6 Pulse workspace, approved homepage/authentication branding, Admin, plans, Private Member Portal and MySQL-first runtime while correcting the legacy migration collision seen when a new ABS source tree is copied over an older project folder.

## Error corrected

The reported failure was:

```text
SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'users' already exists
```

The failing workspace contained both the current V14.6 users migration and an older `2026_05_02_000000_create_users_table.php`. `php artisan migrate:fresh --seed` then executed both files and the older migration attempted to create `users` a second time.

## V14.6.1 corrections

- Added `cleanup-legacy-migrations.php`, a framework-free preflight that detects more than one migration creating the `users` table.
- The current migration is retained; conflicting legacy create-users migrations are moved, not deleted, to `database/migrations_legacy_disabled/`.
- `setup-local.bat`, repair scripts and MySQL switch scripts now run the migration cleanup before Laravel database work.
- Local Windows guidance is Laragon-first while remaining compatible with XAMPP/MySQL.
- Runtime/version labels and the local cache prefix are updated to V14.6.1.
- Added `RUN-ABS-LARAGON.bat` for a one-command local setup + serve workflow.
- The recommended schema path remains `php artisan abs:repair --seed`, which is designed for ABS databases and does not depend on executing stale historical table-creation migrations.

## Recommended Laragon workflow

1. Extract this ZIP into a **new folder** rather than copying files over an old ABS project.
2. Start Laragon and start MySQL.
3. Confirm database `abs` exists, or let `configure-mysql.php` create it when permitted.
4. Open a Laragon Terminal in the project folder.
5. Run:

```bat
setup-local.bat
```

6. Then start the app:

```bat
php artisan serve
```

Alternatively run `RUN-ABS-LARAGON.bat` to perform setup and start the local server.

## Existing/partially-created `abs` database

Use the non-destructive path:

```bat
php cleanup-legacy-migrations.php
php artisan optimize:clear
php artisan abs:repair --seed
php artisan abs:doctor
```

## Dedicated empty database reset

For a disposable local `abs` database only, ABS provides its own fresh repair path:

```bat
php artisan abs:repair --fresh --force --seed
```

Do not use destructive fresh commands on a database containing data you want to keep.
