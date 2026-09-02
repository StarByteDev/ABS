ABS Flutter Mobile V1.0.2 Root Emulator Compatibility Build
============================================================
Backend: ABS V14.9.2+

IMPORTANT
---------
This ZIP is intentionally packaged WITHOUT a wrapper directory.
Extract it into a NEW EMPTY project folder. pubspec.yaml, lib, assets, tool,
and RUN_ANDROID.bat will appear directly at the extraction root.

Windows emulator quick start
----------------------------
1. Start your Android emulator.
2. Open CMD in the extracted project root.
3. Run:

   RUN_ANDROID.bat

The runner automatically:
- uses the Flutter executable currently on PATH;
- removes a stale android/local.properties Flutter SDK pointer;
- creates/repairs the Android host;
- permanently sets compileSdk 36;
- permanently sets NDK 27.0.12077973;
- pins flutter_plugin_android_lifecycle 2.0.26 for Flutter 3.24+ compatibility;
- clears stale Gradle/Dart state;
- runs flutter pub get;
- launches the app.

Flutter requirement
-------------------
Use Flutter 3.24 or newer (Dart 3.5+). Do not use an old Flutter SDK cached in
android/local.properties. RUN_ANDROID.bat repairs that automatically.

Do NOT run `flutter pub upgrade --major-versions` for this release.
