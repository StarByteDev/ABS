# ABS V15.6.4 Pulse Support API

Base URL: `/api/v1`

Pulse Support uses the same authenticated conversation on web and mobile. The backend does not require WebSockets; clients may refresh the active conversation periodically.

## Authenticated endpoints
- `GET /support` — current support availability, support topics, unread count and active conversation.
- `POST /support/messages` — start a conversation or add a message. Fields: `conversation_id` optional, `category` optional, `message` required.
- `GET /support/conversations/{conversation}` — refresh one conversation and message history.
- `PATCH /support/conversations/{conversation}/close` — close the member's conversation.

## Presence and hand-off
Admin presence is `online`, `away` or `offline`.
- `online`: the member message waits for the ABS Support team.
- `away` / `offline`: Pulse Assistant immediately handles common account, package, payment, Free Signal, scanner and mobile-app questions. Requests that need a person stay open for the Support team.

The API never exposes another member's conversation. Authentication uses the existing Sanctum token and active-account middleware.

## Flutter presentation
Use one Support screen with:
- presence label from `presence_label`
- topic shortcuts from `categories`
- `conversation.messages[]` rendered by `sender` (`customer`, `admin`, `assistant`, `system`)
- periodic refresh of the active conversation while the screen is open

Do not create a separate mobile ticket database. Web and mobile intentionally share the same support records.
