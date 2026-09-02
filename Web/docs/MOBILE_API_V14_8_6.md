# ABS V14.8.6 Mobile API Notes

V14.8.6 preserves the existing `/api/v1` endpoint count and extends scanner behavior without removing or renaming Mobile API routes.

## Scanner behavior

`POST /api/v1/pulse/scanner/run`

- Omit `symbols` to scan the complete market universe permitted by the authenticated user's package.
- Provide `symbols` only for an intentional targeted subset.
- Targeted requests accept up to the configured server safety ceiling (default 1000 symbols).
- Requested symbols must still belong to the current package.
- A provider failure affecting every requested/package market returns a controlled `422` response and the failed run does not consume completed daily scan quota.

`GET /api/v1/pulse/scanner/overview`

- Scanner results can include evaluated markets that did not create a signal.
- Results expose transparent statuses such as `High-Conviction`, `Qualified`, `Below Filter`, and `No Setup`.
- A row without a Pulse signal has a null signal identifier and must not be treated as executable signal evidence by the mobile client.

## Pair-selection behavior

The existing settings/pair catalog APIs remain package-controlled. `selected_pairs` continues to represent execution/watch selection and remains subject to the 50-hour change cooldown. It no longer defines the default package-wide scan universe.

## Build identity

- API bootstrap build: `14.8.6`
- OpenAPI version: `14.8.6`
