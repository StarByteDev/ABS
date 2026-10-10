# V15.8.8 web UI theme audit candidate

## Fixed source-level causes
- V15.8.7 only themed `pp-*` member design components. The public homepage still uses `final-*` classes with fixed navy backgrounds, while V15.8.6 broadly recolors `p/small/strong` to light foreground. This created invisible prices/headings in Light mode.
- Introduced final-loaded `abs-theme-v1588.css`: explicit matched background and foreground for public market board, chart controls, side cards, trust cards, news, plans, footer and CTA; styling also extends shared admin/member/investor modules.
- Authentication uses a separate layout and was missing theme state, stylesheet and switch control. Added `abs-auth-theme-v1588.css` and shared persistence.
- Semantic gains/losses and gold action buttons remain legible in both themes.

## Deploy / cache
1. Back up complete current code and database. Keep V15.8.7 for rollback.
2. Use the complete web source, or copy only files listed below into the existing V15.8.7 site. Do not replace `.env`, `storage`, uploads or the live database.
3. Clear Laravel views/cache: `php artisan view:clear`, `php artisan optimize:clear` (use host's available PHP CLI).
4. Hard reload on desktop/mobile to invalidate previous CSS, open homepage first, then member/admin/private-investor pages, news, auth, mobile nav.
5. Restore previous files (no DB change) to roll back.

## Updated files
- `public/assets/css/abs-theme-v1588.css` (new)
- `public/assets/css/abs-auth-theme-v1588.css` (new)
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/pulse/layout.blade.php`
- `resources/views/admin/layout.blade.php`

## Testing limits
Browser smoke/contrast tests run on representative DOM using actual CSS, not authenticated Laravel pages or real production data. Manual in-app QA remains needed for all app routes. Native Flutter Pulse app is a separate project, NOT modified by this web package. Its own ThemeMode-based design must be verified separately.

## High-contrast sidebar follow-up (user screenshot)
- Member navigation: removed the legacy `opacity:.58` reduction from locked links, maintained locked access/badges without disabling their labels, improved active/hover styling, text sizing, wrapping and semantic label clarity.
- Admin navigation: increased menu row typography from 10.6px to 12.5px, group heading from 7.6px to 10.5px; supplied matched foreground/background in both modes and accessible mobile menu styles.
- All changes are CSS-only. No access control, routes, signal selection or trading behavior was changed.

## Deployment options
- **UI-only patch** is intended for existing **V15.8.7** installations. Copy files preserving paths, without touching `.env`, storage, uploads, vendor, or database.
- **Full candidate** includes the underlying V15.8.6 changes as well as V15.8.7 and V15.8.8 theme revisions. It is not recommended to replace a production app without verifying its expected base, existing migrations and staged tests.
- Check signed-in Pulse nav (including locked links), admin nav in expanded/collapsed state, homepage market terminal, sign-in, account/profile, macro news, all other routes, on phone/tablet/desktop and both themes.

**No claim of verified native Flutter dark/light theme** — native mobile source must be revised and tested separately.
