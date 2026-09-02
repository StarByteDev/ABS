#!/usr/bin/env bash
set -euo pipefail
command -v flutter >/dev/null || { echo "ERROR: Flutter is not installed or not on PATH."; exit 1; }
python3 tool/validate_source.py
flutter pub get
flutter analyze
flutter test
flutter build apk --release
flutter build appbundle --release
echo "Android release build PASSED."
echo "APK: build/app/outputs/flutter-apk/app-release.apk"
echo "AAB: build/app/outputs/bundle/release/app-release.aab"
