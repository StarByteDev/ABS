# ABS V14.7.8 — Complete Premium Authenticated User Experience

**Release date:** 24 August 2026  
**Baseline:** uploaded `ABS_V14_7_7_PREMIUM_PULSE_USER_PAGES_MOBILE_API_PARITY_BUILD(1).zip`  
**Original SHA-256:** `3c67ef3783a6e846e1758ce3faf618954e55fb4e5f9edcf5dedbd2a80e62403f`  
**Database migration:** None

## Why V14.7.8 was required

The V14.7.7 source already contained the approved premium implementations for Pulse Dashboard, Market Scanner, Signals, Strategies and Trade Execution. The package was nevertheless incomplete as a final authenticated-user build because the login/default dashboard path still exposed a separate older member dashboard, Profile did not use the approved premium Pulse application shell, and several secondary Pulse pages retained the earlier workspace styling. This made the deployed application appear to be missing agreed customizations even though part of the premium work existed in source.

## Corrected routing and navigation

- Active Pulse users now sign in to `/pulse/dashboard`.
- A successful Pulse-specific sign-in also opens `/pulse/dashboard`.
- `/dashboard` remains a safe compatibility entry point; when the user has active Pulse access it redirects to `/pulse/dashboard`.
- Users without Pulse access still retain the account fallback needed to review plans/access.
- The top profile icon and bottom sidebar user identity both open `/profile`.
- Sign-out is available from the Profile page rather than replacing the expected profile affordance in the sidebar user card.

## Premium Profile

`/profile` now uses `pulse.layout` and therefore keeps the exact ABS logo/header, collapsible left navigation, plan chip and premium navy/cyan/gold identity used by the approved Pulse screenshots.

The page exposes only authenticated/real account information:

- account status, role, verification, member-since and last-sign-in state;
- Pulse plan, access status, start/expiry, remaining duration and plan limits;
- current Pulse environment and safe Binance readiness state;
- active signals, open/in-flight positions, 30-day executed trades, realized P&L and unread alerts;
- Pulse Intelligence, Private Member Portal and Administration access as applicable;
- personal watchlist management and public market context.

No manufactured production performance values were added.

## Remaining Pulse page alignment

The existing data and business logic for Open Positions, Trade History, Risk Controls, Alerts & Watchlists, Reports & P&L, Binance Connection, Settings and related authenticated surfaces is preserved. Their shared UI controls, headings, metric cards, forms, tables, panels, empty states and action buttons are visually harmonized in `pulse-premium.css` so they belong to the same compact professional application family as Dashboard, Scanner, Signals, Strategies and Trade Execution.

## Preserved functionality

- V14.7.6 administrator membership request approve/reject workflow and remarks.
- V14.7.7 five premium Pulse pages and collapsible sidebar behavior.
- Plan/capability-aware route protection.
- Binance secret encryption and execution safeguards.
- MySQL-first runtime and non-destructive repair path.
- Existing Mobile API/OpenAPI operation set; OpenAPI build metadata is advanced to 14.7.8 with no breaking API path removal.

## Acceptance checks

Run after deployment:

```bash
php artisan optimize:clear
php artisan abs:doctor
php artisan abs:view-audit
php artisan test --filter=PulsePremiumPagesApiTest
node scripts/static-release-audit.mjs
node tests/Visual/verify-premium-contract.mjs
node tests/Visual/verify-v1478-authenticated-experience.mjs
```

The package-level checks can run without a configured production database. Runtime Laravel checks require Composer dependencies and the intended MySQL environment.
