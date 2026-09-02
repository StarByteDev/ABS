# ABS V14.0 Full Project Content Map

ABS V14.0 is intentionally focused on one public product, **Pulse Trading Intelligence**, plus the invitation-only **Private Member Portal**. Live Markets and source-linked Market News support daily Pulse usage without being marketed as separate products.

## Public website

### Home

- Premium Pulse-first hero
- Live BTC/USDT terminal and candlestick chart
- Live BTC, ETH, SOL, BNB and XRP ticker
- Active Pulse strategy names
- Pulse capability summary
- Liquid market movers
- **Five** latest verified/source-attributed market headlines
- Private Member Portal introduction
- Pulse account and plan calls to action

The server-rendered page uses cached market/news data and does not wait for external providers. Live values refresh asynchronously after the page is visible.

### Pulse public gateway

- Pulse Trading Intelligence overview
- Market Scanner, Signals, Risk Controls, Binance Connection, Orders & Trades, Alerts & Reports workflow
- Account registration and access journey
- Dynamic plan section loaded from administrator-created Pulse plans
- Default Pulse Trial presentation when it is the only active plan
- Customer-facing risk and execution explanation

### Live Markets

- Core market prices and 24-hour changes
- BTC candlestick chart
- Liquid gainers and losers
- Global market snapshot when provider data is available
- Provider availability states without fabricated fallback prices

### Market News

- Source-attributed external publisher headlines
- Original publisher links
- ABS editorial articles published through Admin
- Draft, publish, feature, image, category and source controls
- Honest unavailable states when news feeds cannot be reached

### About and legal

- Focused Alpha Block Solutions company position
- Pulse and Private Member Portal descriptions
- Security and responsible market-information principles
- Terms, Privacy, Risk Disclosure and Market Data Disclaimer

## Authentication and shared ABS account

### Premium sign in

The login screen uses a contained premium layout rather than the previous oversized split screen. Copy changes according to the requested service:

- standard ABS account sign in;
- Pulse workspace sign in;
- Private Member Portal sign in.

The form includes email, password, remember-me, account creation where appropriate, support contact and a security notice.

### Shared account features

- One registration/login identity
- Personal dashboard
- Watchlist
- Pulse plan/access status
- Private Member Portal status
- Administrator routing for admin accounts

## Pulse plan and permission model

Administrators can create any Pulse plan and configure:

- customer-facing name, description, price, currency and display order;
- active/inactive status;
- scanner runs/day;
- signals/day;
- manual trades/day;
- automatic trades/day;
- maximum open positions;
- maximum selected markets;
- Market Scanner visibility/access;
- Signals visibility/access;
- Orders & Positions visibility/access;
- Trade History visibility/access;
- Reports & Performance visibility/access;
- Binance API Connection visibility/access;
- Alerts visibility/access;
- Plan & Limits visibility/access;
- Pulse Settings visibility/access;
- Mobile API eligibility;
- Practice trading eligibility;
- Manual execution eligibility;
- Live trading eligibility;
- Automatic trading eligibility;
- strategy availability.

A user receives one assigned Pulse plan. The plan is the maximum feature envelope. Admin can add per-user restrictions that only remove capabilities; they cannot unlock a feature excluded by the plan.

A clean installation prepares **Pulse Trial** as the onboarding entitlement plus the public **Pulse Intelligence** and **Pulse Professional** memberships. New registrations can receive the active Trial automatically. Administrators can edit these records, control customer visibility/pricing/entitlements and create additional plans at any time.

## Pulse end-user workspace

The navigation and routes are generated from the user's effective plan capabilities. Depending on the plan, users can have access to:

- Dashboard
- Market Scanner
- Signals
- Orders & Positions
- Trade History
- Reports & Performance
- Binance API Connection
- Alerts
- Plan & Limits
- Pulse Settings

The workspace also supports:

- selected Binance USD-M Futures markets;
- fifteen configurable strategy modules;
- LONG, SHORT and NEUTRAL signal output;
- entry, stop-loss, take-profit and risk-to-reward values;
- Practice and controlled Live Binance connections;
- encrypted user Binance credentials;
- risk, sizing and leverage preferences;
- exchange orders and protection orders;
- trade synchronization;
- user and platform emergency stops;
- performance reporting;
- mobile-ready effective capability data.

## Private Member Portal

- Secure invitation-only access
- Current account value
- Net contributions and reported profit/loss
- Transaction history
- Monthly statements
- Print/PDF-friendly statement output
- CSV export
- Account ownership authorization on every member record

## Administration

### Main Admin

- ABS overview
- Users and account status
- Pulse administration
- Private Member Portal management
- Market News publishing

### Pulse Admin

- Operational dashboard
- Plans & Feature Access
- User Plan Assignment
- Strategies
- Supported Markets
- Signals
- Trades
- Platform Settings
- Alert Broadcasts
- Audit Logs

### Market News Admin

- Create and edit ABS articles
- Save draft or publish
- Feature on homepage
- Publication time
- Category
- Author
- Original source/publisher URL when applicable
- Article image

## Performance behavior

V14.0 retains the removal of external network waits from normal server-side page rendering:

- Home, Markets, News and Pulse dashboard render from local database/cache first;
- live market data updates asynchronously in the browser;
- three RSS publishers refresh concurrently;
- provider timeouts are shorter;
- Google Fonts are removed from active layouts;
- local development uses a development-appropriate bcrypt cost;
- provider failure never triggers fabricated market/news content.

## User account provisioning

V14.0 does not seed Standard User or Private Member credentials. Create real accounts through **Admin → User Management**, where role, status, Pulse plan, dates and individual restrictions can be assigned in one flow.

