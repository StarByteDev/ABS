# ABS V14.3 Complete Authentication UI

## Locked design authority

The following supplied images are the final visual authority for authentication development:

- `docs/reference/ABS_V14_3_LOGIN_REFERENCE.png`
- `docs/reference/ABS_V14_3_CREATE_ACCOUNT_REFERENCE.png`
- `docs/reference/ABS_V14_3_ACCOUNT_RECOVERY_REFERENCE.png`

All three are 1672×941 references. The implementation preserves their Alpha Block Solutions header, transparent official emblem, navy market-intelligence background, cyan interactions, gold highlights, serif headings, premium outlined cards, customer wording and four-link legal footer.

## Login

Route: `/login`

- Email Address and Password
- Show/hide password
- Remember Me
- Forgot password
- Create an account
- Service-aware Pulse and Private Member routing retained
- Protected session regeneration and inactive-account checks retained

## Create Account

Route: `/register`

- Full Name
- Email Address
- Country
- International country code
- Phone or WhatsApp Number
- Password and Confirm Password
- Terms & Conditions and Privacy Policy consent
- Signed 24-hour email activation
- Pulse Trial eligibility evaluated after activation

Registration creates one Alpha Block Solutions identity. Pulse remains a service inside ABS.

## Account Recovery

Route: `/forgot-password`

### Password tab

The customer can enter either:

- the complete registered email; or
- the unique username portion before `@` in the registered email.

ABS resolves a unique account internally and sends the standard secure Laravel password-reset link. No public response confirms whether the account exists.

### Username / Email tab

The customer provides the registered country code and Phone/WhatsApp number. If the details match, ABS sends the sign-in email to the email address already registered to that account. The sign-in email is never displayed on the public recovery page.

### Security

- CSRF protection on both forms
- Six recovery attempts per minute per throttling identity
- Server-side calling-code and phone validation
- Generic success responses for matching and non-matching details
- Password reset tokens managed by Laravel
- Password policy: minimum eight characters, mixed case and a number
- Keyboard-operable tabs with Arrow, Home and End navigation

## Responsive behavior

- Desktop: approved two-panel layout with full header and legal footer
- Tablet: market-intelligence summary stacks above the form
- Mobile: single-column forms, stacked benefits, compact header and wrapped footer links
- Visible keyboard focus, reduced-motion support and high-contrast fallbacks are included

## Brand rule

`public/assets/brand/abs-logo-master.png` is the approved transparent master. It must not be redrawn, recolored, cropped or replaced. `abs-logo-512.png` is the optimized runtime asset.
