# ABS V14.7.0 — Email Alert Matrix

ABS uses `App\Services\BrandedMailService` for a consistent premium navy/gold/cyan email presentation. Delivery attempts are recorded in `email_delivery_logs` with event, recipient, subject, status, error details and metadata where applicable.

| Workflow | Event key | Default | User preference / admin control |
|---|---|---:|---|
| Account activation | `account_activation` | On | Transactional emails |
| Welcome / account ready | `welcome` | On | Transactional emails |
| Admin-created account | `admin_account_created` | On | Transactional emails |
| Password reset | `password_reset` | On | Transactional emails |
| Password changed | `password_changed` | On | Transactional emails |
| Admin password change | `password_changed_admin` | On | Transactional emails |
| Sign-in identifier reminder | `account_identifier_reminder` | On | Transactional emails |
| Pulse plan request received | `plan_request_received` | On | Transactional emails |
| Pulse plan activated | `plan_activated` | On | Transactional emails |
| Pulse request declined | `plan_request_declined` | On | Transactional emails |
| Pulse access updated/renewed | `access_updated` | On | Transactional emails |
| Expiry reminders | `plan_expiry_7d`, `3d`, `1d`, `0d` | On | Expiry emails + user `plan_expiry` |
| Access expired | `plan_expired` | On | Expiry emails + user `plan_expiry` |
| Coupon / gift voucher assigned | `promotion_assigned` | On | Promotion emails |
| Qualified Pulse signal | `qualified_signal` | On | Signal emails + user `signals` |
| Pulse market alert | `pulse_market_alert` | On | Pulse alerts + user `market` |
| Pulse risk alert | `pulse_risk_alert` | On | Pulse alerts + user `risk` |
| Pulse trade alert | `pulse_trades_alert` | Off | Trade emails + Pulse alerts + user `trades` |
| Pulse system / plan notice | `pulse_system_alert` | On | Pulse alerts + user `system` |
| Newsletter confirmation | `newsletter_subscribed` | On | Transactional emails |
| Contact acknowledgement | `contact_acknowledgement` | On | Transactional emails |
| Daily Market Brief | `daily_market_brief` | Off | Daily brief switch + user `daily_brief` |
| Production test email | `system_test` | Manual | Admin or CLI test |

## Scheduled delivery

Laravel Scheduler runs:

- `abs:expiry-reminders` daily at 08:00 application time.
- `abs:daily-market-brief` daily at 08:15 application time when enabled.
- Existing market cache, Pulse maintenance and automation schedules remain in place.

The live host must run `php artisan schedule:run` every minute through cron.

## Production checks

```bash
php artisan abs:repair --seed
php artisan abs:email-test you@example.com
php artisan abs:production-check --email=you@example.com
```

A `sent` application log confirms that Laravel handed the message to the configured mail transport successfully. Before launch, also verify real inbox receipt, spam placement, SPF, DKIM, DMARC and the production sender domain.

## Security / anti-noise behavior

- Passwords, private keys, seed phrases and exchange API secrets are never included in emails.
- Account recovery responses are generic to reduce account enumeration risk.
- Qualified signal email is optional at user level and can be disabled globally.
- Trade email is disabled globally by default because active trading can generate high email volume.
- Daily Market Brief is opt-in and globally disabled by default until the administrator is ready to send it.
- Expiry reminders are deduplicated by access record/event so the same successful reminder is not repeatedly sent.
