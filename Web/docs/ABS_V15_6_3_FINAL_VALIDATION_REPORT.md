# ABS V15.6.3 — Final Validation Report

Release: **ABS V15.6.3 — Pulse Payments, Alerts & Mobile Signal Parity**  
Validation date: **25 Sep 2026**

## Scope validated

- Direct-USDT checkout and Pulse access/payment-history presentation
- Member and Admin package-payment notifications
- Production mail transport health and non-delivery reporting
- Mobile membership API parity
- Shared web/mobile rewarded Free Signal behavior
- BTC 4-Hour Outlook fallback
- V15.6.1 Market Intelligence/economic-calendar regression behavior
- V15.6.2 one-build application rollback and production-database preservation
- V15.5 multi-signal research and V15.4 strategy workflow regression behavior

## Automated checks

- PHP/Blade syntax: **308 files passed**
- JavaScript/MJS syntax: **53 files passed**
- Static route/view/OpenAPI audit: **PASS**
  - 108 Blade files inspected
  - 191 named routes discovered
  - 175 named-route references checked
  - 117 mobile/API operations matched to OpenAPI
  - 89 static view targets checked
  - 206 Blade template references checked
- V15.4 task workflow regression: **PASS**
- V15.5 multi-signal/Admin-flow regression: **PASS**
- V15.6 news/scanner/calendar regression: **PASS**
- V15.6.1 Market Intelligence/calendar regression on V15.6.3: **PASS**
- V15.6.2 live patch/data-safe rollback regression on V15.6.3: **PASS**
- V15.6.3 payment/mobile Free Signal contract: **PASS**

## Protected invariants

- `app/Services/PulseScannerService.php` SHA-256: `81251d2dd99829127e251c5b217b4d499773ec9f4f4f1de58183cb40012cb5ad`
- `public/assets/brand/abs-logo-master.png` SHA-256: `f2c53ad570c0be3ddcc3683b5cddbfad3c18dbe2530dab1084b391f424b3cc05`
- `public/assets/brand/abs-logo-512.png` SHA-256: `e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8`
- No V15.6.3 database migration added.
- No production seed operation added to the patch installer.
- Application rollback remains code-only and leaves the production database in place.

## Deployment note

The application can only confirm actual inbox delivery on the live host after a real SMTP/sendmail test. V15.6.3 therefore exposes mail health in Admin and records development/non-delivery transports as `not_delivered` instead of presenting them as successful inbox sends.
