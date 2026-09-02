# ABS V14.8.5 — Final Validation

Release: **ABS V14.8.5 — Async Scanner Fragment Stability Hotfix Build**

## Root cause fixed

The asynchronous `/pulse/scanner/refresh` path rendered `pulse.partials.scanner-bottom` independently. That partial previously relied on the parent scanner view's local `$filters` variable, producing `Undefined variable $filters` after Run Market Scan.

V14.8.5 normalizes the fragment contract by deriving filters from `$page['filters']` with a safe score fallback and by explicitly supplying the filter payload from `ScannerController::refresh()`.

## Validation summary

| Validation | Result |
|---|---|
| V14.8.2 production contract | PASS — 48 checks |
| V14.8.3 behavior contract | PASS — 53 checks |
| V14.8.4 upgrade/database contract | PASS — 78 checks |
| V14.8.5 scanner hotfix contract | PASS — 17 checks |
| Premium visual contract | PASS — 262 checks |
| Static Blade/routes/OpenAPI audit | PASS |
| PHP lint | PASS — 156 files |
| JavaScript syntax | PASS — 15 files |
| Mobile/OpenAPI routed operations | 107 |

No database migration is required for V14.8.5.
