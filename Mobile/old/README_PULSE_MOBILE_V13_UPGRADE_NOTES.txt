Pulse Mobile V13 Upgrade Notes
==============================

Branding updates:
- App display branding changed to Pulse.
- Pulse logo asset updated from the latest supplied logo.
- Login page and authenticated dashboard use the same Pulse identity as the web version.
- Dashboard header now shows the Pulse logo instead of the NEXT GENERATION TRADING INTELLIGENCE text.
- pubspec version updated to 13.0.0+130.

App icon:
- Added assets/images/app_icon.png using the supplied Pulse logo.
- Added flutter_launcher_icons configuration in pubspec.yaml.
- In the full Flutter project, run:
  flutter pub get
  dart run flutter_launcher_icons

Dashboard and user display:
- Mobile app now saves logged-in user name/email after login.
- Dashboard welcome text uses the real user name where available.
- If backend returns generic name like User, the app falls back to the email prefix.

Trading settings improvements:
- Profile & Settings now includes a Trading Settings section.
- Users can select trading pairs from the mobile app.
- Selected pairs are sent to /api/mobile/settings when saved.
- Selected pairs are also sent when running /api/mobile/scan-next-batch.
- Fallback common USDT pairs are shown if backend does not return available_pairs.

Required backend support:
- GET  /api/mobile/settings should return available_pairs and selected_pairs where possible.
- POST /api/mobile/settings should save selected_pairs/pairs for the logged-in user.
- POST /api/mobile/scan-next-batch should use selected_pairs/pairs from request or saved user settings.

Production API:
- Keep lib/core/api_config.dart baseUrl as:
  https://pulse.alphablocksolutions.com

Local emulator testing:
- Change baseUrl to:
  http://10.0.2.2:8000

Build:
  flutter clean
  flutter pub get
  dart run flutter_launcher_icons
  flutter run

Release:
  flutter build apk --release
  flutter build appbundle --release
