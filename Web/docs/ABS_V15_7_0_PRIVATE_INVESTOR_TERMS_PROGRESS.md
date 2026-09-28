# ABS V15.7.0 — Private Investor Investment Terms & Automatic Monthly Progress

This release makes Private Investor administration task-based and connects posted investment capital to an explicit monthly performance agreement.

## Calculation model

For each calendar month, ABS calculates the capital active on each day from Opening Investment plus posted investment/withdrawal ledger effects. The month's equivalent capital is the sum of daily active capital divided by the number of calendar days. The configured monthly percentage is applied to that equivalent capital.

This means a first investment dated mid-month is automatically prorated, while later full months receive the full target if capital remains unchanged.

Daily provisional accruals use deterministic varied weights and varied scheduled times. They reconcile to the configured monthly target. They do not create realized-profit transactions or publish official statements automatically.
A published monthly statement finalizes its month: future provisional posting for that month stops and finalized provisional amounts are excluded from the additional indicative-value layer.

## Production safety

The V15.7.0 migration only adds `portfolio_investment_terms` and safe metadata columns on `portfolio_performance_plans`. Existing live data is retained. Rollback remains application-code-only under the V15.6.2 production rule.
