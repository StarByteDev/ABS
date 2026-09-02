#!/usr/bin/env bash
set -euo pipefail
command -v flutter >/dev/null || { echo "ERROR: Flutter is not installed or not on PATH."; exit 1; }
command -v dart >/dev/null || { echo "ERROR: Dart is not available from the Flutter SDK."; exit 1; }

echo "[1/7] Creating/updating Android and iOS host projects..."
flutter create --platforms=android,ios --org com.alphablocksolutions .
echo "[2/7] Applying ABS native identity and release network permission..."
python3 tool/configure_native.py
echo "[3/7] Getting packages..."
flutter pub get
echo "[4/7] Generating ABS launcher icons..."
dart run flutter_launcher_icons
echo "[5/7] Generating ABS splash screens..."
dart run flutter_native_splash:create
echo "[6/7] Running Flutter analyzer..."
flutter analyze
echo "[7/7] Running Flutter tests..."
flutter test
echo "ABS Flutter bootstrap PASSED. Confirm bundle identifiers/signing before store release."
