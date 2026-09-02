Pulse Trading Intelligence Mobile V12.9 Rebrand
================================================

This package contains the updated Flutter mobile source rebranded from Signals_ABS / Alpha Block Solutions to Pulse Trading Intelligence.

Main changes:
- App title changed to Pulse Trading Intelligence.
- Tagline changed to NEXT GENERATION TRADING INTELLIGENCE.
- Pulse logo added under assets/images/pulse_logo.png.
- Login, register, dashboard, plans, profile/settings screens updated with Pulse branding.
- Production API URL set to https://pulse.alphablocksolutions.com.
- API prefix kept as /api/mobile to match Laravel V12.x mobile endpoints.
- Dashboard supports latest_signals and signals response keys.
- Plans screen supports admin-configured wallet address from Laravel subscription-plans API.
- Start scan uses /api/mobile/scan-next-batch.
- Professional dark cyan UI matching the Pulse web identity.

How to use:
1. Copy this package over your latest Flutter mobile app source, or replace your lib/, pubspec.yaml, and assets/ folders.
2. Run:
   flutter clean
   flutter pub get
   flutter run

For local Android emulator testing:
- Open lib/core/api_config.dart
- Change baseUrl from https://pulse.alphablocksolutions.com to http://10.0.2.2:8000

For production release:
- Keep baseUrl as https://pulse.alphablocksolutions.com
- Build Android:
   flutter build apk --release
   flutter build appbundle --release

Important backend requirement:
Laravel must include V12.3+ / V12.9 mobile routes:
- POST /api/mobile/login
- POST /api/mobile/register
- GET  /api/mobile/dashboard
- POST /api/mobile/scan-next-batch
- GET  /api/mobile/subscription-plans
- GET  /api/mobile/profile
- POST /api/mobile/profile
- POST /api/mobile/change-password
- POST /api/mobile/logout
