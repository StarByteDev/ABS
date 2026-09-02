# ABS V14.0 — Enterprise User Management Guide

## Create a user

Open **Admin → User Management → Create User**.

The administrator can configure the account in one operation:

- full name and email;
- initial password;
- Standard User, Private Member or Administrator role;
- Active, Pending or Suspended account status;
- email-verification state;
- optional account-ready email;
- optional Pulse plan;
- Pulse status, start date/time and end date/time;
- individual capability restrictions;
- internal access note.

## Private Member access

Set the account role to **Private Member** and the account status to **Active**. V14.0 automatically records Private Member approval. The user can then sign in through the Private Member entry point. Financial values and statements are configured separately under **Private Member Reporting**.

## Pulse access

Pulse access can be assigned during user creation or from the user's **User 360° View**. The plan is always the maximum entitlement. Per-user restrictions can disable capabilities but cannot grant a capability excluded by the selected plan.

Exact access dates can be entered. Leaving the end date blank creates open-ended access. Existing access can also be extended by the renewal shortcuts in User 360°.

## Password administration

User 360° includes an administrator password-reset form. The new password is never emailed. On reset, mobile API tokens and other database sessions are revoked where possible. The user receives a security notification email when transactional email is enabled.

## Administrator protection

The system blocks actions that would remove the final active administrator. An administrator also cannot demote or suspend their own current administrator session.

## Clean install behavior

`php artisan abs:repair --seed` intentionally creates only the configured administrator and production-safe platform data. It does not create sample Standard User or Private Member credentials. Create those users from User Management.
