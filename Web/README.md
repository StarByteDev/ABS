## ABS V15.7.4 — Automatic Investor Profit Payouts, Statements & USD Consolidation

V15.7.4 is the production Private Investor settlement and reporting release built on V15.7.3. It fixes the gaps visible in the live investor screens: completed months no longer leave Statements blank, monthly profit is treated as an external investor payout rather than capital retained in the portfolio, capital withdrawals remain separate from profit distributions, and Admin can see every investor in their own principal currency while also managing all investor funds in consolidated USD.

### Investor accounting behavior
- Daily performance continues to accrue automatically from the agreed monthly percentage and effective date.
- At the configured payout moment, ABS automatically finalizes the completed performance month, records the month's **Profit Paid** transaction, and publishes/reconciles that month's statement.
- The default payout timing is month end. Admin can alternatively set a payout day from 1–28 of the following month.
- **Profit Paid is not a capital withdrawal.** It is money distributed to the investor and therefore has zero principal/current-capital effect.
- **Capital Withdrawal** is reserved only for return of invested principal. The investor receives principal in the locked original account currency under the V15.7.2/V15.7.3 FX-protection rule.
- Monthly statements show opening capital, added capital, capital withdrawn, profit/loss, profit paid, payment date, and closing capital balance.
- Completed due months are reconciled idempotently: re-running the scheduler or opening the portal cannot create duplicate monthly profit payouts.

### Admin reporting
- Each investor remains visible in their own account/principal currency, including principal, reported value, profit paid, performance and statements.
- Admin Portfolio Overview and Reports also maintain consolidated USD accounting using each transaction's locked USD basis/rate.
- **Total Investor Funds** is the combined USD historical principal basis across active investors.
- Admin-only realized FX gain/loss remains separate from investor profit and does not alter investor principal.
- The Activity ledger distinguishes **Investment**, **Capital Withdrawal**, and **Profit Paid** so the Withdrawals KPI is zero when no principal was actually returned, even if monthly profit has already been paid out.

### Automatic settlement and shared-hosting scheduling
- `InvestorSettlementService` performs monthly settlement and statement reconciliation.
- The normal ABS scheduled maintenance pipeline calls the settlement process every minute; the operation is idempotent and safe for the existing HostGator one-minute cron setup.
- Portal/Admin/API reads also reconcile due months before presenting financial summaries so an already-due statement does not remain blank simply because a scheduled invocation was delayed.
- Historical manual profit entries are preserved and mapped to a performance month instead of being duplicated.

### Mobile/API readiness
- Private Investor mobile APIs now expose the corrected activity summary with separate `capital_withdrawal` and `profit_paid` values.
- Statements returned to mobile include `profit_paid`, `payment_date`, and the reconciled monthly statement fields.
- Private Investor API reads invoke due-settlement reconciliation before returning account, transactions or statements.
- OpenAPI and application build identity are updated to V15.7.4 so the next Flutter release can target this backend contract.

### Production/data safety
- V15.7.4 uses an additive migration only. No investor, user, transaction, statement, request, agreement, trade, payment or audit row is deleted.
- Historical `profit` records are retained. Their old capital-increase effect is corrected to zero because published/paid profit is external distribution, not invested principal.
- Existing V15.7.2 principal-currency and Admin FX invariants remain unchanged.
- Schema Repair includes the V15.7.4 fields for shared-hosting recovery.

### Release QA
The final release checks cover PHP syntax, Blade stability, routes/views/OpenAPI consistency, automatic settlement contract, statement reconciliation, payout/withdrawal separation, Admin local-currency plus consolidated-USD reporting, mobile API fields, schema-repair coverage and release-manifest integrity. The package does not contain production dummy/test financial records.

---

## ABS V15.7.3 — Investor/Admin Principal Currency Controls & Production UX Fix

V15.7.3 completes the end-user multi-currency workflow introduced in V15.7.1/V15.7.2. The accounting engine already supported non-USD investor principal and Admin USD FX accounting, but an existing empty investor account could still look fixed to USD because the main Admin transaction form and investor Requests page did not provide a complete currency-change path.

### What is fixed
- **Investor side:** Private Investor → Requests now has a real Principal Currency selector for an empty portfolio.
- **Investor first/add-investment request:** the investor can select the currency being contributed before financial activity starts.
- **Admin side:** currency can be changed from Investment Setup, Transactions, and Account Controls while the portfolio is financially empty.
- **First Admin transaction:** selecting PKR/AED/EUR/etc. immediately switches the amount label, FX conversion panel, and FX reference lookup to that currency.
- **USD is now a default, not a forced choice.** Supported currencies come from `config/private_investor.php`.

### Principal-protection rule remains locked
- Example: investor contributes **PKR 1,000**.
- ABS stores PKR 1,000 as the investor principal and stores the investment-date USD equivalent as Admin historical cost basis.
- Full principal withdrawal returns **PKR 1,000**, regardless of later PKR/USD movement.
- Any difference between historical USD basis and withdrawal settlement USD cost is an **Admin-only realized FX gain/loss**.
- The investor's principal, monthly target and statement figures are never revalued because FX moved.

### When currency can and cannot change
The principal currency can be changed only while there is no real financial history. `InvestorCurrencyService` enforces this server-side and uses a database row lock.

Currency becomes locked when the account contains transactions, statements, non-zero portfolio values, meaningful non-zero performance history, or an unresolved capital request. A zero-value performance schedule created from a percentage agreement does **not** incorrectly lock an otherwise empty account. An open Add Investment/Capital Withdrawal request must be resolved or cancelled before changing currency so a pending request cannot silently change denomination.

