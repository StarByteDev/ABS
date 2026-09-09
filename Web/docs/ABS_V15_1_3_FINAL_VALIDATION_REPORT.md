# ABS V15.1.3 Final Validation Report

**Build:** ABS V15.1.3 — Branded Free Signal Teaser + Persistent Page-Session Reveal + Social Sharing Build  
**Release date:** 09 September 2026  
**Base:** ABS V15.1.2

## Release scope

V15.1.3 implements the approved ABS Pulse Free Signal experience inside the existing Alpha Block Solutions branding. The production ABS logo and global public header were not replaced or redesigned.

### Free Signal behavior

- No registration is required.
- The real signal payload is not present in the initial page HTML.
- Before ad completion, the signal area is a blurred placeholder/teaser only.
- A rewarded signal is returned only after the rewarded-ad grant is accepted by the existing signed claim endpoint.
- The unlocked signal remains visible in the current page until refresh/navigation. There is no 30-second auto-hide timer.
- The server never re-exposes the prior rewarded signal through the status/index response after refresh.
- A successful unlock starts the configured cooldown (30 minutes by default), and the live next-free-signal counter remains visible above the result.
- When the cooldown ends, the current page can request another rewarded signal; refreshing during cooldown returns to the teaser while preserving the cooldown.

### Signal presentation

The rewarded snapshot now includes central ABS market context when available:

- current price
- 24H percentage change
- 24H high / low / volume
- recent central ABS candles for the signal timeframe
- entry
- up to three take-profit levels
- stop loss
- score / confidence label
- qualified strategies
- simple setup summary

The browser renders the chart from the rewarded payload only after unlock. Missing market fields are displayed as unavailable/emdash values; the page does not fabricate market data.

### Social sharing

The revealed card exposes easy sharing actions for:

- X
- Facebook
- WhatsApp
- Telegram
- LinkedIn
- Reddit
- native/device share via Web Share API where available
- Copy Link fallback

The shared URL is the public ABS Free Signal page. The unlocked private payload is not embedded in the shared URL, so the recipient lands on the ABS Pulse acquisition experience and unlocks their own signal.

## Branding integrity

The production branding assets were intentionally preserved from V15.1.2:

- `public/assets/brand/abs-logo-512.png` SHA-256: `e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8`
- `resources/views/partials/header.blade.php` SHA-256: `b3b4483430b274defba74479cc6f183250ae181001183bb778dfbf001af78ce5`

Both hashes are byte-identical to the V15.1.2 base.

## Protected subsystem regression checks

The following important V15.1.2 files remain byte-identical:

- Pulse scanner engine: `54763eebcab6af64ef2299d3abaa999ec137731ed7db7b0cb4945e01446decab`
- Binance Futures precision/execution service: `fafea2bf5ca48fda72d679687c1415e1c390c92c6f34e7662c9d4694713c6953`
- ABS News controller and existing News UI/history navigation were preserved.
- Premium transactional email template and Admin communications UI were preserved.

Direct USDT → Admin verification → package activation remains active and no customer Sparks/points economy has been reintroduced.

## Automated validation completed

| Validation | Result |
|---|---|
| PHP syntax validation across PHP/Blade-labelled files | **PASS — 292 files** |
| JavaScript/MJS syntax validation | **PASS — 42 files** |
| Static Blade/route/API/view audit | **PASS** |
| Blade templates inspected | **91** |
| Named routes discovered | **175** |
| Named route references checked | **163** |
| Mobile/API operations matched to OpenAPI | **112** |
| Static view targets checked | **74** |
| Blade template references checked | **177** |
| V15.1.0 direct-USDT/rewarded-signal regression contract | **PASS** |
| V15.1.1 ABS News/legal/email regression contract | **PASS** |
| V15.1.2 usability/Binance/calendar-history regression contract | **PASS** |
| V15.1.3 Free Signal / teaser / social-sharing contract | **PASS** |
| Direct-USDT architecture regression check | **PASS** |
| ABS News navigation regression check | **PASS** |
| Binance precision-guard regression check | **PASS** |
| Active app/routes/views/config Sparks/points scan | **PASS** |

## Database impact

V15.1.3 requires **no new database migration**. The existing nullable `view_expires_at` column remains for schema compatibility, but new V15.1.3 rewarded unlocks store it as `NULL`; page-session visibility is controlled by the claim response and browser page lifecycle. Existing V15.1.0–V15.1.2 migrations remain valid.

## Required production smoke tests

These external services cannot be fully executed in the packaging environment and must still be tested with your own production/test credentials after deployment:

1. Google rewarded-ad inventory and browser eligibility on desktop and mobile web.
2. Google production ad-unit configuration and consent behavior in your target regions.
3. Binance Testnet and Live execution using your API credentials.
4. FMP economic-calendar provider responses using your key.
5. SMTP delivery/rendering through your production mail account.
6. Production MySQL and HostGator cron/scheduler behavior.
7. Direct USDT wallet/network/payment review workflow.

## Upgrade from V15.1.2

1. Back up the application and database.
2. Preserve the production `.env` and secrets.
3. Replace the V15.1.2 application files with V15.1.3.
4. Run `php artisan optimize:clear` where Terminal is available.
5. No V15.1.3 database migration/import is required.
6. Keep Google rewarded inventory in test mode until the new page flow is verified in your target desktop/mobile browsers.
