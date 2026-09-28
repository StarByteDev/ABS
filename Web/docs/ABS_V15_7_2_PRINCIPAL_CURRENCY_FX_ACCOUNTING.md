# ABS V15.7.2 — Principal-Currency Protection & Admin FX Accounting

## Production rule
Investor principal is a liability in the investor account currency, not in USD. A PKR 1,000 investment remains PKR 1,000 principal until capital is added or withdrawn. USD exchange-rate movement belongs to ABS/Admin accounting only.

## Posting model
**Investment**
- local principal increases by the original investor-currency amount;
- USD principal basis increases by the locked investment-date USD equivalent;
- realized FX = zero.

**Capital withdrawal**
- investor receives the requested principal amount in the same investor currency;
- local principal decreases by that exact amount;
- USD principal basis decreases using weighted-average historical cost;
- USD settlement amount uses the withdrawal-date/admin-entered settlement rate;
- realized Admin FX = historical USD principal basis removed minus USD settlement amount.

Positive result is an Admin FX gain; negative result is an Admin FX loss. Neither result enters investor P/L.

## Safety / compatibility
V15.7.2 is an additive upgrade from V15.7.1. It adds audit/accounting fields only and does not delete or replace existing records. Existing V15.7.1 transaction effects are preserved during migration; the new model applies to new or edited withdrawals. The shared-hosting Database Fix path (`AbsSchemaRepair`) contains the same fields as the normal migration.

## Validation
Release checks verify version identity, PHP/Blade/static application integrity, schema parity, backend principal guards, weighted-average basis logic, Admin-only field protection and the PKR 1,000 gain/loss examples.
