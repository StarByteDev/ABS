Pulse Trading Intelligence Mobile V13.1 Root Build
==================================================

Purpose
-------
This mobile root build is aligned with the deployed Pulse web build at:
https://pulse.alphablocksolutions.com

Main improvements
-----------------
1. Pulse branding aligned with the web platform.
2. App name remains Pulse.
3. Login page cleaned and simplified.
4. Removed extra feature boxes from the mobile login page.
5. Dashboard top area uses Pulse logo only.
6. User display name improved. If backend sends name as User, the app falls back to locally saved name/email prefix.
7. Dashboard UI polished for better mobile readability.
8. Trading Settings screen includes selected-pair management.
9. Scan request sends selected pairs to the deployed web API.
10. Latest signals, dashboard stats, plans, profile, password, logout, and subscription APIs remain compatible with the web backend mobile routes.

Production API
--------------
lib/core/api_config.dart is set to:
https://pulse.alphablocksolutions.com

Expected Laravel mobile API routes on web backend
-------------------------------------------------
POST /api/mobile/login
POST /api/mobile/register
GET  /api/mobile/dashboard
POST /api/mobile/scan-next-batch
GET  /api/mobile/subscription-plans
GET  /api/mobile/profile
POST /api/mobile/profile
GET  /api/mobile/settings
POST /api/mobile/settings
POST /api/mobile/change-password
POST /api/mobile/logout

Flutter commands
----------------
Run these inside the Flutter mobile project root:

flutter clean
flutter pub get
flutter pub upgrade
flutter run

App icon command
----------------
If app icon does not update, run:

dart run flutter_launcher_icons
flutter clean
flutter pub get
flutter run

Android release commands
------------------------
APK:
flutter build apk --release

AAB for Google Play:
flutter build appbundle --release

Output files:
build/app/outputs/flutter-apk/app-release.apk
build/app/outputs/bundle/release/app-release.aab

Local emulator testing
----------------------
For local Laravel testing only, change lib/core/api_config.dart from:
https://pulse.alphablocksolutions.com

to:
http://10.0.2.2:8000

Important production note
-------------------------
Because HostGator shared hosting may not allow artisan commands, database tables required by Laravel Sanctum must exist on the live MySQL database. If login shows personal_access_tokens table missing, create that table manually in phpMyAdmin using the SQL already shared earlier.
