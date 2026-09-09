# ABS V15.1.5 Final Validation Report

**Build:** ABS V15.1.5 — Free Signal Entry Watch Fallback Build  
**Release date:** 2026-09-09

## Scope

This release refines the public `/pulse/free-signal` rewarded experience requested after V15.1.4. It preserves the production Alpha Block Solutions logo/header, Pulse scanner engine, direct-USDT package model, ABS News, premium emails, Binance precision guards, page-session reveal, social sharing and 30-minute rewarded cooldown.

## Implemented behavior

- Qualified public/system Pulse signals remain the first priority for rewarded Free Signal.
- If no evaluated setup reaches the configured Pulse qualification threshold, ABS selects the **highest-scoring evaluated LONG/SHORT setup** from the completed system scan and reveals it as **ENTRY WATCH**.
- ENTRY WATCH is not inserted into `pulse_signals`; its rewarded unlock has `signal_id = NULL`, `presentation = market_watch`, `is_qualified_signal = false` and a visible `ENTRY WATCH` label.
- The Entry Watch card clearly states that no qualified Pulse signal is active from the market check and that the setup is for monitoring/confirmation only.
- Entry Watch keeps market context, direction, score, entry/watch level, stop, target, current price, 24h data, strategies and recent candle data where available.
- Entry Watch social sharing explicitly says the setup has **not qualified as a Pulse signal**.
- The public scan now evaluates both supported Pulse timeframes (`15m` and `4h`) when searching for a free qualified signal or Entry Watch fallback.
- If there is neither a qualified signal nor a usable LONG/SHORT Entry Watch candidate, the existing premium **Pulse Market Watch** no-opportunity state remains. No ad is shown and no successful-unlock cooldown is consumed.
- Admin Rewarded Signal Ads distinguishes qualified signal reveals from Entry Watch reveals and excludes Entry Watch rows from the Qualified Signals KPI.
- Terms/market disclaimer copy now explicitly distinguishes Entry Watch from a qualified signal.

## Regression protection

- `PulseScannerService.php` SHA-256 is unchanged from V15.1.4: `54763eebcab6af64ef2299d3abaa999ec137731ed7db7b0cb4945e01446decab`.
- Production ABS logo SHA-256 is unchanged from V15.1.4: `e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8`.
- Global public header SHA-256 is unchanged from V15.1.4: `b3b4483430b274defba74479cc6f183250ae181001183bb778dfbf001af78ce5`.
- Direct USDT → Admin verification → package activation remains active.
- No active Sparks/points economy is reintroduced.

## Validation

- PHP/Blade syntax validation: **PASS** — 280 files across app/bootstrap/config/database/routes/resources views.
- JavaScript/MJS syntax validation: **PASS** — 43 files.
- Static Laravel release audit: **PASS**.
- Blade templates inspected: 91.
- Named routes discovered: 175; route references checked: 163.
- API/OpenAPI operations matched: 112.
- Static view targets checked: 74; Blade references checked: 177.
- Dedicated V15.1.5 Entry Watch release contract: **PASS**.
- Release manifest verification: **PASS**.
- Final ZIP integrity: **PASS**.

## Runtime validation boundary

The source distribution intentionally does not bundle Composer `vendor/`, matching the existing ABS release packaging approach. Therefore Laravel runtime commands such as `php artisan view:cache` cannot execute inside the packaging sandbox without running Composer install first. Production smoke testing still requires the deployment environment for Google rewarded-ad inventory, live MySQL, HostGator cron, FMP, SMTP, Binance Testnet/Live credentials and USDT configuration.

## Upgrade

No new database migration is required from ABS V15.1.4. Back up the site/database, retain the production `.env`, replace the application files and run `php artisan optimize:clear` in the deployed environment where Composer dependencies are installed.
