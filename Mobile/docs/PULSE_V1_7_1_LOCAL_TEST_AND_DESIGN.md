# Pulse Flutter V1.7.1+171 — Approved Redesign and Local Test

## What changed

- Premium dark navy, gold, and electric-blue design based on the approved five-screen mockup set in `docs/approved_mockups/`.
- One-time investor/trader preference selection. ABS's server determines investor authorization; choice does not grant permission.
- Investor-first Home / Portfolio / Statements / News / Account navigation for eligible investors, plus Invest/Trade switch.
- Trader Home / Pulse / Signals / News / Account navigation.
- **Free Signal has a visible link in both home headers and both home dashboards, plus Account > Pulse trading > Free Signal.** All entry points open the existing native rewarded-ad flow, visitor state, 30-minute cooldown, claim verification, qualified-signal / Entry Watch / BTC fallback, and sharing.
- Trader trade-plan details use the original live signal feed (entry, targets, stop, strategy votes) and keep the read-only risk-planning calculator and alerts.
- Existing scanner, full signals, exchange positions, trade history, watchlists, research, profile, subscriptions, notifications, support, Firebase and AdMob remain in source.
- Backend stays ABS Web/Backend V15.9.1.

## Accuracy notes — important

- Screenshots in this folder are **visual mockups only**, with illustrative numbers. They are NOT app screens or images used as Flutter UI backdrops.
- Investor values, profit history, agreement status and statement rows are read from authenticated ABS APIs. No sample PDF or mockup account figures are injected.
- ABS V15.9.1 does not provide a verified per-asset investor allocation series to this mobile client. Show a clear no-data explanation instead of inventing allocation percentages.
- The investor line chart is explicitly labelled posted monthly profit; it is **not** a historical account-value chart. The monthly bars also show posted profit, not monthly percentage returns.
- The trader section does not fabricate win rate, profit in R, or active signals when unavailable; separate authenticated reporting screens remain accessible in Account.
- The risk calculator is for planning only. It does not submit orders, and it excludes fees, slippage, and leverage.
- The Free Signal rewarded ad opens the existing flow. On an emulator, SDK test ads are expected by default.

## Local Windows build (VS Code)

1. Extract the entire ZIP into a clean directory. Do NOT copy only lib/ into a different Android project.
2. `flutter doctor -v`
3. `flutter clean`
4. `flutter pub get`
5. `flutter analyze`
6. `flutter test`
7. `flutter run`

Flutter/Android tooling was unavailable in the authoring runtime. `flutter analyze`, `flutter test`, and APK/AAB compilation **have not yet been executed here**. These steps are required on your computer before using the version as a production update.

### Build configuration already in the ZIP

- Gradle Wrapper: 8.14.3
- Android Gradle Plugin: 8.12.2
- Kotlin: 2.2.20
- Flutter app version: 1.7.1+171
- Android application ID: unchanged (existing Play Store update compatibility)

If Flutter reports newer compatibility warnings, distinguish warning from build-stopping failure. Do not use `--android-skip-build-dependency-validation` to hide version errors.

### Ads, signing, Firebase

- AdMob consent, placements, and the original rewarded flow remain intact.
- In **debug** the default Google test ad unit IDs are used. The production AAB must set `--dart-define=ABS_ADS_USE_TEST_IDS=false` and supply **your actual production unit IDs** as described in `lib/core/ads/ad_config.dart`.
- Use your original Android release keystore and `key.properties` / signing configuration. This package does not create or recover a private signing key.
- Firebase services/config files are carried forward; verify Google/Firebase project configuration and receive a real test FCM notification before publishing.
- Preserve online Android application ID and ensure published versionCode 171 is greater than currently deployed Play Store versionCode.

## Device-level checklist

- New investor: first login -> Investor onboarding -> Investor home, balance from `/private/account`; switch to Trader and back.
- Investor home: refresh, balance/profit distinction, monthly posted profit bar/line, agreement and missing allocation notice.
- Investor requests: top-up / principal withdrawal / account review; inspect in admin; no direct capital movement.
- Investor portfolio: overview, monthly results, statements, requests, statement details and cancellation (if allowed).
- Trader: pulse live feed, signals list, best signal, detail, copy, level alert, scanner/trading tools from Account.
- Free Signal: home header and Free Signal card both route to reward; handle ad rewarded/completed, ad unavailable, cooldown, Entry Watch, BTC fallback, share.
- News: open economic calendar, impact filters, alerts.
- Android/iOS: verify 320/390/430px sizes, text scaling, keyboard/scroll, network errors and offline views.
- Firebase and AdMob: actual registered test device, notifications, consent, subscriptions and ad unit IDs.

## Production safety

No backend, investor data, SQL, or trading strategy updates are included. This is a source-only mobile UI revision. Keep V1.7.0 Kotlin-fixed ZIP as rollback source. Test an internal Play Store track before rolling out.
