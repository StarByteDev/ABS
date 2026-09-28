# Pulse Mobile V1.6.4+164

**Target backend:** ABS V15.7.4  
**Production API:** `https://alphablocksolutions.com/api/v1`

## Economic Calendar data completeness

V1.6.4 hardens the mobile calendar against the different response shapes used by the live ABS economic-calendar pipeline.

- Historical and upcoming windows remain separate so completed-release data is requested independently from future events.
- The client now recursively discovers calendar events inside nested `data`, `events`, `calendar`, `releases`, historical, upcoming and provider-specific wrappers instead of stopping at the first collection.
- Duplicate releases are merged rather than discarded. This matters when one copy contains schedule/context while another copy contains final release figures.
- Previous / Forecast / Actual support direct values, camelCase/snake_case aliases, nested figure maps such as `{ value, unit }`, labelled value arrays, and common provider aliases such as prior/expected/consensus/reported/released/result.
- A final safe parser can read labelled values from provider summaries such as `Previous: ... | Forecast: ... | Actual: ...` without treating ordinary explanatory prose as a value.
- Generic bare `value` is no longer treated as Actual, preventing an unrelated provider number from being shown as the release result.
- Future missing Actual values display `Pending`; historical missing Actual values display `Not reported`. Missing Previous/Forecast values display `Not provided` rather than a misleading blank dash.
- The client never fabricates an economic release number. When ABS/provider data genuinely does not contain a figure, the UI says so explicitly.

## Product branding

The mobile product is now displayed simply as **Pulse**.

- Android launcher name: `Pulse`
- iOS display/bundle name: `Pulse`
- Flutter application title/config: `Pulse`
- Intelligence header: `Pulse Intelligence`
- Sign-in, activation, sharing, About and account copy use `Pulse` instead of `ABS Pulse`.
- The parent company remains **Alpha Block Solutions** where company/legal/source context is appropriate.
- The Android application ID remains `com.alphablocksolutions.abs` so existing installations/update identity are not broken.
- The internal Dart package name remains `abs_pulse` to avoid unnecessary source/import migration; this is not user-visible.

## Preserved from V1.6.3

- True user-supplied template UI rebase.
- Home / Pulse / Free Signal / News / Account navigation.
- Direct USDT membership payment/verification workflow.
- Interactive Find Best Signal 15M + 4H workflow.
- Rewarded Free Signal, activation-limited access, registration phone capture, market overview fixes and V15.7.4 Private Investor support.

## Validation limitation

The packaging environment does not include Flutter/Dart, so native `flutter analyze`, `flutter test`, Android/iOS compilation and emulator execution must still be performed locally before deployment.
