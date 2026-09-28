# ABS V15.6.8 — Investor Entry Management & Portal Stability

## Private portal stability
The Private Investor overview now renders account-missing and account-present states as separate Blade conditions. This removes the malformed conditional path that caused `/private` to return a 500 ParseError.

## Simple entry correction workflow
Admin → Private Investors → Investor Activity now provides a direct management workflow for mistaken or test entries:

- **Edit**: available for draft or posted transactions. Posted changes automatically adjust the portfolio by the difference between the previous and new accounting effect.
- **Delete**: available for draft, posted or corrected transactions. Posted transactions have their exact stored accounting effect reversed before the database row is removed.
- **Reverse**: retained for Admins who prefer to keep the original corrected record visible in the investor ledger.

Portfolio account values remain editable from each investor's Portfolio Details card.

## Notifications and audit
A posted transaction edit or delete creates a Pulse alert and branded member email. Admin audit history stores the correction snapshot even when the portfolio transaction itself is deleted.

## Production safety
V15.6.8 requires no new database migration. It preserves all existing production data and the single-previous-build code rollback workflow introduced in V15.6.2.
