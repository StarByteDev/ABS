# ABS V15.8.7 — Light/Dark UI hotfix (testing candidate)

## Why this release exists

The existing 15.8.6 light theme styles were mainly for `wf-*`, `pulse-card`, and common tables, but the actual authenticated Pulse member dashboard uses `pp-*` components from `pulse-premium.css`. This caused light outer scaffolding with hard-coded navy dashboards (and invisible text). The existing theme button displayed the **active** state instead of the **next action**.

## Fix

- `public/assets/css/abs-theme-v1587.css` is a last-loaded, scoped token layer for the member `pp-*` system (dashboard, signals, scanner, account, reports, strategy pages, ticket drawer, tables), shared navigation, notices, admin and public surfaces.
- `public/assets/js/abs-theme-v1587.js` is the single controller for the toggle on admin, public, and member layouts. It persists `abs.theme` (`light` or `dark`) and changes the label to "Switch to Dark" / "Switch to Light", plus accessible aria-label/title/aria-pressed.
- All three layouts were updated to load the **versioned** new CSS after `@stack('head')` and use the new JS to avoid stale browser caches.
- No PHP business logic, payment/investor workflows, trading strategies, market data, Firebase services, routes, or database tables have been modified by this hotfix.

## Deploy safely on shared HostGator hosting

1. Back up the currently deployed Laravel project and existing `.env` and database. **Do not replace the `.env` with the example in this archive.**
2. For a minimal hotfix, upload `public/assets/css/abs-theme-v1587.css` and `public/assets/js/abs-theme-v1587.js`; update exactly these layout templates: `resources/views/pulse/layout.blade.php`, `resources/views/admin/layout.blade.php`, `resources/views/layouts/app.blade.php`.
3. Optionally upload the full archive as an application code-only upgrade. Preserve existing production `.env`, storage, uploaded media, permissions, and MySQL tables; NEVER truncate or reseed them.
4. Clear Laravel compiled views (`php artisan view:clear`) only if available. The new CSS/JS have `?v=15.8.7` cache keys; hard-refresh browser or reopen the app if needed.
5. With a staging user account, test Dashboard, Find Best Signal, Signals, Reports, Private Investor, Admin Strategy Performance, website landing/login, with both themes. Verify sidebar, tables, forms, colored statuses, chart labels, responsive phone layout, and persistence after navigation/reload.
6. Restore the previous three layout files (and drop new CSS/JS) to roll back instantly if an unexpected theme regression occurs; no database rollback is necessary for this theme-only patch.

## Validation performed

- Browser-rendered local **representative dashboard markup** with the actual bundled stylesheets in Chromium, not your authenticated live Laravel site.
- Computed colors of dashboard body, header, sidebar, KPI, card, and table for both themes were verified and differ consistently.
- Button click changes theme state and next-action label. New JavaScript syntax and static release contract validated.
- **Not performed:** on-host staging and full authenticated per-page screenshot testing, production WebSocket/Firebase or DB integration testing. Live production behavior is not guaranteed.

## Baseline

Built from ABS V15.8.6 candidate (which in turn contains V15.8.5 calendar enhancements and preserves the V15.8.4 application baseline). Production V15.8.4 remains the last confirmed deployed build until the user validates any newer candidate.
