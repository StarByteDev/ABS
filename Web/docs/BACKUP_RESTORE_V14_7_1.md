# ABS V14.7.1 — Production Backup & HostGator Restore

## Purpose

The Admin → Backup & Restore module provides a portable, administrator-controlled migration path for an existing online Alpha Block Solutions installation. It is intended to preserve the live user database and database-backed configuration when moving the application to HostGator or before a production upgrade.

## Backup contents

A full ABS backup ZIP contains:

- `manifest.json` — backup format, version, timestamp and checksums.
- `database.sql` — complete MySQL/MariaDB schema and data.
- `configuration.json` — safe runtime reference only; no secrets.
- `uploads/` — optional copy of `storage/app/public`.

Database content includes all database-backed ABS configuration and data: users, roles, plans, memberships, subscription/expiry records, CMS content, market news, Pulse controls/settings, signals/trades, email settings/logs, newsletter/contact records, mobile settings/devices, private member reporting, and related application records.

## Secrets and destination environment

Restore does not overwrite `.env`. This is deliberate. HostGator has different database credentials, APP_URL, SMTP credentials and potentially different API keys. Configure those on HostGator before restoring the ABS backup.

The backup does not intentionally export APP_KEY, database passwords, SMTP passwords, API secrets or exchange keys from `.env`. Exchange credentials already stored by the application database remain protected according to the application's existing encrypted-storage behavior and require the same APP_KEY when Laravel encryption is used for those values. Therefore, when migrating encrypted database fields, preserve the existing production APP_KEY securely and set that same APP_KEY on HostGator before restore.

## Important APP_KEY migration note

Laravel-encrypted database values can only be decrypted with the APP_KEY that encrypted them. If the current ABS installation stores Binance/exchange secrets or other encrypted fields, copy the existing production `APP_KEY` securely into the HostGator `.env`. Do not generate a new APP_KEY for that migrated database unless you have first rotated/re-encrypted the protected values.

## Restore safeguards

- Admin role and CSRF protection are required.
- ABS manifest and database checksum are validated.
- The administrator must type `RESTORE`.
- Destination `.env` is never changed.
- Uploaded files restore is optional.
- Pending migrations can run automatically after restore.
- Backups are kept outside the public web root under `storage/app/abs-backups`.

## PHP limits

Web upload restore is limited by `upload_max_filesize` and `post_max_size`. The Admin screen displays both values. Increase them in HostGator's PHP settings when the backup archive is larger than the active limit.
