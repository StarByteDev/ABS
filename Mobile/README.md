# Pulse Mobile

**Version:** V1.6.4+164  

## V1.6.4 calendar + product-brand update

V1.6.4 keeps the supplied premium template and V15.7.4 integrations, strengthens Economic Calendar value extraction across live provider response shapes, merges duplicate release payloads so result figures are preserved, and changes the user-facing mobile identity to **Pulse**. See `RELEASE_NOTES_V1_6_4.md`.

## V1.6.3 web-parity update

V1.6.3 keeps the user-supplied premium template as the production shell and aligns two major mobile journeys with the live ABS web application: **Direct USDT package payment** and **Find Best Signal**. Package payment now opens directly into transfer/verification details, while Pulse gets an animated 15M + 4H Best Signal scan and result reveal. See `RELEASE_NOTES_V1_6_3.md`.

## V1.6.2 runtime reliability update

V1.6.2 keeps the supplied premium template as the actual production shell and fixes four emulator findings: historical calendar Actual/Forecast/Previous mapping, sparse Market Overview cards, mobile-number capture at registration, and broken Membership unauthenticated/error presentation. See `RELEASE_NOTES_V1_6_2.md`.
**Backend:** ABS V15.7.4  
**Production API:** `https://alphablocksolutions.com/api/v1`

This Flutter source is fully rebased on the user-supplied premium `abs_mobile(1).zip` template. The template is now the production visual/navigation root; the previous ABS mobile `MainShell` is not used.

The five primary tabs are **Home, Pulse, Free Signal, News, Account**. The template's demo content has been replaced by live ABS market, signal, intelligence, membership and account services while preserving the existing server-authoritative trading architecture.

See `RELEASE_NOTES_V1_6_0.md` for feature mapping and `LOCAL_TEST_GUIDE.md` for local Flutter checks.
