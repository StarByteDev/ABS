# ABS V15.6.9 — Private Investor Admin Simplification & Portfolio Reconciliation

## Purpose
V15.6.9 fixes the Private Investor member dashboard render failure and separates investor administration into short, task-based pages.

## Individual investor flow
Summary → Portfolio Values → Monthly Performance → Transactions → Statements → Requests.

## Correcting stale values
Portfolio transactions and portfolio account values are separate records. A deleted transaction reverses that transaction's stored accounting effect. If the account also contains manually entered Reported Value / Net Investment / P&L, Admin can either edit those values, reset them to zero, or use **Recalculate from Ledger** to rebuild totals from Opening Investment + remaining posted transactions.

## Production safety
No database schema change is introduced. Existing production data is preserved and the V15.6.2+ code-only previous-build rollback workflow remains unchanged.
