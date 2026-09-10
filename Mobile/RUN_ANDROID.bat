@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"

echo ===============================================================
echo   ABS Pulse - Android Emulator Runner
echo   V1.3.2 - Rewarded Access Motion & Free Signal Recovery
echo ===============================================================
echo.

where flutter >nul 2>nul || (
  echo ERROR: Flutter is not on PATH.
  echo Install/update Flutter first, then reopen the terminal.
  exit /b 1
)
where python >nul 2>nul || (
  echo ERROR: Python is not on PATH.
  exit /b 1
)

for /f "delims=" %%F in ('where flutter 2^>nul') do if not defined FLUTTER_EXE set "FLUTTER_EXE=%%F"
for %%D in ("!FLUTTER_EXE!") do set "FLUTTER_BIN=%%~dpD"
for %%D in ("!FLUTTER_BIN!..") do set "FLUTTER_SDK=%%~fD"

echo [1/9] Active Flutter SDK:
echo       !FLUTTER_SDK!
flutter --version || exit /b 1
echo.

if not exist android\app\build.gradle.kts (
  echo ERROR: Included Android host is missing. Re-extract the V1.3.2 ZIP.
  exit /b 1
)
if not exist android\gradlew.bat (
  echo [2/9] Completing Gradle wrapper with your installed Flutter version...
  flutter create --platforms=android --org com.alphablocksolutions . || exit /b 1
) else (
  echo [2/9] Using included hard-fixed ABS Pulse Android host.
)

REM Remove stale machine-specific SDK pointer before rewriting it.
if exist android\local.properties del /q android\local.properties

set "ANDROID_SDK=%ANDROID_SDK_ROOT%"
if not defined ANDROID_SDK set "ANDROID_SDK=%ANDROID_HOME%"
if not defined ANDROID_SDK set "ANDROID_SDK=%LOCALAPPDATA%\Android\Sdk"

set "ABS_FLUTTER_SDK=!FLUTTER_SDK!"
set "ABS_ANDROID_SDK=!ANDROID_SDK!"

echo [3/9] Applying permanent ABS Android configuration...
python tool\configure_native.py || exit /b 1

echo [4/9] Removing stale Dart/Gradle build state...
if exist .dart_tool rmdir /s /q .dart_tool
if exist build rmdir /s /q build
if exist android\.gradle rmdir /s /q android\.gradle
flutter clean || exit /b 1

echo [5/9] Resolving pinned compatible packages...
flutter pub get || exit /b 1

echo [6/9] Confirming lifecycle compatibility pin...
flutter pub deps | findstr /C:"flutter_plugin_android_lifecycle 2.0.26" >nul || (
  echo ERROR: Expected flutter_plugin_android_lifecycle 2.0.26 was not selected.
  echo Run: flutter pub get
  exit /b 1
)
echo       lifecycle plugin: 2.0.26 OK

echo [7/9] Android SDK / NDK configuration:
findstr /C:"compileSdk = 36" android\app\build.gradle.kts
findstr /C:"minSdk = 23" android\app\build.gradle.kts
findstr /C:"ndkVersion = \"27.0.12077973\"" android\app\build.gradle.kts

echo [8/9] Connected devices:
flutter devices

echo [8.5/9] Removing old debug install (helps refresh launcher name)...
for /f "tokens=1" %%A in ('where adb 2^>nul') do if not defined ADB_EXE set "ADB_EXE=%%A"
if defined ADB_EXE (
  "!ADB_EXE!" uninstall com.alphablocksolutions.abs >nul 2>nul
  "!ADB_EXE!" uninstall com.alphablocksolutions.abs_mobile >nul 2>nul
  "!ADB_EXE!" uninstall com.alphablocksolutions.absmobile >nul 2>nul
)

echo [9/9] Launching ABS Pulse...
flutter run
