# ABS V15.9.0 — UI Theme Repair and Calendar Impact Filter

**Status:** staging candidate, NOT live production verified. **Base:** V15.8.9 full candidate. **Scope:** web CSS, period/impact filtering on public economic calendar; no trading, mobile binary, database, strategy or Firebase modifications.

## Issues addressed
- `/profile`: separate `.pp-service-tile` backgrounds and hard-coded heading/description colors in a Light theme.
- `/pulse/free-signal`: separate `free-signal-page` design system with dark-only panels and text when user selected Light. Blur/claim/advertising, risk acknowledgment and cooldown logic remain unchanged.
- `/news`: nested macro period tabs, forecast/previous/actual figures and status text were dark-on-dark/light. The visible legend was not interactive. Added actionable High / Medium / Low / All impact filters; server-side filtering applies before `limit(240)`, and counts use the same filter; current period remains selected across changes.
- Signed-in member navigation: sidebar flex layout caused inconsistent icon/label/badge alignment. Replaced with grid-aligned icon/label/badge columns; collapsing sidebar restores icon-only navigation. All locked routes retain access controls.

## QA limitations — do not misrepresent scope
- Source/template audit is NOT a visual QA pass over every authenticated route. The Laravel `vendor/` directory and a staging database/role accounts were not provided, so full end-to-end route tests are not possible here. Use included route crawler in `scripts/` on a **staging** installation with read-only test accounts and confirm both themes.
- CSS and JS browser fixtures validate representative components with original CSS but are not a complete rendered Laravel site.
- The native Flutter app was not rebuilt. This fixes responsive web pages only.

## Deploy and rollback
1. Backup exact live files, `.env`, DB and `/public/assets/` and stage first.
2. If on V15.8.9, use the small patch to replace ONLY: `app/Http/Controllers/NewsController.php`, `resources/views/news/index.blade.php`, four layout Blade files and add `public/assets/css/abs-theme-v1590.css`. Do not replace production `.env`, `.sql`, `storage` or `vendor`.
3. `php artisan view:clear` and `php artisan optimize:clear`; hard-refresh and change themes while moving between pages.
4. Check `/news?calendar=upcoming&impact=high`, `/news?calendar=today&impact=medium`, `/news?calendar=history&impact=low`, `/profile`, `/pulse/free-signal`, all member/admin routes accessible under each role, both themes; also at 390, 768, 1440 viewport widths. Filter must not change live headlines or editorial articles.
5. Rollback by reverting the seven modified files and removing the new stylesheet; no database rollback is required.
