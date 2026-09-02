# ABS V14.6.14 Validation Report

Date: 22 August 2026

## Static validation completed

- PHP syntax: 212 PHP files passed `php -l`.
- JavaScript syntax: 5 JavaScript files passed `node --check`.
- CSS: `resources/css/home-final.css` and `public/assets/css/abs-home-final.css` parsed with zero stylesheet syntax errors.
- CSS deployment copies are byte-for-byte synchronized.
- Homepage Blade contains the new `final-advantage-copy` structure for all six advantage cards.
- Desktop side-card grid includes dedicated increased rows for Market Pulse and 24H Liquidations.
- Existing bindings for live long/short liquidations, OI, funding, Long/Short Ratio, Perp Premium Basis and Fear & Greed remain present.

## Runtime note

The distributable source does not include Composer `vendor/` dependencies, so a full Laravel browser boot was not executed inside the packaging container. Use the documented Laragon setup and run `php artisan abs:market-test` after `composer install` to validate provider connectivity on the target machine.
