# ABS Pulse Mobile V1.6.1+161

## Purpose
Compile hotfix for the true-template V1.6.0 rebase.

## Fixed
Flutter compilation failed because `AppState` was available from both:

- `template_rebase/state/app_state.dart`
- `google_mobile_ads` internal exports

`lib/main.dart` now imports only the ad symbol it needs:

```dart
import 'package:google_mobile_ads/google_mobile_ads.dart' show MobileAds;
```

This leaves `AppState` exclusively owned by the ABS Pulse template-rebase state layer.

## Unchanged
All V1.6.0 UI and ABS V15.7.4 integrations remain unchanged: Home / Pulse / Free Signal / News / Account, Calendar-first Intelligence, activation-limited access, trading tools, membership, support and Private Investor.

## Validation
The release validator checks the AppState/ad import collision in addition to the existing template, API, navigation, FontWeight, Color API, import-resolution and delimiter checks.

Native `flutter analyze`, `flutter test` and device compilation must still be executed on a machine with Flutter installed.
