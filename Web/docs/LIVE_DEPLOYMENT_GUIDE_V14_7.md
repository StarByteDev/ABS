# ABS V14.7.0 — Live Deployment Guide

## 1. Back up first

Back up the existing MySQL database and current production project before replacing files.

## 2. Production environment

Copy `.env.production.example` to `.env` or merge its production values into the existing `.env`.

Required items:

- Real HTTPS `APP_URL`.
- `APP_ENV=production`, `APP_DEBUG=false`.
- Existing production `APP_KEY` must be preserved during an upgrade. Do **not** generate a new key over a live installation that already has encrypted data.
- MySQL database credentials.
- Production SMTP credentials and sender address.
- `MARKET_SSL_VERIFY=true` and `PULSE_BINANCE_SSL_VERIFY=true` on a correctly configured live PHP installation.
- Change bootstrap administrator credentials before first seed on a new installation.

## 3. Install and repair

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan abs:repair --seed
php artisan abs:doctor
```

For a completely new installation, generate an application key once:

```bash
php artisan key:generate
```

## 4. Validate live integrations

```bash
php artisan abs:market-test
php artisan abs:email-test YOUR_REAL_ADMIN_EMAIL
php artisan abs:production-check --email=YOUR_REAL_ADMIN_EMAIL
```

The market test checks BTC pricing/chart/movers, long/short liquidations, OI, funding, Long/Short Ratio, Perp Premium Basis, Fear & Greed and crypto industries. It fails rather than inventing substitute figures when required live providers are unavailable.

## 5. Scheduler

Configure one cron entry:

```cron
* * * * * /usr/local/bin/php /FULL/PATH/TO/ABS/artisan schedule:run >> /dev/null 2>&1
```

This drives market caching, Pulse maintenance/automation, plan-expiry communications and the optional Daily Market Brief.

## 6. Cache for production

After all `.env`, database and integration checks pass:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7. Go-live security checklist

- HTTPS certificate valid and forced.
- Production APP_KEY preserved/secured.
- Debug disabled.
- Database account uses a strong password and least practical privileges.
- SMTP sender domain has SPF, DKIM and DMARC.
- Test email is actually received.
- `storage` and `bootstrap/cache` are writable; source files are not broadly writable.
- No `.env`, database backup or source archive is exposed from the web root.
- Live/automatic trading gates remain off until deliberately approved and tested.
- Binance credentials are entered only inside the authenticated Pulse connection workflow.
- Admin account uses a strong unique password.
- Run `abs:production-check` after deployment and after major hosting/PHP changes.
