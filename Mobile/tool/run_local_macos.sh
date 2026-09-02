#!/usr/bin/env bash
set -euo pipefail
command -v flutter >/dev/null || { echo "ERROR: Flutter is not installed or not on PATH."; exit 1; }
echo "Starting ABS Mobile against Laravel at 127.0.0.1:8000 for iOS Simulator..."
flutter run --dart-define=ABS_API_BASE_URL=http://127.0.0.1:8000/api/v1
