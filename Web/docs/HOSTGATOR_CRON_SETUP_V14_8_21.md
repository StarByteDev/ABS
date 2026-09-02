# HostGator Shared/Baby Cron Setup — ABS V14.8.21

ABS V14.8.21 includes a HostGator-aware Laravel scheduler profile for shared hosting.

## Why 15 minutes on HostGator Shared

HostGator shared-hosting guidance recommends not setting cron intervals below 15 minutes, and HostGator shared-hosting policy has historically restricted cron entries below 15 minutes. V14.8.21 therefore does **not** require a one-minute host cron on HostGator Shared/Baby.

The central market-data process runs every 15 minutes in this profile. Each cycle downloads the missed closed 1-minute candles for active signal symbols, so signal validation still evaluates the 1-minute candle sequence between cron executions. The current-price snapshot itself can be up to roughly one scheduler cycle old on this hosting tier.

## Required `.env` values

```env
PULSE_SCHEDULER_PROFILE=hostgator_shared
PULSE_MARKET_PRICE_REFRESH_SECONDS=900
PULSE_MARKET_READ_MAX_AGE_SECONDS=1200
PULSE_MARKET_SCANNER_SYMBOLS_PER_CYCLE=200
PULSE_MARKET_VALIDATION_1M_LIMIT=180
```

After changing `.env`:

```bash
php artisan optimize:clear
php artisan abs:scheduler-check
```

## Cron command

Find the full path to the ABS Laravel root — the folder containing `artisan`. If the application root is `/home/CPANELUSER/public_html`, the cron command is typically:

```bash
cd /home/CPANELUSER/public_html && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /home/CPANELUSER/abs-scheduler.log 2>&1
```

If Laravel is installed in a subfolder such as `/home/CPANELUSER/alphablocksolutions`, use that folder instead.

Use the PHP binary matching the site PHP version. For PHP 8.3, HostGator's cPanel EA-PHP pattern is commonly `/opt/cpanel/ea-php83/root/usr/bin/php`. Verify it in Terminal before saving the cron.

## HostGator schedule fields

Use **Every 15 Minutes**. Equivalent fields are:

```text
Minute: */15
Hour: *
Day: *
Month: *
Weekday: *
```

Do not add separate cron entries for market data, validation, learning, order synchronization, maintenance, or email reminders. Laravel `schedule:run` dispatches the correct ABS jobs for that time.

## First verification

Run these from HostGator Terminal after deployment:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan abs:repair --seed
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan abs:doctor
/opt/cpanel/ea-php83/root/usr/bin/php artisan abs:scheduler-check
/opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:list
/opt/cpanel/ea-php83/root/usr/bin/php artisan abs:production-check
```

`abs:production-check` does not submit a trade. It verifies the application environment, database/schema, Blade/routes, scheduler profile, public market connectivity, central market-data ingestion, and signal-validation pipeline. Email is only tested when `--email=you@example.com` is supplied.

After the cron has fired, check the HostGator Cron Jobs **View Last Run** option or inspect `~/abs-scheduler.log`. Once confirmed, you may redirect cron output to `/dev/null` instead of the log file.
