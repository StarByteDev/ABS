# ABS V14.7.3 deployment notes

## Scope
- Premium Enterprise Console CSS/UX refresh.
- Robust shared email design with visible CTA and fallback link.
- Correct Admin-created unverified account activation email.
- Standard user first-login Pulse Trial routing/self-heal.

## Production deployment
1. Keep the current production `.env` and APP_KEY.
2. Upload the application code and the matching `public/assets` files to the real web root.
3. Run `php artisan optimize:clear`.
4. Sign in as Administrator and confirm Dashboard, User Management, Email Communications and Database & Updates.
5. Create a temporary standard test user with email verification required, verify the activation email CTA, activate the account and confirm the first login opens Pulse when Trial auto-assignment is enabled.
6. Send an Admin Email Communications test message and a password-reset email to confirm production-client rendering.

No schema reset is required.
