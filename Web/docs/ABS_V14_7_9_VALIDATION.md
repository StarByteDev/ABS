# ABS V14.7.9 Validation Record

**Date:** 24 August 2026

## Verified correction scope

- Normal non-admin login no longer uses a stale Laravel `intended` URL for its default landing.
- Active Pulse users route directly to `/pulse/dashboard`.
- `/dashboard` never renders the old member dashboard; it redirects to the premium Pulse Dashboard or Pulse Access.
- The compatibility dashboard view itself uses `pulse.layout` as a defensive fallback.
- The finalized 12-item authenticated navigation is always present; unavailable plan capabilities are shown as locked rather than removed.
- Authenticated Membership & Billing and checkout screens use `pulse.layout`.
- Profile remains inside `pulse.layout`.
- Approved Dashboard, Market Scanner, Signals, Strategies and Trade Execution visual contract remains unchanged.
- Mobile/OpenAPI path parity remains 105 operations.

## Validation executed

| Check | Result |
| --- | --- |
| PHP syntax lint across 157 application/route/config/database/test PHP files | PASS |
| Static Blade/named-route/API parity audit | PASS — 79 Blade files, 146 named routes, 139 named-route references, 105 API operations |
| Approved premium visual contract | PASS — 262 checks across Dashboard, Scanner, Signals, Strategies and Trade Execution |
| V14.7.8 authenticated-experience compatibility verifier | PASS |
| V14.7.9 finalized logged-in-shell verifier | PASS |
| Release SHA manifest verification | PASS |

The validation container does not provide a Composer executable, so PHPUnit could not be launched in this packaging environment. The source includes updated feature tests covering direct Pulse login landing, `/dashboard` compatibility routing, stable locked navigation for inactive/restricted access, and membership-page shell consistency. Run the normal Laravel test suite on the target development machine after `composer install`.

## Deployment requirement

After replacing the files on an existing Laragon or HostGator installation, run `php artisan optimize:clear`. This is required to remove stale route/config/compiled-view caches from the previous deployed version. No database migration is introduced by V14.7.9.
