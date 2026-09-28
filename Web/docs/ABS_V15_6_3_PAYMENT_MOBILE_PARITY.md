# ABS V15.6.3 — Payment Experience, Alerts & Mobile Parity

## Direct-USDT payment flow

The package checkout is now a focused ABS Pulse flow: package total and access duration, network and wallet, transfer steps, package benefits, then transaction verification. The member account shows the latest verification state and the capabilities included with the current/next package.

Submitting a payment request performs three independent notification actions:

1. The payment request is stored in the existing database workflow.
2. A persistent Admin alert is created and pending payment counts are shown in Admin navigation.
3. Branded member/Admin emails are attempted through the configured production mail transport.

The release does not add a database migration.

## Production email readiness

Admin → Alerts & Emails now reports the effective delivery transport. `log` and `array` are development transports and are deliberately recorded as `not_delivered`, not as successful inbox delivery. In production ABS can use configured SMTP; if the configured transport is non-delivery and the host exposes `/usr/sbin/sendmail`, ABS can use sendmail.

For dependable production delivery, configure a real sender domain and either SMTP or the hosting mail transport, then use the Admin test-email action. The V15.6.2+ updater preserves the live `.env` file.

## Shared package/API presentation

`PulseMembershipService` is the shared source for package benefits and normalized payment-request status. Web and mobile API responses now expose the same package benefit language and payment stages:

- Payment submitted
- Transaction verification
- Pulse access active

## Free Signal parity

Web and mobile Free Signal requests use the same `PulsePublicRewardedSignalService`.

Priority:

1. Current qualified member/Professional Pulse signal pool
2. Current qualified system research pool
3. Fresh market-wide Pulse scan
4. Latest active qualified Pulse signal
5. BTC 4-Hour Outlook when no qualified signal exists

The final fallback is not labelled as a trade signal. It gives a 4-hour BTC market watch with current directional bias, watch zone, expected range, potential move and invalidation. The mobile API returns `presentation_schema_version: 3` and `fallback_mode: btc_4h_outlook` so Flutter can render the same state as web.

## Live data safety

V15.6.3 is application-code only. Existing users, package requests, payments, signals, trades and history are preserved. The V15.6.2 one-build code rollback model remains in force and never restores the production database.
