# ABS V14.1 Login Page

## Approved implementation

The `/login` route now uses the finalized Alpha Block Solutions Member Portal design. The page is isolated in `layouts/auth.blade.php`, so it can match the approved full-screen presentation without changing the public website header, page content or footer layouts.

## Working controls

- Back to Website returns to the ABS home page.
- Email and password are submitted to the existing Laravel authentication controller with CSRF protection.
- Remember Me continues to use Laravel's persistent-login behavior.
- The eye control shows or hides the password and retains keyboard focus.
- Forgot Password opens the rate-limited recovery flow.
- Create an Account preserves the existing Pulse plan query when login started from Pulse.
- Privacy Policy, Terms & Conditions and Risk Disclosure use the existing legal routes.
- Contact Support uses the configured `ABS_SUPPORT_EMAIL` value.

## Security retained

- Email addresses are normalized before authentication.
- Sessions regenerate after successful login.
- Inactive accounts cannot continue.
- Role, Pulse-plan and Private Member redirects remain unchanged.
- Password recovery uses Laravel's token store and password broker.
- Recovery requests return the same message for matching and non-matching email addresses.
- Recovery posts are rate-limited.

## Brand asset rule

`public/assets/brand/abs-logo-master.png` is the supplied 2048×2048 transparent source logo. Do not redraw, recolor, crop or replace this file. Use `abs-logo-512.png` for normal website display and preserve the master for future output sizes.
