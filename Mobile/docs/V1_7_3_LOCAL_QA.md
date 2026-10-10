# Pulse Flutter V1.7.3+173 — Local QA & release gate

Source baseline: Pulse V1.7.2+172, backend contract from ABS V15.9.1. All original files, Firebase and AdMob integrations remain in the source archive.

## Why this release exists

Home previously overlapped with Markets (BTC spot price, dominance, global volume, watchlist). Portfolio did not fetch `/private/transactions` and lacked detailed cash-out tracking. These are corrected at the application source level.

## Navigation and market behavior

- **Home:** Market Pulse, derivatives liquidation total/composition, long/short ratio, open interest, funding, volatility index, live qualified opportunities, upcoming high/medium macro events, Free Signal. No spot market cap, global volume, BTC dominance, spot watchlist or best setup teaser.
- **Markets:** Market cap, 24h global volume, BTC dominance, stablecoin flows, market breadth, spot BTC chart, sectors, gainers and losers, watchlist, headlines. No derivative liquidation/funding/positioning panel.
- **Free Signal:** The pre-existing rewarded ad, verification, 30-minute cooldown, entry-watch fallback and guest handling are retained.

## Portfolio API coverage

- `GET /api/v1/private/account` — source of truth for remaining principal, lifetime total profit, cash profit paid, profit reinvested, capital withdrawn, reported value, agreement, provisional monthly accrual, monthly performance and 12-month activity chart.
- `GET /api/v1/private/transactions?per_page=100&page=N` — full available ledger, with Load older transactions pagination and filters. Never infer lifetime totals by summing one page.
- `GET /api/v1/private/statements?per_page=50` and `GET /api/v1/private/statements/{id}` — official published reporting.
- `GET /api/v1/private/requests?per_page=50` — request tracking; existing request POST/cancel endpoints retained.

**Accounting rules:** `profit_paid` is external profit distributed; `profit_reinvested` is not cash paid. `capital_withdrawal` is principal returned. `mtd_provisional` is not paid cash. Historical `performance_history.posted` is monthly profit, not the historical account market valuation. The activity chart's `performance` metric is not the cash-payout figure.

## Local commands

```powershell
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run
```

Android Gradle 8.14.3, Android Gradle Plugin 8.12.2 and Kotlin 2.2.20 were preserved. Use the *existing signing key* and production AdMob configuration before release. Do not submit with test AdMob IDs, an unapproved package name or a different signing identity.

## Mandatory device acceptance tests (cannot be performed without a local Flutter SDK and accounts)

1. Guest: Home / Markets / Free Signal / Account work, no investor balances displayed. Rewarded ad Free Signal retains cooldown.
2. Non-investor account: Portfolio is access-gated; no private data exposed.
3. Investor account: reconcile principal, total profit, paid profit, reinvestment, capital withdrawals and provisional month-to-date with the same account in the ABS V15.9.1 website. Test an investor with more than 100 transactions and use **Load older transactions**.
4. Verify historical statements and open request statuses against the website. Submit a small test portfolio-review request and cancel *only when status is submitted*. Requests create records; they do not transfer funds.
5. Device widths: narrow 320px, 390px and tablet. Rotate; check KPI cards, long financial amounts, all five portfolio tabs and cash-flow filters for overflow.
6. Pull-to-refresh each Home, Markets and Portfolio screen. Simulate API timeouts/offline state and ensure no fabricated values appear.
7. Confirm Firebase notification deep links, production Google Ads placements, wallet UI and existing trade/signal screens are unaffected.
8. Run signed release compilation using original keystore before publishing to Play Store. Confirm exact `applicationId` and signing certificate.

**Status:** Code-level checks and ZIP integrity only. This is not a compiled/device-certified APK/AAB. Publish only after above acceptance tests succeed.
