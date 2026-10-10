# Pulse V1.7.0 Android Gradle compatibility hotfix

## Why your `flutter run` failed
The original Pulse Flutter V1.7.0+170 project used Gradle 8.11.1, but your installed Flutter tool requires Gradle 8.14.0 or newer. Android Gradle Plugin (AGP) 8.9.1 was also below the API 36 support line while the project compiles Android API 36.

## Fix applied
- android/gradle/wrapper/gradle-wrapper.properties: Gradle 8.11.1 -> 8.14.3
- android/settings.gradle.kts: AGP 8.9.1 -> 8.12.2
- Kept Kotlin 2.1.0, Java 17 target, Android API 36, app id com.alphablocksolutions.abs and app version 1.7.0+170.
- All Dart source, Firebase files and Google AdMob integration left untouched.
- No bypass of Flutter's Gradle dependency validation, no AGP 9/new-DSL opt-out is introduced (this project uses AGP 8.x).

## Test on Windows (VS Code terminal)
1. Back up your existing Mobile project; extract this complete ZIP into a clean project folder or replace the two files from the fix-only ZIP.
2. Start VS Code terminal at the Flutter project root (where pubspec.yaml is).
3. `flutter doctor -v` (confirm Android SDK and Java 17 available)
4. `flutter clean`
5. `flutter pub get`
6. `flutter run`

The first run needs internet to download Gradle 8.14.3 and AGP 8.12.2. Android SDK API 36 and NDK 27.0.12077973 must be installed or downloaded.

## If the error persists
- Run `flutter --version`, `flutter doctor -v` and `flutter run -v` to capture the *new* error.
- Do not use `--android-skip-build-dependency-validation` as the routine solution.
- If AGP 9 warnings appear, they are advisory for future upgrades: keep AGP 8.12.2 and do not enable AGP 9/newDsl migration without a separate compatibility review.
- For a Google Play update, restore your **existing release signing key**, keep the applicationId the same, confirm production AdMob IDs, and increment the version code for the actual new store release. Do not ship a debug-signed app.

## Verification scope
ZIP CRC/integrity, exact targeted substitutions and preservation of application source/assets validated locally. A Flutter/Gradle build was NOT run here because the Flutter and Android SDK toolchains are not installed. This is a compile hotfix *candidate*; test with `flutter run` before publishing.
