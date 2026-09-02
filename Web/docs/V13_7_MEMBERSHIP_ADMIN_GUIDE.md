# ABS V13.7 — Pulse Membership & Payments Admin Guide

## Purpose

V13.7 keeps the commercial workflow inside Admin while presenting a simple two-membership experience to customers. The default public memberships are **Pulse Intelligence** and **Pulse Professional**. Trial is an onboarding entitlement rather than a paid pricing card.

## First commercial setup

1. Run `php artisan abs:repair --seed` after configuring the project database.
2. Open `/admin/pulse/plans` and set the real USDT price and access duration for Pulse Intelligence and Pulse Professional.
3. Confirm only the plans you want customers to see have **Show on customer plan page** enabled.
4. Open `/admin/pulse/memberships`.
5. Publish the exact USDT network and receiving wallet address.
6. Set customer payment instructions and decide whether proof upload is required.
7. Configure Trial auto-assignment, Trial banner visibility and Trial duration.
8. Enable or disable coupons/gift vouchers as required.

A payment-required plan with an unpublished zero price cannot be requested. A paid request with no configured wallet/network also cannot be submitted.

## Membership request workflow

The customer signs in, selects a plan, reviews the exact amount/network/wallet, transfers USDT and submits the transaction reference/hash. Payment proof can be optional or required. Admin reviews the request in **Memberships & Payments** and either verifies/activates it or rejects it with an internal note.

The request stores the plan, amount, discount, network and wallet snapshot used when the user submitted it. This protects the review trail if commercial settings change later.

## Coupons and gift vouchers

Admin can create:

- percentage discount coupons;
- fixed-value discount coupons;
- full-value complimentary gift vouchers;
- codes limited to one public plan or usable across public plans;
- codes with custom access days;
- validity dates, maximum uses and per-user limits;
- member-specific codes assigned to an ABS user email.

A member-specific code is visible on that member's **My Membership** screen and matching checkout, and the server rejects use by a different account.

For full-value gift vouchers, **Auto-activate** can be enabled. When a valid full gift voucher reduces the membership amount to zero, the system can activate the membership immediately without a USDT transfer.

## Trial controls

Trial settings are managed in `/admin/pulse/memberships`. New accounts can be automatically assigned the active Trial plan for the configured duration. `trial_used_at` records Trial consumption. The public/customer page does not expose the internal one-time eligibility wording.

## Direct access and renewals

Admin can still grant, change, suspend, revoke or extend Pulse access directly through **Subscriptions & Expiry** and the user 360° page. Membership requests are an additional commercial workflow, not the only way Admin can manage access.

## Safety boundary

Membership activation only changes commercial access/entitlements. Live trading and automatic trading still require the installation-level safety gates plus the plan/account/user permissions already enforced by Pulse.

## Re-seeding and upgrades

V13.7 seeding creates missing default membership records but does not intentionally overwrite existing administrator-managed Pulse commercial settings, plan configuration, strategy assignments or access. Avoid destructive reset/fresh commands after real users or payment records exist.
