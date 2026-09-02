@echo off
setlocal
where flutter >nul 2>nul || (echo ERROR: Flutter is not installed or not on PATH.& exit /b 1)
python tool\validate_source.py || exit /b 1
flutter pub get || exit /b 1
flutter analyze || exit /b 1
flutter test || exit /b 1
flutter build apk --release || exit /b 1
flutter build appbundle --release || exit /b 1
echo.
echo Android release build PASSED.
echo APK: build\app\outputs\flutter-apk\app-release.apk
echo AAB: build\app\outputs\bundle\release\app-release.aab
pause
