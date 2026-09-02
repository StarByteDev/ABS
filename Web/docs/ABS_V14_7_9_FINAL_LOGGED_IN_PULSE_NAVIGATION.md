# ABS V14.7.9 — Finalized Logged-In Pulse Navigation

**Release date:** 24 August 2026

This corrective release removes the remaining split between the legacy authenticated member experience and the finalized premium Pulse screens approved in the supplied reference images.

## Corrected logged-in behavior

- Normal non-administrator sign-in is now Pulse-first and does not use Laravel's stale `intended` destination to revive an older member page.
- Active Pulse users always land on `/pulse/dashboard`.
- `/dashboard` is compatibility-only and never renders the legacy member dashboard. It forwards active Pulse users to the premium Dashboard and users without current Pulse access to `/pulse/access`.
- Private Member reporting remains available from Profile or the explicit Private Member sign-in path, while the normal account sign-in remains in the Pulse application family.
- The finalized left navigation remains visible on authenticated Pulse screens: Dashboard, Market Scanner, Signals, Strategies, Trade Execution, Open Positions, Trade History, Risk Controls, Alerts & Watchlists, Reports & P&L, Binance Connection and Settings.
- Plan/account restrictions no longer remove menu entries and expose an older-looking reduced navigation. Restricted destinations remain visible as `LOCKED` and route safely to Pulse access information.
- Authenticated Membership & Billing and plan checkout pages now use the same Pulse header/sidebar shell instead of the old general-site member layout.
- The public-site Dashboard button sends signed-in users directly to the correct Pulse destination.
- The obsolete `dashboard.blade.php` content has been replaced by a safe premium-shell compatibility fallback so even a stale server-side route cannot display the previous member dashboard.

## Visual source of truth

The approved Market Scanner, Signals, Strategies and Trade Execution screenshots remain packaged under `docs/reference/pulse-v1478/` and continue to define the authenticated visual family. V14.7.9 changes navigation/routing consistency; it does not replace those approved page designs.

## Deployment

No database migration is required. Preserve `.env`, `APP_KEY`, the MySQL database and uploaded files. Replace the application files and run:

```bash
php artisan optimize:clear
```

This cache-clear step is mandatory when upgrading over an older deployment because old compiled Blade/route/config caches can otherwise continue showing previous authenticated behavior.
