# ABS V14.8.6 — Final Validation

Release: **ABS V14.8.6 — Full Package Scanner, Select-All Markets & Scan Results Reliability Build**

Release date: **27 August 2026**

## Validated corrections

- Normal Run Market Scan no longer submits the user's saved execution/watch `selected_pairs` and instead evaluates the complete current-package market universe.
- Scanner targeted symbol requests are bounded by a configurable server safety ceiling (default 1000) rather than the previous 20-pair runtime ceiling.
- Large Binance Futures packages use bounded concurrent candle batches and one bulk 24H ticker request for result context.
- Strategy scores are normalized to 0–100 based on the strategy engines included in the user's package.
- Signal quota exhaustion does not stop evaluation of the remaining package markets.
- All-market provider failure marks the scan failed and does not consume completed daily scanner allowance.
- Interrupted `running` scanner records are released after the stale-run timeout.
- Scanner results are driven by the latest evaluation summary and display below-threshold/no-setup candidates when no qualifying signal is created.
- User Trading Markets includes Select All / Clear All / Popular while preserving the 50-hour change lock and plan `max_selected_pairs` limit.
- Admin package market assignment includes Select All / Clear All.
- Mobile API preserves endpoint parity and uses package-wide scanning when `symbols` is omitted.

## Validation results

| Validation | Result |
|---|---:|
| PHP source files linted | **165 PASS** |
| JavaScript / MJS files syntax checked | **18 PASS** |
| V14.8.6 release contract | **42 PASS** |
| V14.8.5 regression contract | **17 PASS** |
| V14.8.4 regression contract | **78 PASS** |
| V14.8.3 regression contract | **53 PASS** |
| V14.8.2 regression contract | **48 PASS** |
| Premium visual contract | **262 PASS** |
| Blade views inspected | **85** |
| Named routes discovered | **157** |
| Named route references checked | **150** |
| Mobile/API operations matched to OpenAPI | **107** |
| Static release audit | **PASS** |

## Database

No database migration is introduced by V14.8.6. The V14.8.4 self-healing Database Fix, migration reconciliation, backup and rollback protections remain packaged.
