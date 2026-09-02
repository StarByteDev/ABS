# ABS V14.8.3 — Final Validation

**Release:** ABS V14.8.3 — Premium Async Scanner, Usage Quotas, Safe Logout & 50-Hour Pair Cooldown Build  
**Release date:** 25 August 2026

## Validation results

| Check | Result |
| --- | --- |
| PHP syntax | PASS — 167 PHP files |
| Blade/static route audit | PASS — 85 Blade views |
| Named routes | PASS — 157 discovered |
| Named route references | PASS — 150 checked |
| Mobile/OpenAPI parity | PASS — 107 routed API operations |
| Premium visual contract | PASS — 262 checks |
| V14.8.3 release contract | PASS — 53 checks |
| JavaScript syntax | PASS — ABS app, Pulse shell and premium Pulse runtime |

## V14.8.3 behavior contract

- Run Market Scan submits asynchronously and refreshes scanner metrics, results and scan-quality/saved-view fragments only.
- A professional account menu exposes Profile, Plan & Limits and Sign Out.
- Logout is safe for both the current GET path and legacy/cached POST forms, preventing stale-CSRF 419 Page Expired failures.
- Scanner-run and generated-signal daily usage comes from the active package, is visible in the Pulse shell and is exposed to mobile clients.
- Failed scanner runs do not consume scanner allowance.
- Homepage Long/Short Ratio rejects null/zero/invalid exchange values instead of rendering a false `0.00`.
- Saving a changed trading-pair selection starts a default 50-hour cooldown; the same lock is enforced by web and Mobile API while unrelated settings remain editable.
- V14.8.2 production update/rollback, dynamic Binance Futures markets, 15 package-controlled strategies, pair access and Live/Testnet safeguards remain preserved.

## Upgrade

V14.8.2 installations can stage this release from **Admin → System Updates & Rollback**. The updater creates a restore point before installation and runs migrations plus cache clearing. For a manual deployment, preserve production `.env`, APP_KEY, database, uploads and vendor dependencies, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
```
