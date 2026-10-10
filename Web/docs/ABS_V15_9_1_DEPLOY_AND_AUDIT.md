# ABS V15.9.1 — Whole-web Theme Candidate

## Scope and limitations

- Starts from complete V15.9.0 source and preserves trading, investors, Firebase and payments.
- Adds a final CSS theme system loaded **after existing page CSS** on the 4 standard layout shells (public, authentication, member, administrator).
- Source-inventories all Blade templates in `docs/ABS_V15_9_1_COMPLETE_THEME_SOURCE_AUDIT.json`.
- Browser-tested illustrative components at 390, 768, 1440 pixels, Light and Dark. These are representative HTML fixtures; **not authenticated end-to-end screenshots**.
- Laravel `vendor` and a database/session were not provided. Running every authenticated route is not possible within this source-only environment. A live/staging browser audit is required before replacing V15.9.0.

## Changes

- Fixes Open Positions, Trade History, and shared Pulse KPI/financial panels: always pairs background and text and improves text size/spacing.
- Brings signal reviews, watchlists, reports, tables, filters and cards onto a single foreground/background palette.
- Preserves green/red P&L and separate visual states for alerts/locked features.
- Responsive tables scroll inside their cards on narrow screens.
- Preserves V15.9.0 news impact filter and earlier work.

## Safe installation

1. Take backup of the full **deployed** web files and database, record the working build/commit. If using the patch, use it ONLY on exactly V15.9.0.
2. Deploy the new stylesheet and 4 layout files to STAGING first (or full build staging install); do not delete storage, database, `.env`, cron or user media.
3. From the Laravel project directory: `php artisan view:clear`; `php artisan optimize:clear` (if supported by your hosting).
4. Clear reverse proxy/CDN browser cache; use Ctrl+Shift+R. Verify the Network tab shows `abs-theme-v1591.css?v=15.9.1` and it is the **last stylesheet**.
5. In browser, check public, sign-in, member, private-investor, and admin routes as real authorized users. Test light+dark, desktop+phone (and tablet), empty/populated data, menus, modals and validations.
6. Run read-only `python tests/Visual/audit_all_web_routes.py --base-url http://YOUR-STAGING:8000 --storage-state /outside-repo/member-state.json --out /outside-repo/member-audit.json` and repeat for investor/admin sessions. Do not include session JSONs in a source ZIP.
7. Verify risk actions, dates, economic-calendar filters, signals and database transaction counts are unchanged; do not run trade or destructive actions as part of visual inspection.
8. Only then promote to production and retain rollback ZIP/patch.

## Rollback

Restore the original 4 layout files (remove v1591 CSS inclusion) and optionally delete `abs-theme-v1591.css`; clear view/browser cache. No migrations to revert.

## Not included

Native Flutter source changes are not part of this web-only theme package. This CSS does not modify Flutter screens.
