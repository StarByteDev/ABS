# ABS Pulse Mobile V1.6.0+160

## True supplied-template rebase

V1.6.0 is not a reskin of the previous ABS mobile shell. The runtime root now uses the user's supplied `abs_mobile(1).zip` visual/navigation architecture directly.

Primary navigation is exactly:

- Home
- Pulse
- Free Signal
- News
- Account

The legacy `MainShell`, `template_home_screen.dart`, `template_pulse_screen.dart`, and `template_account_screen.dart` adapters were removed so the old shell cannot remain the production entry point.

## ABS Pulse branding

- Production ABS logo asset is used throughout the new template UI.
- Template placeholder/demo identity was removed from primary runtime screens.
- Dark navy / blue / gold premium market-terminal visual language is preserved.
- App version is `1.6.0+160` and backend target remains ABS `V15.7.4`.

## Live production content

The supplied template's demo data layer was replaced with a live mutable data facade populated from ABS APIs. No fake market values are generated when the server does not return a metric; unavailable values display as `—`.

Home now consumes the ABS market overview, movers, BTC chart, headlines and membership state. Pulse consumes the real signal overview and watchlist. ABS Intelligence consumes ABS News, Live headlines and the Economic Calendar.

## Free Signal

The template-style Free Signal screen retains the production rewarded-ad flow:

- `/pulse/free-signal/status`
- `/pulse/free-signal/session`
- `/pulse/free-signal/claim`
- qualified signal first
- Entry Watch when supplied by the backend
- eligible active-package fallback
- BTCUSDT 4H context-only fallback
- no fabricated Entry / Stop Loss / Take Profit
- server cooldown and visitor continuity

## ABS Intelligence

Calendar is the default first tab, with `Upcoming` selected by default. Views are:

- Today
- Upcoming
- Previous
- All

ABS News and Live headline tabs remain available in the same template screen.

## Registration and activation

New accounts can remain inside the platform with basic access when the registration/login backend returns a limited token before email verification. Account clearly shows `Account not activated` with:

- Resend link
- Check status
- explanation of basic vs activation-required features

Full scanner, signals, positions, trading/account controls and membership-protected capabilities remain gated by server state.

## Current Pulse features retained

The template Account hub exposes the current production feature set:

- Scanner
- Signals
- Positions
- Trade History
- Orders
- Strategies
- Reports
- Watchlist
- Alerts
- Calculators
- Trading Setup
- Pulse Dashboard
- Membership
- Profile
- Account Notifications
- Notification Preferences
- Registered Devices
- Sessions
- Change Password
- Research
- Learning
- Global Search
- Newsletter
- ABS Services
- Help Center
- V15.7.4 Private Investor portal
- Legal & Risk
- About ABS Pulse

## Compatibility hotfixes included

- Unsupported `FontWeight.w650` is absent.
- Newer `Color.withValues(alpha: ...)` calls were replaced with Flutter-3-compatible `withOpacity(...)` in retained production screens.
- Empty signal targets no longer crash template signal cards.

## Validation

`tool/validate_v160.py` checks the runtime shell, template navigation, live endpoint wiring, activation flow, V15.7.4 target, Free Signal behavior, import resolution, supported FontWeight constants, old-shell removal and Dart delimiter/string balance.

Native `flutter analyze`, `flutter test`, APK/AAB and emulator execution still require a machine with the Flutter SDK installed. They were not claimed as executed in the packaging environment.
