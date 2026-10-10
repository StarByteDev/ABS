# Pulse V1.7.0+170 — Release & Device QA checklist

Target: ABS web/backend **V15.9.1**. Mobile baseline: uploaded `assets.zip` v1.6.5+165. Do not claim backend parity or production readiness without these tests.

## Required before compiling

- [ ] Restore the **original existing Play Store upload keystore** locally at its correct path and `android/key.properties`. This archive intentionally does not contain a signer. Verify the certificate SHA-256 matches your previously published Android app.
- [ ] Verify your actual AdMob App ID, banner/interstitial/rewarded/app-open units, consent setup and SDK policies. Sample IDs in the code are **test IDs**.
- [ ] Verify Firebase Android/iOS app identities and messaging credentials match the published Pulse app.
- [ ] Confirm application ID `com.alphablocksolutions.abs`, versionCode **170**, versionName **1.7.0** (monotonically increasing vs published).
- [ ] Confirm backend is on V15.9.1 including `/private/account`, `/private/statements`, `/private/requests`, `/pulse/signals/overview`, `/economic-calendar`.

## Compile & validate (on machine with Flutter SDK)

```bash
flutter clean
flutter pub get
flutter analyze
flutter test
flutter build appbundle --release \
  --dart-define=ABS_API_BASE_URL=https://alphablocksolutions.com/api/v1 \
  --dart-define=ABS_ADS_USE_TEST_IDS=false \
  --dart-define=ABS_ADMOB_BANNER_ANDROID=YOUR_BANNER_UNIT \
  --dart-define=ABS_ADMOB_INTERSTITIAL_ANDROID=YOUR_INTERSTITIAL_UNIT \
  --dart-define=ABS_ADMOB_REWARDED_ANDROID=YOUR_REWARDED_UNIT \
  --dart-define=ABS_ADMOB_APP_OPEN_ANDROID=YOUR_APP_OPEN_UNIT \
  --dart-define=ABS_ADS_ENABLED=true
```

Supply `admobAppId=YOUR_PRODUCTION_ADMOB_APP_ID` in the **local untracked `android/key.properties`** as supported by `android/app/build.gradle.kts`. The AdMob App ID and ad unit IDs are distinct.

## Critical investor tests

- [ ] Guest sees trader/public home; no investor data leaked.
- [ ] Trader-only account sees trader home and no investor controls.
- [ ] Private investor account sees onboarding once, then **Investor** home by default. Subsequent launch restores selected mode.
- [ ] Both mode and Account switcher work; navigation labels and destinations change correctly.
- [ ] Investor gets actual server account currency, principal, indicative value, monthly provisional, rate history, statements, transactions.
- [ ] Blank history/allocations have truthful empty states (no PDF example data copied into live screens).
- [ ] Top up request submits API `type=add_investment`; withdrawal submits `type=withdrawal`; support/review sends `type=portfolio_review`. Verify server review and email alerts.
- [ ] Reject zero/invalid amount, insufficient principal and excessive open requests with clear error.
- [ ] Statements show only authenticated investor data; direct API access to someone else's statement remains 403.
- [ ] Logout clears private data immediately; sign in as another investor does not show previous account's information or preference.

## Trader and content tests

- [ ] Pulse scanner/qualified signals/waiting signal/rewarded Free Signal still work.
- [ ] Signal detail renders entry/TP/SL, voting breakdown, sizing helper, copy text, alerts.
- [ ] Economic calendar impact filter shows High/Medium/Low correctly and accepts zero events.
- [ ] News, Watchlist, Account, membership/payment, positions, Binance Testnet/Live and reports remain accessible.
- [ ] AdMob banner/rewarded/interstitial/app-open behavior, privacy/consent, restrictions and reset cooldown continue working with production units.
- [ ] FCM token registration, foreground/background and tapped push notifications work. Investor alerts open a protected investor route only after login.

## Mobile visual and accessibility QA

Test on Android physical devices (small ~360px and larger ~440px), iOS simulator/physical devices and landscape/tablet as supported: onboarding, investor home, each portfolio tab, requests form with keyboard, trading home, signal detail, all existing legacy flows, notifications and ad overlays. Check contrast, overflow, screen-reader labels, offline retry, slow network and reauthentication.

## Rollback

Keep previously published v1.6.5+165 source, App Store/Play releases and signing key. Never delete live app user data/database in order to roll back. Mobile rollback feasibility depends on store rules and versionCode constraints. Release only after staging verification.
