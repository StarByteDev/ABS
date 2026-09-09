# ABS V15.0.3 Validation Report

Release: **ABS V15.0.3 — Pulse Sparks Packages & Strategy Intelligence Build**  
Baseline: **ABS V15.0.2** (`d684f9302a0d2d7119f08efb4ce81bd9ff28779d1608005d7474fc20fd05abb4`)

## Delivered scope

- Renamed the customer-facing economy from Pulse Points/PP to **Pulse Sparks/Sparks** across member, public, Admin, email, legal and API display surfaces.
- Rebuilt `/pulse/sparks` with balanced responsive spacing, four package cards before the top-up flow, wallet/reward progress, guided USDT verification and protected Spark activity.
- Added Spark Day Pass (1 day), Spark Flex (3 days), Spark Momentum (7 days) and Spark Professional (30 days), with different Spark prices, Best Signal costs, market coverage and capabilities.
- Added Admin gifting to registered users through the transaction-locked, idempotent wallet ledger.
- Expanded Admin Strategy Intelligence with filters, all-strategy rollups, validation backlog, feed health, W/L, entry rate, ambiguous outcomes, MFE/MAE, evidence, reliability and confidence impact.
- Corrected package market-limit enforcement for `pair_access_mode=all` and changed central candle warming from obsolete user pair selections to active/public package universes.
- Added a Laravel migration and one-time non-destructive phpMyAdmin upgrade script.

## Automated validation

| Check | Result |
|---|---|
| V15.0.3 Sparks/package/strategy contract | PASS |
| V15.0.3 premium responsive UX contract | PASS |
| Release-wide Blade, route, controller, template and OpenAPI parity audit | PASS |
| JavaScript syntax checks | PASS |
| Root/database phpMyAdmin SQL parity | PASS |
| SQL destructive-operation scan | PASS |

The release-wide audit inspected 89 Blade files, discovered 182 named routes, checked 165 route references, matched 122 mobile/API operations to OpenAPI, checked 73 view targets and checked 202 Blade template references.

## Preserved strategy calculations

The critical scanner method bodies were extracted and compared against the V15.0.2 baseline:

| Method | SHA-256 | Result |
|---|---|---|
| `analyze()` | `471140c16db31b8cf8f0f10c4d566a5278a2becb99fe505ea5b54875aae0d06e` | Byte-identical |
| `signalBreakdown()` | `6b378e48ea044b12c82940fe7f9a7001b87bce5dbb4e92667bcb12007f46ea63` | Byte-identical |
| `confidenceLabel()` | `7b78dc33510b63469ff5013f063e7360f082870a815ded7a6dd7560bc94e05a8` | Byte-identical |

`PulseLearningService.php` and `PulseSignalValidationService.php` are also byte-identical to V15.0.2:

- Learning service: `59725637af0f68da261c7b814894d204240d0b1f9d9c3b5a8e3786509480a3b4`
- Validation service: `ce19e435bcd22944f0c606b153291a946df900f70be576b70e2934e5d1660add`

Confidence remains `technical × 0.75 + learned reliability × 0.25`. Signal validation still runs every minute, immediately rebuilds learning for dates with newly resolved outcomes, and retains the daily learning catch-up.

## Deployment validation

This packaging workspace does not include PHP, Composer or MySQL executables, so Laravel runtime and database execution must be completed on the target server:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan abs:doctor
php artisan abs:production-check
```

For shared hosting without Terminal, import `ABS_V15_0_3_APPLY_PULSE_SPARKS_PACKAGES.sql` after the V15.0.2 schema is ready. The `v1503_spark_packages_seeded` marker prevents repeat imports from resetting later Admin package edits.

Keep the Laravel scheduler cron at once per minute.
