# ABS V14.6.1 MySQL Setup Guide

## Local Laragon — recommended Windows setup

1. Start Laragon and make sure **MySQL** is running.
2. Open **Laragon Terminal** in the extracted ABS V14.6.1 project folder.
3. Ensure PHP has `pdo_mysql` enabled.
4. Run `php cleanup-legacy-migrations.php`.
5. Run `composer install`.
6. Copy `.env.example` to `.env` if `.env` does not already exist.
7. Run `php configure-mysql.php`.
8. Run `php artisan key:generate` if `APP_KEY` is empty.
9. Run `php artisan optimize:clear`.
10. Run `php artisan abs:test-doctor`.
11. Run `php artisan abs:repair --seed`.
12. Run `php artisan abs:doctor`.
13. Run `php artisan serve`.

Typical Laragon values are database `abs`, user `root`, blank password, host `127.0.0.1`, port `3306`.

The same MySQL settings can also be used with XAMPP when its local MySQL account is configured that way.

### One-command Windows launcher

Run:

```bat
RUN-ABS-LARAGON.bat
```

This performs the setup checks and then starts `php artisan serve` on `http://127.0.0.1:8000`.

## Fix for legacy `users` migration collision

If an older ABS project was previously present in the same folder, a stale migration such as `2026_05_02_000000_create_users_table.php` can cause MySQL error 1050 because the current build already owns the `users` table. V14.6.1 includes:

```bat
php cleanup-legacy-migrations.php
```

The helper keeps the canonical current users migration and moves conflicting legacy create-users migrations into `database/migrations_legacy_disabled/`. It does not delete them.

For existing or partially created ABS databases, use the non-destructive repair flow:

```bat
php cleanup-legacy-migrations.php
php artisan optimize:clear
php artisan abs:repair --seed
php artisan abs:doctor
```

For a dedicated disposable local `abs` database that you intentionally want to wipe and rebuild, use:

```bat
php artisan abs:repair --fresh --force --seed
```

Do not use destructive fresh commands against a database containing data you need to keep.

## Shared hosting / cPanel

Create a MySQL database and user in the hosting control panel, grant that user privileges on the database, and use the exact prefixed names supplied by the host in `.env`. Do not assume the database can be named exactly `abs` on shared hosting.

After the connection is configured, run:

```bash
php cleanup-legacy-migrations.php
php artisan optimize:clear
php artisan abs:repair --seed
php artisan abs:doctor
```

## Existing data

V14.6.1 does not automatically copy rows from an SQLite database into MySQL. If migrating a real V13.x SQLite installation, export/import the data deliberately and retain a backup. Once the data is present in MySQL, `abs:repair --seed` adds missing current schema/configuration without intentionally deleting existing ABS records.
