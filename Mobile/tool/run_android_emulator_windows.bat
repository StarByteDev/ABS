@echo off
setlocal
where flutter >nul 2>nul || (echo ERROR: Flutter is not installed or not on PATH.& exit /b 1)
where python >nul 2>nul || (echo ERROR: Python is not installed or not on PATH.& exit /b 1)

if not exist android\app\build.gradle.kts (
  echo [1/6] Creating Android host...
  flutter create --platforms=android --org com.alphablocksolutions . || exit /b 1
) else (
  echo [1/6] Android host already exists.
)

echo [2/6] Applying ABS Android SDK/NDK and identity...
python tool\configure_native.py || exit /b 1

echo [3/6] Cleaning previous build...
flutter clean || exit /b 1

echo [4/6] Getting packages...
flutter pub get || exit /b 1

echo [5/6] Checking connected devices...
flutter devices

echo [6/6] Starting ABS Mobile on emulator/device...
flutter run
