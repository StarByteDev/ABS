# ABS V13.2 Error Corrections

## 1. Binance Testnet cURL error 28

### What the screenshot showed

The previous `abs:pulse-test` requested:

```text
https://demo-fapi.binance.com/fapi/v1/exchangeInfo
```

That response contains exchange metadata for a large number of USD-M Futures instruments. The local cURL process stopped after about 15 seconds while only part of the response had arrived:

```text
cURL error 28: Operation timed out
```

This was a diagnostic-command timeout. It did not indicate an invalid API key, because the command used a public endpoint and did not read user credentials.

### V13.2 correction

The standard Testnet check now uses small public requests:

```bat
php artisan abs:pulse-test --environment=testnet
```

It verifies ping, server time, BTC/USDT price and a small candle response. Exchange precision and quantity filters remain available through the deliberately separate command:

```bat
php artisan abs:pulse-pairs --environment=testnet
```

For local XAMPP, the default Pulse timeout is now 30 seconds. The exchange-information request uses at least 60 seconds and retries transient connection failures.

---

## 2. PHPUnit `table "users" already exists`

### What the screenshot showed

The failing SQL attempted to create a legacy `users` table with columns such as:

```text
is_active
accepted_terms_at
role enum('admin', 'user')
```

Those are not the fields in the clean V13.2 create-users migration. This proves the local project folder contained an older migration or another stale users-table creation path.

All four feature-test classes failed while preparing the shared SQLite `:memory:` database. Therefore, that output did not establish that the private portal, public pages, Pulse APIs or schema-repair assertions were individually broken; PHPUnit never reached those assertions.

### V13.2 correction

Feature tests override Laravel's database migration stage and build the canonical schema through:

```text
App\Support\AbsSchemaRepair
App\Support\LegacyMigrationBaseline
```

The clean package also includes:

```bat
php artisan abs:test-doctor
```

This command reports every migration that creates `users` and stops when it finds duplicate or known legacy signatures.

### Required local cleanup

Use the complete V13.2 build in a new folder. Preserve:

- `.env`
- required files from `storage/app/public`
- any other user-uploaded storage files

Do not copy the old `database/migrations` folder into the new build.

Then run:

```bat
composer install
composer dump-autoload
php artisan optimize:clear
php artisan abs:repair --seed
php artisan abs:doctor
php artisan abs:test-doctor
php artisan test
```

The expected migration-doctor message is:

```text
PHPUNIT MIGRATION SET: READY
```

---

## 3. Box-heavy Pulse design

The earlier Pulse gateway used separate bordered boxes for every capability, access step and plan. V13.2 replaces that with:

- open editorial spacing
- numbered workflow rows
- fine section dividers
- one continuous access timeline
- open plan columns
- flatter authenticated Pulse surfaces

Forms, tables and critical controls retain enough structure for usability, but promotional card-on-card styling has been removed.
