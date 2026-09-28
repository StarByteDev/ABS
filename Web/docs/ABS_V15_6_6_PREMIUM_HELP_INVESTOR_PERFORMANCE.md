# ABS V15.6.6 — Premium Help Center & Investor Performance Reporting

This release redesigns Pulse Help into an exchange-style assistant experience and expands Private Investor reporting with current-month performance planning, varied daily provisional accruals, interactive portfolio reporting and Admin controls.

## Pulse Help
- Natural-language Pulse Assistant first.
- Known account/product questions receive an immediate answer.
- Questions that cannot be answered confidently are automatically handed to ABS Support in the same conversation.
- The same conversation remains available across web and mobile APIs.
- Premium single-chat layout replaces the older topic-heavy help screen.

## Private Investor
- Premium Portfolio Command Center with reported value, indicative value, published P/L, current-month progress, 12-month trend, monthly results, recent statements and activity.
- Admin can define a monthly performance target per investor using a base amount and percentage.
- ABS creates varied daily provisional accruals across the month and schedules each day at a deterministic variable time.
- Daily provisional amounts reconcile exactly to the configured monthly target after manual adjustments.
- Admin can add or subtract a daily provisional adjustment; remaining unposted days rebalance automatically so the monthly target is not exceeded.
- Provisional accruals never create realized profit transactions or publish monthly statements automatically. Published statements remain the official account record.
- Mobile `/api/v1/private/account` now includes the current performance plan and daily provisional schedule.

## Production safety
- Additive tables only: `portfolio_performance_plans`, `portfolio_daily_accruals`.
- No existing production records are removed or replaced.
- Database Fix can repair the two new tables non-destructively.
- V15.6.2 one-build code-only rollback remains intact.
