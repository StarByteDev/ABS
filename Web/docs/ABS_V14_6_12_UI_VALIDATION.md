# ABS V14.6.12 UI Validation

Date: 22 August 2026

Validated changes:
- Long / Short Ratio uses the same bordered split-card presentation as OI/Funding.
- Perp Premium Basis uses the same bordered split-card presentation as OI/Funding.
- Both live `data-*` bindings remain connected to the existing JavaScript refresh flow.
- Desktop homepage canvas is widened to `min(1800px, calc(100% - 36px))`.
- Tablet/mobile width rules remain unchanged.
- Malformed literal `\\n` stylesheet append from V14.6.11 is removed.
- `resources/css/home-final.css` and `public/assets/css/abs-home-final.css` are byte-identical.

Static validation:
- 211 PHP files passed `php -l`.
- 5 JavaScript files passed `node --check`.
- `tinycss2` reported 0 stylesheet parse errors.
- CSS brace counts match.
- Required Long/Short Ratio and Perp Premium Basis server + client bindings are present.

Runtime market provider logic is unchanged from V14.6.11. Use `php artisan abs:market-test` on Laragon to exercise the live provider calls from the target environment.
