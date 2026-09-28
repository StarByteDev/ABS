# ABS V15.7.4 — Automatic Investor Profit Payouts, Statements & USD Consolidation

## Fixed
- Fixed blank investor Monthly Statements for completed/due performance months by adding automatic idempotent month settlement and statement reconciliation.
- Corrected profit accounting: a posted monthly `profit` entry is an external **Profit Paid** distribution and no longer increases investor capital/current portfolio value.
- Kept **Capital Withdrawal** exclusively for return of invested principal, so the Withdrawals KPI no longer includes normal monthly profit payouts.
- Added explicit `profit_paid`, `profit_paid_usd`, `payment_date` and automatic-statement metadata.
- Added `performance_month` and `entry_source` to transaction records so monthly payouts reconcile to the correct performance month without duplicate creation.
- Added configurable automatic payout timing to the investment agreement: month end by default, or day 1–28 of the following month.
- Added due-settlement reconciliation to investor portal, Admin reporting and Private Investor API reads.
- Updated investor Transactions and Admin activity/reporting labels to distinguish Investment, Capital Withdrawal and Profit Paid.
- Updated Admin overview/reporting to show every investor in their own currency while maintaining total investor funds and consolidated reporting in USD.
- Added Admin-only realized FX gain/loss separation without changing investor principal.
- Updated mobile/API activity summary with separate `capital_withdrawal` and `profit_paid` fields and reconciled statement data.

## Production/data safety
- Additive migration only; no investor/user/transaction/statement/request/agreement records are deleted.
- Existing profit records are preserved while their legacy capital-increase side effect is corrected to zero.
- Historical missing performance-month references are populated deterministically for reconciliation.
- V15.7.2/V15.7.3 original-currency principal and Admin USD FX accounting rules remain intact.
- Shared-hosting `AbsSchemaRepair` includes V15.7.4 fields.

---

# ABS V15.7.3 — Investor/Admin Principal Currency Controls & Production UX Fix

## Fixed
- Added a real editable Principal Currency selector to the investor Requests screen for financially empty portfolios.
- Added investor-side direct currency update and first Add Investment request currency selection.
- Added Admin Principal Currency controls to Investment Setup, Transactions and Account Controls.
- Added first-transaction currency selection so an empty USD-default account can be changed to PKR/AED/EUR/etc. without recreating the investor.
- Made the Admin FX panel dynamic: amount labels, currency prefix, USD-rate label, rate requirement and FX quote lookup now follow the selected currency.
- Fixed the empty-account lock rule so a zero-value generated performance schedule does not prevent a legitimate currency change.
- Centralized currency lifecycle enforcement in `InvestorCurrencyService` with server-side validation and row locking.
- Prevented funded/history-bearing accounts from being relabelled through crafted requests, preserving original-currency principal protection.
- Added an open-capital-request lock: submitted/under-review/approved Add Investment or Capital Withdrawal requests must be resolved before principal currency can change.

## Production/data safety
- No database schema change in V15.7.3.
- No historical investor, transaction, statement, request, agreement, trade, payment or audit data is rewritten.
- V15.7.2 historical USD principal basis and realized FX accounting remain unchanged.
- Existing V15.7.1/V15.7.2 migrations and shared-hosting schema-repair behavior remain compatible.

---

# ABS V15.7.2 — Principal-Currency Protection & Admin FX Accounting

- Locked investor principal to original account currency; FX movement cannot alter investor capital.
- Added historical USD principal basis, withdrawal settlement USD amount and Admin realized FX gain/loss.
- Capital withdrawals now reduce local principal by the exact local amount and reduce Admin USD principal by historical weighted-average basis.
- Added over-withdrawal protection against remaining investor principal.
- Added Admin withdrawal preview and transparent basis/settlement/FX presentation.
- Added Admin realized FX to account snapshot, consolidated overview, reports and CSV export.
- Updated statement USD logic so principal is not revalued at statement FX rates.
- Updated normal migrations and shared-hosting `AbsSchemaRepair` path.
- Preserved V15.7.1 records without destructive migration or silent historical rewrite.
- Bumped web/API build identity to 15.7.2.

---

# ABS V15.7.1 — Private Investor Multi-Currency, Email Alerts & Stability

## Fixed
- Fixed the Monthly Progress page ParseError caused by an unsafe compact `@php` expression.
- Added Private Investor Blade QA for PHP blocks, echo expressions and balanced control directives.
- Investor transaction pages now clearly show whether outbound email delivery is ready or requires SMTP/sendmail configuration.

## Private Investor multi-currency
- Investor-facing portfolio values remain entirely in the investor's assigned currency.
- Added transaction-level locked FX conversion and USD accounting effects for Admin consolidation.
- Added statement-level locked FX conversion and USD reporting values.
- Added current FX reference lookup for Admin transaction/statement entry while keeping historical rate entry under Admin control.
- Admin Portfolio Overview, Monthly Progress, Investor Accounts, Reports and CSV exports use USD consolidated values.
- Internal USD accounting fields are hidden from investor/mobile serialization.

## Alerts and email
- Premium ABS Pulse branded email layout refreshed for investor account communications.
- Confirmed transactions, corrections, deletions, statements, investment-term changes and investor request lifecycle events produce investor notifications.
- Investment transaction emails include the agreed monthly rate/effective date when configured with the investment.
- Investor request cancellation now sends a confirmation email.
- Admin transaction management includes recent investor email-delivery history.

## Data safety
- Additive-only migration; no destructive database operation.
- Database Fix/Schema Repair knows the V15.7.1 multi-currency fields.
- V15.6.2 single previous-build code-only rollback remains unchanged.
