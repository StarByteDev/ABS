# Pulse V1.7.0 — Kotlin compatibility hotfix (ABS backend V15.9.1)

## Reason
Flutter's Android build check rejects Kotlin Gradle Plugin 2.1.0; the screenshot requires 2.2.20 or higher.

## Actual change
- `android/settings.gradle.kts`: Kotlin Gradle Plugin **2.1.0 => 2.2.20**.
- Retain Gradle **8.14.3**, Android Gradle Plugin **8.12.2**, existing JDK 17 target, Google Ads, Firebase settings, application ID, versionCode and Dart source.
- Gradle 9.1 / AGP 9.0.1 warnings are advisory at this stage. Do **not** migrate to AGP 9 just to silence them.

## Usage
For full ZIP: extract into a fresh folder (or back up your current one), open its root with VS Code, then run:

```powershell
flutter clean
flutter pub get
flutter run
```

For fix-only ZIP: extract the `android/settings.gradle.kts` file into the **root of the existing Flutter project**, overwriting that single relative file; then run the commands above.

If another error occurs, share the first `* What went wrong` block. This source has **not** been compiled with Flutter in this container because Flutter/Android SDK are not installed.

## Update safety
Keep your existing release-signing key, Firebase config, AdMob IDs and application ID. Neither the package name nor release version is changed by this patch.
