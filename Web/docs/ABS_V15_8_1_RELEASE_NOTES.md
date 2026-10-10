# ABS V15.8.1 Release Notes

## Purpose
This release adds a ranked qualified-signal shortlist to the web scanner, effective-dated Private Investor rates, selectable profit payout/reinvestment, capital-aware future target calculation, and an Admin exchange-performance ledger.

## Accounting examples

### Rate changes without rewriting history
- Investment capital: 20,000
- Jan–Jun agreement: 5% monthly → a full unchanged month targets 1,000.
- New agreement effective 1 July: 2% monthly → July targets 400 if capital is still 20,000.
- Jan–Jun published statements remain unchanged.

### Retained monthly profit compounds future capital
- Capital: 20,000
- 5% completed-month profit: 1,000
- If disposition is **reinvest**, the 1,000 is recorded as Profit Reinvested and also added to invested capital on settlement.
- New capital becomes 21,000 from that settlement date; a later full month at 2% targets 420.

### Partial principal withdrawal
- If capital is 21,000 and 5,000 is withdrawn during a month, invested capital becomes 16,000 from the posted withdrawal date.
- That month is day-weighted across the pre/post-withdrawal capital; later full months use 16,000 unless another capital movement occurs.

## Deployment safety
1. Back up production code/config and database before deploying.
2. Deploy files without deleting existing storage or environment configuration.
3. Run the normal Laravel migration command once.
4. Run the application schema/health check used by the existing ABS shared-hosting workflow.
5. Confirm web login, Private Investor pages, Find Best Signal, cron/scheduler health and existing mobile API access before announcing the release.
6. Do not roll back by dropping V15.8.1 finance tables; the migration intentionally retains audit/financial history.

## Mobile/API compatibility
The scanner still publishes one canonical Best Signal through the established API shape. Multiple qualifying candidates are exposed to the web experience without removing existing response fields or routes.
