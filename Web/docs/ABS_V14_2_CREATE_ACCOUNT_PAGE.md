# ABS V14.2 Create Account Page

## Final implementation

The `/register` route now uses the same finalized Alpha Block Solutions authentication shell as `/login`. The original transparent ABS logo, header, premium intelligence panel, outlined form card and legal footer remain the visual authority.

Registration always creates an **Alpha Block Solutions account**. Pulse is shown only as an eligible ABS service and never as a separate identity.

## Customer fields

- Full Name
- Email Address
- Country
- Country Code
- Phone or WhatsApp Number
- Password
- Confirm Password
- Terms & Conditions and Privacy Policy consent

The discontinued purpose field is not present. Country selection can suggest the international calling code, while the customer can still correct the code when necessary.

## Activation journey

1. The customer submits the protected Laravel registration form.
2. ABS creates the identity in Pending state and sends a branded activation email.
3. The signed activation link remains valid for 24 hours and is rate-limited.
4. After the email hash and signature are verified, ABS marks the email verified and activates the account.
5. If Admin has enabled automatic Trial assignment, the configured Pulse Trial starts only at activation.
6. The customer signs in through the approved V14.1 Member Portal page and sees the ABS services available to the account.

## Security and data handling

- CSRF protection and Laravel validation remain mandatory.
- Registration posts are rate-limited.
- Email addresses are normalized before uniqueness checks.
- Passwords require at least eight characters, mixed case and a number.
- Calling codes and phone-number characters are validated server-side.
- Country values must match the maintained country list.
- Contact fields are added through a non-destructive migration and the MySQL schema reconciler.
- Trial access is not consumed before account verification.
- The login screen, password recovery and role-aware redirects remain unchanged.

## Admin visibility

Admin User Management can search by phone number and can store, display and update Country, Country Code and Phone/WhatsApp data from Create User and User 360°.
