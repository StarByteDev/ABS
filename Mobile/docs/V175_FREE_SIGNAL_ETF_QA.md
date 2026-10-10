# Pulse V1.7.5 — Free Signal / BTC ETF local QA

Baseline: Pulse V1.7.4+174, target ABS V15.9.1. All Android/iOS and AdMob/Firebase settings carried forward.

## User-facing changes

- The *entire checkbox/consent row* is tappable (text and square), has clear dark-theme contrast, and is accessible to screen readers.
- A network/SDK exception or a rewarded-ad load timeout can no longer leave the UI indefinitely in a busy state. The visitor may retry without bypassing ABS's server-enforced reward/cooldown logic.
- The Home ETF card clearly distinguishes an absent route, missing provider key, provider outage, and no recent completed session. A **View published BTC ETF flows** button opens Farside's official page in the device browser, even if the integrated numeric panel is unavailable.
- No ETF figures are fabricated. The numeric card still reads `/api/v1/market/etf-flows` (optional V15.9.1 server patch), which requires an authorized CoinGlass key.

## Local QA

```bash
flutter clean
flutter pub get
flutter analyze
flutter test test/pulse_v175_consent_etf_test.dart
flutter test
flutter run
```

1. Open **Free Signal**, tap the words **I agree to watch a rewarded ad for one free reveal.** Verify the checkbox becomes checked; tap the square and verify it becomes unchecked. Re-check.
2. Press **Watch Ad & Unlock Signal** on Android (use Google's test rewarded ad ID for debug). Check that an ad appears, that closing without a reward does not activate a 30-minute cooldown, and that a fully earned reward is reconciled with the ABS claim API.
3. Test with Internet off: the page must return to a usable state and show an error, not stay loading forever.
4. Test with the ads SDK failing to load an ad: after at most 25 seconds, the UI should unlock and explain the issue.
5. Under **Home → Spot Bitcoin ETF flows**, press **View published BTC ETF flows**: Farside opens externally, not in a fake in-app chart.
6. If the backend patch is not installed, the card reports **endpoint is not installed**. If installed but `COINGLASS_API_KEY` is empty, it reports **provider not configured**. With a licensed key and a successful provider response it shows the actual USD amount, latest flow date and source.
7. Confirm existing investor accounting, Free Signal visitor continuity, Firebase messaging, AdMob test IDs, and Android app ID remain unchanged.

## Configure numeric ETF values (ABS server)

The **separate** additive backend patch is `ABS_V15_9_1_OPTIONAL_COINGLASS_BTC_ETF_FLOW_BACKEND_PATCH.zip`.

1. Back up current V15.9.1 files, review/merge the four changed paths into a staging copy (do not overwrite unrelated backend code).
2. Get a licensed CoinGlass API key and set `COINGLASS_API_KEY=...` **on the Laravel server only**. Never embed it in Flutter or share the value in screenshots.
3. Run `php artisan config:clear` and `php artisan route:clear`.
4. Open `https://alphablocksolutions.com/api/v1/market/etf-flows` and inspect `data.status`: `available`, `provider_not_configured`, `provider_unavailable`, or `no_recent_trading_session`.
5. Refresh mobile Home. Weekend and holiday flows are expected to reflect the latest *completed* ETF trading session, not a synthetic live feed.

Third-party figures may require licensing for in-app redistribution; do not scrape Farside's HTML into the app as a replacement for a licensed feed without permission. Its link is a direct publisher visit only.

**Status:** Syntax/structure and ZIP integrity verified in this environment. Flutter SDK/emulator and live backend/device credentials were not available; complete the tests above before production deployment.
