# ABS V15.0.9 Validation Report

## Release scope

ABS V15.0.9 is a visual-consistency and dashboard-polish release built on V15.0.8. It does not change the Pulse scanner engine, strategy calculations, rewarded-ad credit service, USDT→Sparks→package commerce boundary, scheduler rules, or database schema.

## Admin consistency

- Final stylesheet `public/assets/css/admin-executive-v1509.css` loads after all prior Admin layers.
- All full Admin Blade pages use `admin.layout`; shared partials remain embedded in that shell.
- Legacy white/light table cells, filters, report cards, forms, pagination, popovers and list surfaces are explicitly overridden with the approved ABS navy/orange-gold/cyan palette.
- The corporate ABS logo is rendered once in the visible Admin shell sidebar. The ABS Pulse strip is text-led and still states `A PRODUCT OF ALPHA BLOCK SOLUTIONS`.

## Dashboard corrections

- Pulse Intelligence primary series is rendered as a line/area trend rather than sparse vertical bars.
- Signal Oversight hides the chart engine's duplicate legend and uses the dedicated lifecycle list, preventing overlap.
- Member Growth bar labels are thinned to a maximum of roughly six time labels plus the final point.
- Primary, secondary and bottom dashboard cards use normalized alignment/heights.
- Plan expiry values use whole days rather than Carbon fractional-day values.
- Responsive top-bar/date controls were tightened to avoid desktop collisions.

## Protected functionality checks

- `PulseScannerService.php` SHA-256 matches the V15.0.8 package exactly: `2bfa32bd082cbe96c0ff36991113995c6b76fa43faf1566663f805fb91e237db`.
- `PulseRewardedAdService.php` SHA-256 matches the V15.0.8 package exactly: `c6e9416ca516611b31cc34a068b0f8f5678a15319f5379626fa19571d923aa06`.
- V15.0.8 rewarded-ad routes, member Watch & Earn JavaScript, mobile AdMob config/SSV service and migration remain present.

## Validation executed

- PHP syntax: 199 PHP files passed `php -l`.
- JavaScript syntax: modified Admin analytics and rewarded-ad client scripts passed `node --check`.
- Static release audit: 90 Blade files, 185 named routes, 168 named-route references, 124 mobile/API operations, 73 static view targets and 206 Blade references passed.
- V15.0.7 premium executive compatibility contract passed.
- V15.0.8 rewarded-ads release audit passed.
- V15.0.9 Admin consistency audit passed across 35 Admin Blade files (full pages plus partials counted by the scanner; partials are excluded from the layout-extension requirement).
- V15.0.9 CSS brace/syntax sanity check passed.

## Database

No new database migration is required when upgrading from V15.0.8. Sites upgrading from V15.0.7 or earlier still need the V15.0.8 rewarded-ad migration (or the included phpMyAdmin fallback SQL / protected schema repair).
