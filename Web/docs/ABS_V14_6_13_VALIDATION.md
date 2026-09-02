# ABS V14.6.13 Validation Report

Date: 22 August 2026

## Confirmed corrections

- Futures Insights contains Open Interest, Funding Rate, Long / Short Ratio and Perp Premium Basis in one 2×2 card.
- Desktop homepage canvas uses `min(1860px, calc(100% - 24px))`.
- Fear & Greed uses a five-zone semicircular gauge with a live score-driven needle.
- Pulse Intelligence has a protected effective rate of 29 USDT/month when a legacy database contains zero.
- Pulse Professional has a protected effective rate of 79 USDT/month when a legacy database contains zero.
- Trial remains 0 / 7 days and Enterprise remains Custom Pricing.
- Checkout quotation logic uses the protected effective price, not a legacy zero database value.
- Database seeding and the included repair migration persist corrected paid-plan prices without overwriting administrator-managed non-zero prices.

## Static validation performed

- 212 PHP files passed `php -l`.
- 5 JavaScript files passed `node --check`.
- Homepage CSS parsed with zero top-level syntax errors.
- Source/public homepage CSS copies are identical.
- Required Futures Insights bindings and pricing-repair code paths were verified present.

## Runtime note

This packaging environment does not contain Composer/vendor dependencies, so the Laravel HTTP application and external live market providers could not be executed end-to-end here. On Laragon, run `php artisan abs:repair --seed` and `php artisan abs:market-test` after setup; these commands are already included in the build.
