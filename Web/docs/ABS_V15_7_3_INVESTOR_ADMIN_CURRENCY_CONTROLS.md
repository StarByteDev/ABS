# ABS V15.7.3 — Investor/Admin Principal Currency Controls

## Why this release exists
V15.7.1/V15.7.2 contained the multi-currency accounting fields and FX logic, but an already-created empty investor portfolio could still appear fixed to USD in the main Admin transaction screen and the investor Requests screen. V15.7.3 completes the end-user currency-selection workflow.

## Currency lifecycle
1. Admin creates the investor portfolio shell. USD remains only the default, not a permanent choice.
2. Before financial history begins, either Admin or the investor can choose any supported principal currency.
3. The Admin transaction form also allows the currency to be selected immediately before the first financial entry.
4. The investor Add Investment request allows the investor to choose the actual principal currency before financial activity starts.
5. Once transactions, statements, non-zero portfolio values, or meaningful performance history exist, the principal currency is locked.
6. A locked currency is not a UI limitation; it protects the contractual principal rule. For example, PKR 1,000 principal remains PKR 1,000 principal and is returned as PKR 1,000 regardless of USD/PKR movement.

## Admin controls
Currency is editable on:
- Private Investors → Investment Setup
- Private Investors → Transactions (before the first financial entry)
- Private Investors → Account Controls

The first non-USD transaction requires the locked USD conversion rate. Switching the transaction currency updates amount labels and FX UI immediately, and the current-reference button requests the selected currency rather than a hard-coded account currency.

## Investor controls
Private Investor → Requests now includes a Principal Currency card. An empty portfolio can change currency directly. The Add Investment request also exposes a supported-currency selector. A withdrawal always uses the locked principal currency.

## Production protection
No V15.7.3 schema migration is required. Currency changes are server-enforced through `InvestorCurrencyService`, not only hidden/disabled in HTML. Crafted requests cannot relabel a funded portfolio. The service uses a database row lock and refuses a currency change after financial history begins.

## Pending request safety
A submitted, under-review, or approved Add Investment/Capital Withdrawal request locks the account currency until that request is resolved or cancelled. This prevents a pending capital request from being re-denominated after submission.
