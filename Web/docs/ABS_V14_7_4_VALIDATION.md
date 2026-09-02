# ABS V14.7.4 Validation

Release date: 23 August 2026

## Regression targets

- Pulse shared layout no longer uses Blade `@if/@endif` nesting in the navigation shell that previously caused the `/pulse/access` ParseError.
- Inactive Pulse accounts can open `/pulse/access` and membership pages without exposing protected workspace actions.
- Active Pulse accounts retain the complete capability-driven navigation.
- `/dashboard` uses the new premium member dashboard layout while preserving watchlist, market snapshot, news and service-access behavior.
- No destructive database migration is required.

## Static checks performed before packaging

- PHP syntax validation for application/config/routes/database PHP files.
- Direct PHP syntax validation for all Blade source files, including raw PHP blocks.
- JavaScript syntax validation with Node.
- CSS brace-balance validation.
- `resources/css/abs-app.css` and `public/assets/css/abs-app.css` byte-for-byte synchronization.
- Explicit Pulse layout regression guard confirming no Blade `@if`, `@elseif` or `@endif` directive remains in the shared Pulse layout.
- ZIP integrity test after packaging.

## Deployment acceptance

After copying the build to Laragon/HostGator, preserve `.env` and `APP_KEY`, then run:

```bash
php artisan optimize:clear
php artisan abs:doctor
```

Then verify:

1. standard user login → `/dashboard` renders the premium member account page;
2. inactive Pulse user → `/pulse/access` renders normally;
3. active Pulse user → `/pulse/dashboard` renders normally;
4. administrator login → Admin console remains available.
