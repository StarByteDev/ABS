# ABS V13.8 — Premium Experience & Communications Guide

ABS V13.8 keeps the V13.7 commercial and enterprise administration layer intact and upgrades the customer-facing account, legal and communications experience.

## Customer journey

The Pulse account journey is framed around market intelligence, real-time market awareness, trading signals, structured setups and disciplined risk management. Customer-facing screens use **plan** and **access** wording rather than exposing internal subscription/entitlement terminology.

## Legal pages

- `/legal/terms`
- `/legal/privacy`
- `/legal/risk-disclosure`
- `/legal/market-disclaimer`

The registration checkbox links directly to Terms of Service and Privacy Policy.

## Branded email events

The `App\Services\BrandedMailService` is the single customer-communication layer for account and Pulse events. It uses `resources/views/emails/branded.blade.php` for consistent Alpha Block Solutions presentation.

Automatic transactional messages cover account creation, plan requests, plan activation/review, manual access changes/renewals, account-specific offers and market-update subscriptions. Admin can additionally send a Pulse broadcast alert by email. Trade-event email alerts are separately controlled and disabled by default.

## Admin controls

Open `/admin/pulse/settings` and review the **Communications** group:

- Transactional emails
- Pulse alert emails
- Promotion emails
- Trade email alerts

For Admin broadcasts, enable **Also send this alert by email** on the alert form when email delivery is desired.

## Local vs production email

Local builds use:

```env
MAIL_MAILER=log
```

Email HTML and content are logged instead of sent. Before production launch configure your actual mail transport using the standard `MAIL_*` settings in `.env` and confirm that `ABS_SUPPORT_EMAIL` is monitored.

## Existing database upgrades

Run:

```bash
php artisan abs:repair --seed
```

This keeps V13.7 plan/payment/access data and adds any missing V13.8 communication settings through the normal non-destructive seed path.
