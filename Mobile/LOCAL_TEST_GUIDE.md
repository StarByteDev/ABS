# ABS Pulse V1.3.5 Local Test Guide

This source connects to the live ABS V15.1.6 API by default. No web deployment is required.

## Windows Android emulator

1. Extract the ZIP into a new empty folder.
2. Install Flutter and Android Studio, then confirm `flutter doctor` is healthy.
3. Start an Android emulator.
4. Open the extracted folder and run:

```bat
RUN_ANDROID.bat
```

The runner repairs generated Android settings, installs Flutter packages, lists devices and starts the app.

## Manual Flutter run

```bash
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run --dart-define=ABS_API_BASE_URL=https://alphablocksolutions.com/api/v1
```

## Test checklist

- Continue as a guest and open **Free Signal**. Confirm the new compact premium layout fits above the navigation cleanly.
- Complete the consent and Google test rewarded ad, then claim the signal.
- Confirm the signal remains visible during the page session and the server cooldown appears after leaving it.
- Register or sign in, then verify Pulse dashboard, markets, watchlist, scanner and member signals.
- Open a signal and test native **Share** and **AI Explain** when the backend enables explanation.
- Check Reports for Overview, Signals, Strategies and Simulation across 7, 30 and 90 days.
- Open Plans and test the direct USDT quote/request flow with non-production data only.
- Connect Binance Testnet and verify readiness, scanner, trade review, positions, guarded close and reconciliation before any LIVE use.
- Open **News → CALENDAR** and test **Past / Today / Upcoming**, Impact and Currency filters. Confirm past events show **Actual / Forecast / Previous** where the backend supplies those values.
- Verify ABS News and Live Market Wire cards remain readable on smaller Android devices.
- Verify profile, sessions, devices, notifications, alerts, research, learning and private member screens allowed by the account.

## AdMob configuration

Google sample IDs are included so rewarded ads can be tested safely. Before publishing, replace the Android and iOS sample application IDs and provide the production rewarded-unit IDs:

```bash
flutter run \
  --dart-define=ABS_ADMOB_REWARDED_ANDROID=YOUR_ANDROID_REWARDED_UNIT \
  --dart-define=ABS_ADMOB_REWARDED_IOS=YOUR_IOS_REWARDED_UNIT
```

Keep ABS API, Binance and signing secrets outside Flutter source.

The included Gradle configuration uses Android debug signing when `android/key.properties` is absent. Add the private production keystore only on the authorized release machine.
