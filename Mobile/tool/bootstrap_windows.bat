@echo off
setlocal
where flutter >nul 2>nul || (echo ERROR: Flutter is not installed or not on PATH.& exit /b 1)
where dart >nul 2>nul || (echo ERROR: Dart is not available from Flutter SDK.& exit /b 1)

echo [1/7] Creating/updating Android and iOS host projects...
flutter create --platforms=android,ios --org com.alphablocksolutions . || exit /b 1

echo [2/7] Applying ABS native identity and release network permission...
python tool\configure_native.py || exit /b 1

echo [3/7] Getting packages...
flutter pub get || exit /b 1

echo [4/7] Generating ABS launcher icons...
dart run flutter_launcher_icons || exit /b 1

echo [5/7] Generating ABS splash screens...
dart run flutter_native_splash:create || exit /b 1

echo [6/7] Running Flutter analyzer...
flutter analyze || exit /b 1

echo [7/7] Running Flutter tests...
flutter test || exit /b 1

echo.
echo ABS Flutter bootstrap PASSED.
echo Confirm Android applicationId / iOS bundle identifier before store release.
pause
