# ABS V14.7.8 Validation Record

**Release date:** 24 August 2026

V14.7.8 was corrected directly from the user-supplied V14.7.7 archive whose SHA-256 is `3c67ef3783a6e846e1758ce3faf618954e55fb4e5f9edcf5dedbd2a80e62403f`.

## Package-level checks completed

| Check | Result |
|---|---|
| Changed PHP controller syntax (`AuthController`, `DashboardController`, `ProfileController`) | PASS |
| Web and console route PHP syntax | PASS |
| Static Blade named-route references | PASS — 79 Blade files / 139 named-route references |
| Controller/action route audit | PASS |
| Mobile API ↔ OpenAPI parity | PASS — 105 operations |
| Existing approved premium five-page visual contract | PASS — 262 checks |
| V14.7.8 login/dashboard/profile/secondary-page contract | PASS |
| Original V14.7.7 archive SHA match | PASS |
| Approved Scanner/Signals/Strategies/Execution reference screenshots retained in package | PASS |

## Runtime checks after deployment

Composer `vendor/` dependencies are intentionally not distributed in the source archive, so Laravel runtime commands must be run after `composer install` in the target Laragon/HostGator environment:

```bash
php artisan optimize:clear
php artisan abs:doctor
php artisan abs:view-audit
php artisan test --filter=PulsePremiumPagesApiTest
```

No database migration is introduced by V14.7.8.
