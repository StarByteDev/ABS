# ABS V14.6.12 Final Build Review

- Long / Short Ratio and Perp Premium Basis now render as two bordered metric cards matching Open Interest and Funding Rate.
- V14.6.11 malformed CSS escape text was removed and replaced with valid stylesheet rules.
- Desktop content width is now capped at 1800px with reduced side gutters.
- No market-data calculation or provider behavior was changed.
- The confirmed homepage structure and all Pulse/Admin/MySQL functionality remain intact.


## Visual acceptance target

- V14.6.10 premium navy/gold/cyan homepage layout and wide/readable desktop composition remain unchanged.
- The existing Futures Insights card still shows only Open Interest (OI) and Funding Rate.
- One compact strip immediately beneath it now shows Long / Short Ratio and Perp Premium Basis.
- The new strip consumes the previously reserved empty space and does not increase the hero-board height or move the bottom coin-price strip.
- Existing Market Pulse, Market Sentiment, Long/Short 24H Liquidations, news, industries, market overview, gainers/losers, plans, CTA and footer remain unchanged.

## Functional review

- Binance Futures public market service now reads the global account long/short ratio.
- Perp Premium Basis is calculated from Binance Futures mark price and index price.
- Live homepage refresh updates both values.
- No database schema, Admin CMS, authentication, membership, Pulse execution/risk or MySQL/Laragon changes.

## Acceptance commands on Laragon

```bat
composer install
php artisan optimize:clear
php artisan abs:repair --seed
php artisan abs:doctor
php artisan abs:market-test
php artisan serve
```