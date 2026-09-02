# ABS V14.0 Enterprise Administration Guide

## Main dashboard

Open `/admin` after signing in as an administrator. The dashboard is intended to answer the questions an operator normally needs first:

- How many users exist?
- How many are active?
- How many have active Pulse access?
- How many are on a Trial plan?
- Which subscriptions expire in 7 or 30 days?
- Which subscriptions have already expired?
- Which plans are most heavily assigned?
- What recent operational activity is taking place?

## User Directory

Open `/admin/users`.

Available filters:

- Name or email
- ABS account status
- Role
- Pulse plan
- Pulse access status
- Expiring within 7 days
- Expiring within 30 days
- Already expired

Select **Manage** on any user for the User 360° View.

## User 360° View

The detail page combines:

- User identity and account state
- Registration date and last login
- Pulse plan, start date and expiry
- Individual Pulse restrictions
- Daily scanner, signal and trade usage
- Open-trade count
- Total Pulse trade records and stored realized P&L
- Recent signals and trades
- Private Member status

Administrators can update the Pulse plan/access period and core ABS account state separately.

## Subscription renewal

When a user already has Pulse access, the User 360° View offers quick extensions:

- +7 days
- +30 days
- +60 days
- +90 days
- +180 days
- +365 days

If the current expiry is still in the future, the extension is added to that expiry date. If already expired, the extension begins from the current date.

## Subscriptions & Expiry

Open `/admin/pulse/access`.

The page summarizes:

- Active Pulse users
- Trial users
- Expiring within 7 days
- Expiring within 30 days
- Expired access
- Users without Pulse assignment

Use filters to isolate the accounts requiring attention.

## Memberships & Payments

Open `/admin/pulse/memberships`.

This is the commercial control center for Pulse. It includes:

- open membership requests and recent decision KPIs;
- USDT receiving wallet and network;
- customer payment instructions;
- optional/required payment-proof policy;
- Trial auto-assignment, Trial banner and Trial-duration controls;
- coupons and gift vouchers;
- member-specific assigned offers;
- transaction/proof review, activation and rejection.

The user-facing plan page does not expose these internal controls. Customers see only their membership choices, eligible Trial messaging, checkout and their own request history/offers.

## Plans & Entitlements

Open `/admin/pulse/plans`.

The summary table shows each plan's:

- Active/inactive status
- Monthly list price
- Enabled capability count
- Strategy count
- Total assignments
- Active assignments
- Accounts expiring within 30 days

Create and edit plans in collapsible editors below the summary.

## Pulse Operations Center

Open `/admin/pulse` for operational monitoring and direct navigation to:

- Signals
- Trades
- Strategies
- Markets & Pairs
- Controls & Alerts
- Audit Log

## User Management (V14.0)

Open **Admin → User Management** to create and manage accounts. A user can be created as Standard User, Private Member or Administrator. Account status can be Active, Pending or Suspended.

The create-user flow can also assign Pulse immediately, including the plan, access status, start/end dates, internal notes and per-user capability restrictions. Existing users expose the same Pulse controls in User 360°.

Private Member approval is synchronized automatically with the **Private Member + Active** combination. Private reporting values and statements remain managed under **Private Member Reporting**.

User 360° can reset a password without ever sending the password by email. Target mobile tokens and other database sessions are revoked where applicable. The final active administrator is protected from accidental demotion/suspension.

A clean V14.0 seed creates no sample Standard User or Private Member identities. Create real users from the Admin console.
