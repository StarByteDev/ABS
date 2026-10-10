# Mobile API TLS Recovery — V15.8.3

## Observed failure

The supplied production screenshot reports:

`NET::ERR_CERT_COMMON_NAME_INVALID`

for:

`https://alphablocksolutions.com/api/v1`

This is an HTTPS certificate hostname mismatch. TLS validation happens before the request reaches Laravel, so no controller, route or API JSON change can repair the certificate.

## What V15.8.3 changes in code

V15.8.3 keeps the existing mobile API route/response contract and adds two safe diagnostic endpoints:

- `GET /api/v1`
- `GET /api/v1/health`

A successful response contains `ok: true`, `api_version: v1`, build `15.8.3`, and `mobile_api_compatible: true`.

## HostGator / cPanel recovery checklist

1. Open **cPanel → SSL/TLS Status** (or the HostGator SSL/AutoSSL area).
2. Confirm the certificate explicitly covers `alphablocksolutions.com`. Also include `www.alphablocksolutions.com` if the site/API can use that hostname.
3. Run AutoSSL or reissue/install the correct certificate for the production domain.
4. Confirm DNS for `alphablocksolutions.com` points to the hosting account/server that owns that certificate. If a proxy/CDN is used, verify its edge certificate also covers the exact hostname.
5. Clear any hosting/CDN SSL cache if applicable and wait for the new certificate to propagate.
6. Open `https://alphablocksolutions.com/api/v1/health` in a normal browser. Do not proceed until there is no certificate warning and the JSON health response is visible.
7. Re-test the published Pulse app login/bootstrap/scanner.

## Security rule

Do **not** disable certificate validation or ship a mobile workaround that accepts an invalid certificate. That would expose authentication tokens and customer/API traffic to interception. The correct fix is a valid production certificate for the hostname used by the app.

## If HTTPS is valid but the app still fails

After the certificate is repaired, verify in this order:

1. `/api/v1/health` returns HTTP 200 JSON.
2. `/api/v1/bootstrap` returns HTTP 200 JSON.
3. Mobile login reaches `/api/v1/auth/login`.
4. Authenticated Pulse endpoints return normal JSON rather than 401/403/500.
5. The app's configured base URL is exactly the certificate-covered production hostname.

V15.8.3 did not remove or rename the existing mobile endpoints.
