# ABS V15.7.1 — Private Investor Multi-Currency, Email Alerts & Stability

## Investor currency
Each Private Investor portfolio has one assigned three-letter currency. Member web/mobile views remain in that currency. Admin reporting retains local values but consolidates financial exposure in USD.

For non-USD transactions, Admin locks the USD conversion rate with the financial entry. The resulting USD amount and accounting effects are stored with the transaction so later exchange-rate movement does not rewrite historical investment reporting. Current reference quotes are optional convenience only; backdated activity should use the actual historical conversion used for the transfer or reporting record.

## Notifications
Confirmed portfolio transactions create both an in-app Pulse alert and a branded investor email attempt. The same communication framework covers posted edits, deletions/corrections, investment-term updates, published statements, investor request submissions/status changes and request cancellation.

Email delivery depends on a real SMTP/sendmail transport. Admin → Alerts & Emails reports the effective transport and recent delivery status; LOG/ARRAY are explicitly treated as non-delivery transports.

## Stability
The V15.7.0 Monthly Progress ParseError was caused by an overly compact Blade/PHP calculation. V15.7.1 uses a simple loop and adds release QA that lints all PHP files, parses Blade PHP blocks/echo expressions, checks Blade control-directive balance, validates JavaScript, and runs route/view/API static audit.

## Data safety
The release adds only USD audit/reporting fields to existing investor account, transaction and statement tables. Existing live records are retained. Non-USD historical records with no reliable historical rate are left for Admin reconciliation rather than being assigned an invented rate. Code rollback remains separate from database history.
