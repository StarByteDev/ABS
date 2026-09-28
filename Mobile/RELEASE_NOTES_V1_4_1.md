# ABS Pulse Mobile V1.4.1+141

Target backend: **ABS V15.7.4**

## What this patch fixes

1. **ABS Intelligence opens on Calendar** instead of an empty ABS News tab. The Calendar defaults to **Upcoming**, matching the current web flow, and exposes Today / Upcoming / Previous / All.
2. **News / Live / Calendar payload handling is resilient** to nested or paginated API response shapes used by V15.7.4.
3. **Signup no longer stops at an activation wall.** When `/auth/register` returns the normal registration token, the app stores it and enters a limited account immediately.
4. **Pending email activation is visible in Profile.** The user can resend the activation email or tap Check Status after opening the link.
5. **Basic access while pending:** public Market Pulse, Markets, Free Signal, ABS Intelligence, Research, Learning, Services and Profile. Pulse membership, scanner, signals, positions and trading controls remain locked until activation.
6. **Trade Signals responsive UI fix:** market-scan badges are moved into a wrapping row, fixing the vertically broken title shown on narrow Android screens.
7. **Pulse dashboard cleanup:** the oversized setup hero was replaced with a concise readiness/next-step card and single clear action.

## Backend note for pending-account sign-in

The current mobile flow works immediately after signup because the V15.7.4 registration contract returns a token. The app persists that token and cached identity across restarts. For a user who explicitly signs out before activating and then tries to sign in again, the API login endpoint must also return a limited-access token for an unverified account. V1.4.1 sends `allow_unverified_basic_access: true` and can consume a token/user returned either as a normal login response or inside an activation-required response.

## Validation

Run `python tool/validate_source.py`, then on a Flutter workstation run `flutter pub get`, `flutter analyze`, `flutter test`, and the supplied Android runner/build commands.
