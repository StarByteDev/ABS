# ABS V14.8.3

This release preserves V14.8.2 and adds six authenticated-user UX/runtime corrections:

1. Market scans use AJAX and refresh only scanner fragments.
2. Signed-in Pulse pages expose a structured account menu with a clear Sign Out action.
3. Web logout accepts the current safe session-destroy request without CSRF expiry, eliminating the home-page 419 logout failure.
4. Daily scanner and generated-signal usage/remaining allowance are plan-aware and visible globally; Mobile API exposes `/api/v1/pulse/usage`.
5. Long/Short Ratio no longer renders null as 0.00 and invalid exchange ratios fall through to alternate live data.
6. Changed user pair selections are locked for 50 hours before another pair change; server and Mobile API enforce the same cooldown.