This means an existing live account that currently shows USD 0.00 can be switched to PKR/AED/etc. from either Admin or the investor portal, provided no financial history has already been recorded.

### Admin workflow
1. Open **Private Investors → Investment Setup**, **Transactions**, or **Account Controls**.
2. Select the required Principal Currency while the account is empty.
3. For a non-USD first investment, enter or fetch the locked `1 currency = USD` rate.
4. Post the investment. From that point the investor currency is locked to protect principal.
5. Admin consolidated reporting continues in USD.

### Investor workflow
1. Open **Private Investor → Requests**.
2. On an empty portfolio, choose the Principal Currency and save it, or choose the currency directly while submitting the first Add Investment request.
3. Once financial activity starts, withdrawals are always requested in the locked principal currency.

### Database and production safety
V15.7.3 adds **no database schema changes**. It uses the existing additive V15.7.1/V15.7.2 fields. No users, investors, transactions, statements, requests, agreements, payments, trades or audit records are deleted or recalculated during deployment.

Deploy over V15.7.2 using the normal ABS backup/patch workflow. Run normal Laravel cache clearing after deployment. Existing V15.7.1/V15.7.2 migrations should remain in place and may be run normally on any server that has not applied them yet.

### Release QA
V15.7.3 validation covers PHP syntax, Blade compilation-oriented stability checks, static routes/views/OpenAPI consistency, explicit Admin/investor currency controls, server-side currency locking, first-transaction currency handling, dynamic FX UI behavior, preservation of V15.7.2 principal/FX accounting invariants, and release-manifest integrity.

---

## ABS V15.7.2 — Principal-Currency Protection & Admin FX Accounting

V15.7.2 is a production-safe upgrade of V15.7.1 for the Private Investor module. It keeps each investor's capital obligation permanently denominated in the investor account currency while ABS maintains consolidated internal administration in USD.

### Locked investor-principal rule
- Each investor account has one principal currency selected by Admin from the supported currency list.
- The investor's principal amount never changes because the USD exchange rate changes.
- Example: an investor contributes **PKR 1,000**. If the investment-date rate gives an Admin USD carrying basis of USD 3.57, the investor still owns **PKR 1,000 principal**.
- When that full principal is returned, the investor receives **PKR 1,000**, regardless of the later PKR/USD rate.
- If returning PKR 1,000 costs ABS USD 3.33, Admin records a USD 0.24 realized FX gain. If it costs USD 3.85, Admin records a USD 0.28 realized FX loss.
- Realized FX is Admin-only and never changes investor principal, investor P/L, monthly target, or investor statements.

### USD accounting model
- Investments store the original amount/currency, investment-date locked FX rate, USD settlement equivalent, and historical USD principal basis.
- Capital withdrawals store the original investor-currency amount, withdrawal settlement rate, actual USD settlement cost, historical USD principal basis removed, and realized Admin FX gain/loss.
- Partial capital withdrawals use a weighted-average historical USD principal basis. A full withdrawal consumes all remaining USD basis to avoid rounding residue.
- Admin `net_contributions_usd` is a historical carrying basis, not a floating revaluation of investor principal.
- Published monthly statement P/L may be translated at the statement rate, but principal additions and withdrawals use their transaction-level historical basis.

### Investor experience
- Overview, transactions, performance, statements and requests remain in the assigned investor currency.
- Capital Withdrawal explicitly means return of invested principal in the same investor currency.
- Investor request validation prevents a capital-withdrawal request above remaining principal.
- No Admin USD basis, settlement cost or FX gain/loss is exposed through the investor model serialization.

### Admin experience
- Investor setup clearly selects the principal currency.
- Transaction entry explains investment-date USD basis versus withdrawal settlement rate.
- Withdrawal preview shows investor amount returned, Admin USD basis, USD settlement cost and estimated FX gain/loss.
- Account Snapshot, transaction ledger, Portfolio Overview and Reports show realized FX gain/loss separately from investor P/L.
- CSV reporting includes Admin realized FX gain/loss.

### Existing V15.7.1 live data
- Upgrade migration is additive only.
- No users, investors, transactions, statements, requests, agreements, trades, payments or histories are deleted.
- Existing V15.7.1 transaction financial effects are not silently rewritten during migration.
- New or edited withdrawals use the V15.7.2 principal-basis/settlement model.
- Existing USD accounts continue 1:1 with no FX gain/loss.

### Database / shared-hosting safety
The normal migration and `AbsSchemaRepair`/Database Fix paths both know the new fields:
- `portfolio_accounts.realized_fx_gain_loss_usd`
- `portfolio_transactions.principal_usd_basis`
- `portfolio_transactions.settlement_usd_amount`
- `portfolio_transactions.fx_gain_loss_usd`

The migration `down()` intentionally retains finance/audit fields under the permanent ABS production data-preservation rule.

### Release QA
V15.7.2 release validation covers PHP syntax, Blade expression/directive stability, static route/view/API checks, schema-repair parity, build/version identity, principal-currency invariants, withdrawal basis/settlement/FX contract, Admin-only field protection, and production additive migration requirements.

### Upgrade
Deploy V15.7.2 over the existing V15.7.1 application using the existing ABS production patch/backup workflow. Run normal migrations (or the existing Database Fix if required by the shared-hosting environment), clear application/config/view caches, then verify one non-USD investor with a small test/draft flow before posting real financial activity.
