Pulse Trading Intelligence Mobile V12.10 - Build Fix + Web Branding Alignment

This build keeps the same root package style as AlphaBlock_Signals_Mobile_V11_12_root.
Do not extract it into an extra subfolder. Copy/replace the package files directly over your Flutter mobile project.

What changed in V12.10:
- Fixed Dart syntax/build issue in dashboard_screen.dart.
- Fixed Dart syntax/build issue in plans_screen.dart.
- Rewrote Dashboard and Plans screens using safer explicit widget structure.
- Preserved Pulse logo and branding used in the Laravel web version.
- Dashboard title uses NEXT GENERATION TRADING INTELLIGENCE.
- Mobile UI content is aligned with Pulse web identity.
- Existing Laravel mobile API routes are preserved:
  POST /api/mobile/login
  POST /api/mobile/register
  GET  /api/mobile/dashboard
  POST /api/mobile/scan-next-batch
  GET  /api/mobile/subscription-plans
  GET  /api/mobile/profile
  POST /api/mobile/profile
  POST /api/mobile/change-password
  POST /api/mobile/logout

Important note about the error you saw:
The terminal error was a Dart syntax issue, not a Laravel API issue.
The VS Code Gradle popup is usually from cached Android plugin/dependency configuration. After replacing files, run:

flutter clean
flutter pub get
flutter pub upgrade
flutter run

For local emulator testing:
lib/core/api_config.dart
baseUrl = http://10.0.2.2:8000

For production:
baseUrl = https://pulse.alphablocksolutions.com
