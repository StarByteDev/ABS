# Pulse Flutter Mobile V1.6.4+164 — Validation Report

**Target backend:** ABS V15.7.4  
**Production API:** `https://alphablocksolutions.com/api/v1`

## Release checks completed in this packaging environment

- Offline source validator: **680/680 checks passed**.
- Runtime Dart source contains no user-facing `ABS Pulse` product name.
- Android/iOS display identity and native repair script are pinned to **Pulse**.
- Existing Android application ID remains `com.alphablocksolutions.abs` for update compatibility.
- Calendar historical/upcoming requests remain separated (45-day history / 60-day upcoming window).
- Calendar response discovery recursively scans nested response collections.
- Duplicate event/release payloads are merged by event identity so richer release figures are preserved.
- Result-only provider fragments carrying an event ID plus release figures can merge into their schedule row.
- Calendar value extraction supports direct aliases, case/style-normalized aliases, nested `{value, unit}` maps, labelled value arrays and labelled summary text.
- Generic bare `value` is not treated as Actual, avoiding unrelated provider values being displayed as release results.
- Past missing Actual is labelled `Not reported`; future missing Actual remains `Pending`; missing Previous/Forecast is labelled `Not provided`.
- Added source tests for standard V15 fields, nested figure maps, labelled arrays and summary fallback parsing.
- V1.6.3 Direct USDT membership payment and Find Best Signal code paths remain present.
- V1.6.2 registration phone, activation, market overview and membership-state fixes remain present.

## Important data rule

Pulse only displays release values supplied by the ABS/backend provider. The mobile client does **not** invent Actual, Forecast or Previous values. V1.6.4 is designed to recover values that were previously missed because of provider response shape differences; if the backend truly omits a figure, the UI states that explicitly instead of showing a misleading value.

## Live endpoint limitation in this environment

The packaging runtime cannot resolve/access `alphablocksolutions.com`, so the live production endpoint could not be queried directly from this environment. The fix therefore hardens the client against the known ABS V15.7.4 API contract and provider-shape variants already represented in the project source/history. Final comparison against the live web calendar should be performed on the local emulator/device.

## Native Flutter validation still required locally

Flutter/Dart SDK is not installed in the packaging runtime. Before live deployment run:

```bash
flutter clean
flutter pub get
flutter analyze
flutter test
flutter run
```

Then compare several **News → Calendar → Previous** events directly with the web calendar, especially rate decisions, employment and inflation releases.
