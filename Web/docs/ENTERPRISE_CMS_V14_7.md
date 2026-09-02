# ABS V14.7.0 — Enterprise CMS & Admin Guide

The administrator area preserves the existing ABS/Pulse operations and adds standard content and communications controls needed for production management.

## Existing administration retained

- Dashboard and operational overview.
- User Directory / User 360°.
- Private Member portfolios, transactions and statements.
- Market News CMS and verified-headline controls.
- Pulse plans, subscriptions/access, membership payment requests and promotions.
- Pulse strategies, pairs, signals, trades, platform settings, alerts and audit/log views.

## V14.7.0 additions

### Research CMS
Create/edit/publish/archive research reports with title, slug, summary, full body, category, asset symbol, risk level, image URL, featured state and publication time.

### Learning CMS
Create/edit/publish/archive learning material with category, difficulty level, reading duration, excerpt/body, featured state and publication time.

### Economic Calendar CMS
Manage event title, country, currency, impact, event time, previous/forecast/actual values and source.

### Products & Services CMS
Manage public/mobile product and service descriptions, icon/accent metadata, feature lists, ordering, status and featured state.

### Website & Mobile Settings
Manage SiteSetting values used by public/mobile clients, including mobile minimum/recommended version and maintenance messaging.

### Newsletter Subscribers
Review subscribers and switch between active/unsubscribed states. The original database enum is respected for upgrade safety.

### Contact & Support Inbox
Review submitted web/API enquiries, category, status, priority and internal administrator notes.

### Email Communications
- Global switches for transactional, Pulse alert, signal, trade, promotion, expiry and Daily Market Brief email categories.
- Delivery health counts and detailed sent/failed log.
- Real branded test email from the current production transport.
- Registered mobile-device count for operational visibility.

## Database upgrade

The supported existing-database path is:

```bash
php artisan optimize:clear
php artisan abs:repair --seed
php artisan abs:doctor
```

`abs:repair` adds required production tables/columns non-destructively and does not erase users, memberships, CMS content, Pulse history or Private Member data.
