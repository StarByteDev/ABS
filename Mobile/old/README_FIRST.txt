Signals_ABS V11.11 Flutter Mobile App
====================================

This is a Flutter mobile application package for Android and iOS based on the Signals_ABS V11.11 Laravel backend.

Important:
This Flutter app is designed as a mobile client. It needs API endpoints from your Laravel backend.
If your Laravel project currently only has Blade/web routes, add the included Laravel API bridge file from:

laravel_api_bridge/routes_api_addition.php

to your Laravel routes/api.php or routes/web.php with API middleware adjusted as needed.

Main Mobile Features:
- Login
- Register
- Dashboard stats
- Signal list
- Active signal count
- Auto scan trigger
- Batch scan progress display
- Current batch and last batch display
- Subscription plans
- USDT payment submission
- Telegram settings
- Profile settings
- Risk notice
- Clean V11.11 product UI

Flutter Setup:
1. Install Flutter SDK.
2. Open this folder in VSCode or Android Studio.
3. Run:
   flutter pub get
4. Edit API base URL:
   lib/core/api_config.dart
5. Run:
   flutter run

Android local backend note:
If Laravel is running on your PC:
- Android emulator should use: http://10.0.2.2:8000
- Physical phone should use your PC LAN IP, example: http://192.168.1.50:8000
- iOS simulator can use: http://127.0.0.1:8000

Build:
Android APK:
flutter build apk --release

iOS:
flutter build ios --release

Recommended Laravel API:
- POST /api/mobile/login
- POST /api/mobile/register
- POST /api/mobile/logout
- GET  /api/mobile/dashboard
- POST /api/mobile/scan-next-batch
- GET  /api/mobile/subscription-plans
- POST /api/mobile/subscription-payment
- GET  /api/mobile/profile
- POST /api/mobile/profile/settings

