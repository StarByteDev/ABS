# Pulse V1.7.2+172 — Implementation and local QA

## Approved composition

Five tabs for everyone, with no role-choice onboarding:

1. **Home**: futures/day-trading command center: ABS Market Pulse, liquidation total + 24h long/short composition, long-short ratio with derived share, open interest, funding rate, 24h volume, volatility, fear/greed, BTC dominance and BTC price; active qualified member signals, watchlist, economic events and **Free Signal CTA**.
2. **Markets**: global market cap and cap change, 24h volume, dominance, sentiment, stablecoin flow, market breadth, real BTC chart, core markets, sectors, gainers/losers, watchlist and headlines. This screen intentionally differs from Home.
3. **Free Signal**: rewarded-ad server session and claim, visitor continuity, server-side cooldown and qualified-signal/Entry-Watch/BTC context fallback retained from production. No signal is manufactured by UI.
4. **Portfolio**: backend-permission-gated private investor portfolio with Overview, Performance, Statements and Requests. No investor data is shown if unauthorized; no self-selected role elevation.
5. **Account**: premium overview and shortcuts, with all prior trading/account screens accessible through **All account & trading tools**.

News remains accessible from Markets, Home economic events, Account, and notification taps. Active-signal notifications open the existing Signals screen. Private-investor notifications land in Portfolio.

## Production preservation

- Android application ID `com.alphablocksolutions.abs` and internal Dart package `abs_pulse` unchanged.
- Existing Firebase Android/iOS files, AdMob integration, rewarded ad verification, subscription logic and backend client unchanged.
- Existing Gradle 8.14.3 / AGP 8.12.2 / Kotlin 2.2.20 configuration unchanged from prior corrected release.
- Published signing key is NOT included in source; continue using the existing release signing key/keystore.
- No hardcoded mockup prices, ROI, guaranteed results or asset allocations. Unknown fields show `—`.
- One inline banner remains on Home under the previous policy. The investor Portfolio remains free of ad slots.

## Mandatory local validation (not yet run in this environment)

```
flutter --version
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run
```

Test on Android at 360px/390px/430px widths and verify no RenderFlex overflow. Verify actual finance data with staging credentials for both investor and non-investor accounts. Inspect Home/Markets distinction; Free Signal ad reward, repeated refresh and cooldown; Portfolio authorization; market chart timeframes; requests/statements; FCM destination; all Account shortcuts. Verify notification permissions and Google Ads production placements without using real ad unit IDs in debug.

To generate an APK for local installation after tests:

```
flutter build apk --debug
```

Android release updates require the same package ID **and the original production signing key**, plus incremented build number 172. Do not deploy before testing Play policy, Firebase delivery, private investment workflow and data correctness against ABS V15.9.1.

## Test status

Archive integrity and custom static source contract checks: completed by author. Flutter SDK not available in author environment, therefore `flutter analyze`, `flutter test`, Android build, rendering and hardware testing **not executed**. This is a complete source candidate, not a certified release APK/AAB.
