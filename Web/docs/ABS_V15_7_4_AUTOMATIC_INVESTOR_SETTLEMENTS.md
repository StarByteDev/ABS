# ABS V15.7.4 — Automatic Investor Profit Payouts, Statements & USD Consolidation

## Purpose
Close the Private Investor production gaps discovered after V15.7.3: blank completed-month statements, profit payouts being visually/accountingly confused with capital withdrawals, and the need for clear investor-currency plus consolidated-USD Admin reporting.

## Settlement lifecycle
1. Daily accruals are generated from the active investment agreement and month-specific principal base.
2. When the month's configured payout moment is due, `InvestorSettlementService` locks the account and checks for an existing payout for that performance month.
3. If no payout exists, the due monthly target/accrual is posted once as a `profit` transaction with `entry_source=automatic_monthly_payout` and `performance_month` set to the performance month.
4. Profit payout has zero capital/current-value effect; it contributes to published P/L and paid-performance reporting only.
5. The service creates or reconciles the corresponding monthly statement from posted ledger activity.
6. Re-running settlement is idempotent and cannot create a second payout for the same performance month.

## Statement contract
Statements include investor currency, opening capital, contributions, capital withdrawals, profit/loss, profit paid, payment date, closing capital, and corresponding Admin USD reporting values. Investor serialization hides internal USD accounting fields.

## Currency and FX contract
Investor principal stays denominated in the locked original account currency. Admin consolidates the historical USD basis. Principal withdrawal settlement-rate differences remain Admin-only realized FX gain/loss.

## Mobile API contract
Private Investor API account payload distinguishes `capital_withdrawal` from `profit_paid`; transactions retain investor-facing amount/currency/status/date fields; statements expose the monthly reconciliation including `profit_paid` and `payment_date`. Due settlement is reconciled before account/transaction/statement reads.

## Deployment
Run normal non-destructive Laravel migrations after deploying the code. Existing HostGator one-minute scheduler continues to be supported. Clear Laravel application/config/view caches after deployment.
