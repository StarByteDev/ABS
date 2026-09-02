# ABS V14.7.2 Validation Report

Validated on 23 August 2026 before distribution packaging.

## Static application validation

- PHP syntax: 236 PHP files passed `php -l`.
- JavaScript syntax: 5 JavaScript files passed `node --check`.
- CSS structural validation: 8 CSS files passed balanced-brace validation.
- OpenAPI: `docs/openapi.yaml` parsed successfully as OpenAPI 3.1.0 with 87 paths.
- `resources/js/app.js` and `public/assets/js/abs-app.js`: byte-for-byte synchronized.
- `resources/css/home-final.css` and `public/assets/css/abs-home-final.css`: byte-for-byte synchronized.
- `resources/css/abs-app.css` and `public/assets/css/abs-app.css`: byte-for-byte synchronized.

## Homepage live-data wiring

Validated view hooks + JavaScript updates for:

- Total Market Cap
- 24H Trading Volume
- BTC Dominance
- Fear & Greed
- 24H Long Liquidations
- 24H Short Liquidations
- Open Interest
- Funding Rate
- Long / Short Ratio
- Perpetual Premium Basis
- Top Crypto Industries
- Top Gainers
- Top Losers

Validated independent fallback paths for browser-side/global feeds plus server-side Alternative.me and OKX fallbacks.

## Database/update administration

Validated protected Admin routes and controller/view presence for:

- Apply Latest Database Update
- Repair Tables & Columns
- Content & Configuration Sync
- Pending Migrations
- Clear Application Caches
- Refresh & Test Market Providers

The update workflow attempts a database-only safety backup before schema/content-changing actions. Routine seeding preserves an existing administrator password.

## Runtime limitation of packaging environment

The distribution environment does not contain the project's Composer `vendor/` directory and does not permit application-level outbound network requests, so a full Laravel HTTP boot and live provider request cannot be executed here. The build therefore includes both `php artisan abs:market-test` and Admin → Database & Updates → Refresh & Test Market Providers for the actual HostGator runtime acceptance test. Public provider contracts used by the fallback implementation were reviewed against their published endpoints.
