# ABS V14.8.5 — Async Scanner Fragment Stability Hotfix

This hotfix corrects an asynchronous Market Scanner refresh failure where `scanner-bottom.blade.php` referenced a parent-view `$filters` variable that was not available when the partial was rendered independently by `/pulse/scanner/refresh`.

## Fixes

- Scanner bottom fragment now derives filter state from the canonical `$page['filters']` payload with a safe default minimum score.
- Scanner refresh controller explicitly passes filter context to the fragment.
- Full-page and asynchronous fragment rendering now share the same data contract.
- No database migration is required for V14.8.5.
- V14.8.4 database self-repair, package-upgrade UX, V14.8.3 asynchronous scanner/quota/logout/pair-lock behavior, Binance Futures support, 15 strategies, Mobile API parity, and Admin rollback remain preserved.
