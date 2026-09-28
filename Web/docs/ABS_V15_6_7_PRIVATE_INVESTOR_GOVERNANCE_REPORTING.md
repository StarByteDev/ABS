# ABS V15.6.7 — Private Investor Governance & Portfolio Intelligence

## Purpose
V15.6.7 strengthens the invitation-only Private Investor workflow for production use with multiple investors. It adds portfolio-wide Admin visibility, richer investor reporting, transaction alerts and controlled financial corrections while preserving the existing V15.6.6 monthly provisional-performance engine.

## Admin workflow
- Portfolio Overview: consolidated managed value, net capital, published P/L, return, monthly target, MTD provisional performance and action queue.
- Investor Accounts: account-level portfolio management and monthly performance plan.
- Activity & Corrections: global posted/draft/voided transaction ledger with filters and correction controls.
- Requests: investment, withdrawal and portfolio-review queue.
- Statements: published month-end statements.
- Portfolio Reports: investor performance matrix and CSV export.

## Financial-entry controls
- Save Draft: stores an Admin entry without changing the investor balance or notifying the investor.
- Post & Notify: applies the exact balance effect, creates an in-app alert and sends a branded email.
- Delete Draft: permitted only while the entry has never affected a portfolio balance.
- Void / Reverse: required for posted mistakes. The original record is retained and marked voided; ABS applies the exact inverse of the stored balance effect and records the correction reason, administrator and timestamp.

## Investor communications
Investor receives in-app and branded email notifications for:
- investment/deposit entries
- withdrawals
- profit/loss/fee/valuation adjustments
- voided/corrected posted entries
- monthly statement publication
- request submission
- request status changes

Admin receives a branded email when an investor submits a new investment, withdrawal or portfolio-review request.

## Reporting
Admin gains:
- combined portfolio value trend
- capital/performance flow
- allocation by investor
- positive/flat/negative account counts
- per-investor return, MTD target progress, requests and last statement
- CSV portfolio export

Investor gains:
- 12-month published portfolio trend
- monthly result history
- current-month provisional progress
- capital movement chart
- portfolio value drivers
- detailed audited transaction ledger including corrections

## Privacy
Private Investor is invitation-only. Public product pages, About, public search, auth marketing copy and general public API discovery no longer advertise the Private Investor service. Private modules are exposed only to assigned investor accounts.

## Database safety
The V15.6.7 migration only adds audit/control columns to `portfolio_transactions` and backfills exact historical balance effects. It does not delete or replace records. `down()` intentionally retains the audit data.

## Rollback
The V15.6.2+ Admin Updates & Recovery workflow remains mandatory. Code rollback restores only the previous application build. Live investor and ABS database records are never rolled back by Restore Previous Build.
