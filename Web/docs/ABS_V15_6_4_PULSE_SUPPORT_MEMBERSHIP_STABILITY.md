# ABS V15.6.4 — Pulse Support & Membership Stability

## Purpose
V15.6.4 adds a first-party Pulse Support experience for authenticated ABS members and fixes the V15.6.3 Membership Blade parse error.

## Pulse Support
- One conversation is shared by website and mobile API.
- Admin presence: Online, Away or Offline.
- Online presence expires automatically if the Admin support screen is no longer active; the member then sees the Away state.
- Away/Offline uses Pulse Assistant for common payment, package, Free Signal, scanner, account, mobile and technical-support questions.
- Requests that need a person remain in the Support Inbox for continuation by the Admin.
- Admin replies create a normal Pulse alert for the member.
- The web implementation uses light polling and requires no WebSocket server, making it suitable for HostGator shared hosting.

## Database safety
The release adds only `support_conversations` and `support_messages`. The migration is additive and its `down()` method intentionally does not remove support records. Existing users, memberships, payments, signals, trades and strategy evidence are untouched.

The ABS Database Fix schema repair also knows how to create/repair these two support tables non-destructively if they are missing.

## Membership hotfix
`resources/views/pulse/membership/index.blade.php` used an escaped PHP namespace in V15.6.3. V15.6.4 corrects it to `\Illuminate\Support\Str::limit(...)` so `/pulse/membership` renders normally.

## Mobile API
Authenticated routes:
- `GET /api/v1/support`
- `POST /api/v1/support/messages`
- `GET /api/v1/support/conversations/{conversation}`
- `PATCH /api/v1/support/conversations/{conversation}/close`

The current web/backend package does not contain Flutter source. These endpoints are the canonical mobile integration surface for the next Flutter presentation update.
