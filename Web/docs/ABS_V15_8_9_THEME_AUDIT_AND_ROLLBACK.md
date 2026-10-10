# ABS V15.8.9 — full-shell theme contrast regression candidate

**Status:** testing candidate; not verified against an authenticated running Laravel site. Parent build: V15.8.8. No DB changes, trading/strategy modifications, credentials or user data included.

## Root causes and repairs

1. In `abs-auth.css`, `-webkit-autofill` forced `-webkit-text-fill-color:#fff` regardless of Light theme. A themed autofill rule now sets text and fill together; tested selected and unselected field states.
2. `.pi-hero-value`, `.pi-hero-month`, `.pi-stat` and related `pi-` investor components used hard-coded dark gradient backgrounds, while general Light theme rules forced dark text. V15.8.9 binds complete investor surfaces and text to single-mode variables and preserves profit/loss semantics.
3. `.pulse-account-popover` used a dark-only background, separate from global header theme. All popover surfaces and nested text now use themed tokens.
4. Public industry categories and market movers were never covered by the global theme overrides; their labels and metric cards are now covered.
5. CSS architecture: multiple historical theme overrides remain; the new targeted stylesheet intentionally loads LAST and avoids destructive blanket `p, small, strong` text-color rules. New development should migrate older style systems to tokens rather than adding new global overrides.

## Files changed

- `public/assets/css/abs-theme-v1589.css` (new)
- Four web Blade master layouts: `layouts/app.blade.php`, `layouts/auth.blade.php`, `pulse/layout.blade.php`, `admin/layout.blade.php` (new versioned CSS include; JS cache-bust)
- Build metadata and this release note

## QA limitations

The supplied source has no `vendor/` Laravel dependencies or staging database, so true account-authenticated route-by-route browser verification is not possible here. Static Blade inventory, representative DOM/production CSS browser fixtures and automated contrast and theme-toggle checks are included separately. Browser fixtures are **not** equivalent to a complete authenticated site test. The separate native Flutter project is not changed.

## Deployment and rollback

1. Back up the **exact installed source**, `.env`, database and current `public/assets` files.
2. Prefer a **staging instance**; test all URLs under each role, including empty/loading/error states, dates/currencies and responsive/mobile browser layouts.
3. To apply as a safe patch, copy `public/assets/css/abs-theme-v1589.css` and the four modified layout templates without overwriting other files. Or use full ZIP in staging; **never replace an existing production `.env`, `storage` or DB**.
4. Run `php artisan view:clear` and `php artisan optimize:clear` as supported. Hard-refresh `Ctrl+Shift+R` and reopen different role sessions. Asset `?v=15.8.9` busts browsers' old theme CSS cache.
5. Verify Chrome autofill, account dropdown, private investor balances, public market tiles, all sidebar/nav and chart/alert status colors in light and dark at 390px and 1440px (also tablet), and verify theme persisted navigating routes.
6. If anything fails, restore previous four templates and remove the CSS link, leaving original source, DB and `.env` unchanged.

## Repeatable authenticated route crawl (staging only)

The repository includes `tests/Visual/audit_all_web_routes.py`. Run it with a valid **staging** Playwright session file after logging in as a permitted role. Repeat with member, investor and admin accounts. It follows same-origin GET-only navigation, switches light/dark at 390px and 1440px, flags suspect low-contrast elements and horizontal overflow, and never submits forms. `--screenshots` is optional because authenticated pages may contain private information; keep all reports out of public directories and source control. It does not cover modal/error/empty states without further stateful tests. See script usage header.

**Source inventory:** 131 Blade view/partial files: 60 admin page views, 27 Pulse/member views, 12 public views, 4 authentication views, and 28 layouts/partials/standalone views. Every regular public/admin/member/auth view references the master shell where V15.8.9 is included; standalone error templates and email HTML are not changed to protect recovery and email rendering.
