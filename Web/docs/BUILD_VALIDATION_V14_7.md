# ABS V14.7.7 Build Validation

**Validated:** 24 August 2026  
**Build:** Premium Pulse User Pages + Mobile API Parity

## Completed checks

| Check | Result |
|---|---|
| Blade templates inspected | PASS — 79 |
| Named web routes discovered | PASS — 146 |
| Literal named route references | PASS — 139 |
| Controller classes and registered methods | PASS |
| Local assets and cross-page fragments | PASS |
| Placeholder link/action scan | PASS |
| Dynamic route shadowing scan | PASS |
| PHP block/delimiter structural scan | PASS |
| API routes matched to OpenAPI | PASS — 105 / 105 |
| OpenAPI YAML parsing | PASS — version 14.7.7, 92 paths, 105 operations |
| Premium visual contract | PASS — five pages, 262 standalone checks |
| Approved reference dimensions | PASS — five images at 1676×939 |
| ABS logo identity | PASS — SHA-256 `e43da94188c10d7a67884765334cbd555e9e8e1dc7d63bd3dd7acca21d9007c8` |
| Premium-page purple palette scan | PASS — none |
| Customer-facing terminology check | PASS |

The visual contract regenerates production-style, self-contained previews for Dashboard, Market Scanner, Signals, Strategies and Trade Execution. It verifies the approved desktop geometry, page classes, section hierarchy, navigation, fixture content, logo, palette and decision-support language.

## Runtime acceptance on Laragon/production

This source package intentionally excludes `vendor/`. Run the framework-dependent checks after `composer install` on PHP 8.2 or newer:

```bash
php artisan optimize:clear
php artisan abs:doctor
php artisan abs:view-audit
php artisan test --filter=PulsePremiumPagesApiTest
php artisan abs:production-check --email=YOUR_TEST_EMAIL
```

The `abs:view-audit` command compiles every Blade template and performs PHP lint on the compiled result when the host permits process execution. The package-level static and visual contracts remain available with:

```bash
node scripts/static-release-audit.mjs
node tests/Visual/verify-premium-contract.mjs
```

No database migration or destructive rebuild is required for V14.7.7.
