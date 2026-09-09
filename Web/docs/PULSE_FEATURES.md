# ABS V14.0 Pulse Trading Intelligence — Complete Feature Set

## V15.0.5 Investor analytics and operational control

- Isolated premium responsive Admin design system across Pulse, users, CMS, communications and system operations
- Executive charts for Pulse activity, entry behavior, TP/SL outcomes, ambiguity and operating risk
- Signal Oversight with a first-position date toolbar, plain-language purpose, charts, market-adaptive prices and collapsed lifecycle review
- Strategy Intelligence with selected-period versus all-time TP/SL/ambiguous evidence and current confidence contribution
- Execution & Risk reporting with practice/live separation, protection confirmation, closed profitability and exchange-recorded financial values
- Admin-configurable, deduplicated plan-expiry email thresholds with upcoming audience reporting
- SVG favicon plus PNG/apple fallbacks across public, authentication, Pulse, Admin and recovery layouts
- Executive user-level, package, Spark commerce, content publishing and platform-health reporting
- Structured CMS registers and publishing workflows for News, Research, Learning, Economic Calendar and Products & Services
- No change to V15.0.3 package pricing, no-daily-quota rules, one-minute market scheduler cadence or preserved strategy calculations

Pulse Trading Intelligence is the flagship Alpha Block Solutions trading platform. ABS V14.0 keeps one shared ABS identity, an administrator-controlled plan system and a permission-aware Pulse access environment. The assigned plan determines which Pulse modules, strategies, limits and execution capabilities the user can see and use.

## Accounts, plans and access

- Shared Alpha Block Solutions registration and login
- Administrator-created Pulse plans with customer-facing names, descriptions, pricing and display order
- Default **Pulse Trial** plan created on a clean installation
- Pulse Trial includes the complete current Pulse capability set at plan level for review
- Automatic Trial assignment to new registrations when the Trial plan is active
- Active, pending, suspended, expired and revoked access states
- Optional access start and end dates
- Plan-specific scanner, signal, manual-trade and automatic-trade limits
- Plan-specific maximum open positions and selected markets
- Plan-specific workspace module visibility
- Plan-specific strategy access
- Plan-specific Practice, Manual, Live and Automatic trading eligibility
- Plan-specific mobile API eligibility
- Optional administrator user restrictions that can reduce plan access but can never expand it
- Server-side web and API permission enforcement in addition to hidden navigation

## Administrator-configurable Pulse capabilities

Every plan can independently enable or disable:

1. Market Scanner
2. Signals
3. Orders & Positions
4. Trade History
5. Reports & Performance
6. Binance API Connection
7. Alerts
8. Plan & Access
9. Pulse Settings
10. Mobile API
11. Practice Trading
12. Manual Order Execution
13. Live Trading
14. Automatic Trading

The platform-wide Live and Automatic trading safety switches remain authoritative even when a plan is eligible for those features.

## Market scanner

- Administrator-maintained Binance USD-M Futures market list
- 5m, 15m, 30m, 1h, 2h, 4h and 1d timeframes
- Manual scanner runs from the web workspace or API
- V15.1.0: no daily scanner-run quota; active paid package access includes Best Signal scanning with no per-signal charge
- Per-plan selected-market limit
- Run history, progress and completion status
- Per-market direction, score, entry reference, stop loss, take profit and explanation
- Clear provider-unavailable states
- Scanner completion and failure alerts

## Strategy engine

Pulse includes fifteen configurable strategy modules:

1. Trend Alignment
2. EMA Crossover
3. RSI Recovery
4. MACD Momentum
5. Breakout Confirmation
6. Volume Expansion
7. ATR Volatility Filter
8. Market Structure
9. Trend Pullback
10. Bollinger Reversion
11. Momentum Continuation
12. Range Compression
13. Candle Strength
14. Swing Sequence
15. Risk/Reward Quality

Administrators can enable, disable, order and weight strategies globally and choose which active strategies each Pulse plan can use. Scanner analysis combines the enabled evidence into LONG, SHORT or NEUTRAL output. No sample or fabricated signals are seeded.

## Signals

- LONG, SHORT and NEUTRAL results
- Symbol and timeframe
- Strategy score and confidence label
- Entry reference
- Stop loss and take profit
- Risk-to-reward value
- Strategy-by-strategy evidence
- Active, executed, dismissed and expired lifecycle
- Expiry handling for older active signals
- V15.0.3: no daily signal quota; a new Best Signal costs the Admin-configured Spark amount only when successfully unlocked
- Manual execution only when both Signals and Manual Order Execution are included in the plan

## Binance USD-M Futures connection

- Separate Practice and Live connection records
- Per-user encrypted Binance API key and secret
- Credentials are never displayed after saving
- Connection test against Binance Futures account data
- USDT balance, non-zero positions and open orders
- Exchange-filter synchronization for supported pairs
- Conditional stop-loss and take-profit order support
- Connection status and error reporting
- Withdrawal permission is not required and should remain disabled

## Risk and execution controls

- Signal-only mode
- Manual execution mode when the plan allows it
- Controlled automatic mode when both plan and global controls allow it
- Fixed USDT or fixed-quantity sizing
- Leverage configuration
- Isolated or cross margin configuration
- Minimum signal score
- Default stop-loss and take-profit percentages
- Daily loss limit
- Per-plan daily manual and automatic trade limits
- Per-plan maximum open-position limit
- Market-order submission to Binance USD-M Futures
- Quantity and price normalization using current exchange filters
- Exchange-side STOP_MARKET and TAKE_PROFIT_MARKET protection
- Reduce-only emergency close when protection cannot be confirmed
- User emergency stop
- Platform emergency stop
- Layered installation, system, plan, account, connection and user checks before Live execution

## Orders, positions and trade records

- Binance balances, positions and orders view when enabled by plan
- Side, quantity, entry price, mark price, leverage, liquidation price and unrealized profit/loss
- Local Pulse trade history when enabled by plan
- Binance order and protection-order identifiers
- Manual reduce-only close when manual execution is enabled
- Pending-close state until exchange reconciliation confirms closure
- Trade synchronization
- Fill-based realized profit/loss and commission when Binance fill data is available
- Position-closed and synchronization-error alerts

## Reports and alerts

- Scanner, signal and execution totals
- Open and closed trade totals
- Wins, losses and win rate
- Realized profit/loss and commission
- Scanner, signal, connection, order, position, risk and administrator notices
- Read-one and read-all alert actions
- Module visibility controlled by the assigned plan

## Pulse administration

- Pulse operational dashboard
- Enterprise Memberships & Payments center
- Admin-controlled USDT wallet, network, customer instructions and proof requirement
- Membership request queue with transaction/proof verification and activation/rejection
- Coupon and gift-voucher management with validity, usage limits, plan scope and optional member-specific assignment
- Full-value gift vouchers can optionally auto-activate membership
- Create, edit, activate, disable and delete unassigned plans
- Configure plan descriptions, pricing/currency, access duration, customer visibility, request/payment policy, badge, order, limits and capability switches
- Choose strategies for each plan
- Assign any active plan to a user
- Apply optional per-user restrictions
- Activate, renew, suspend, expire or revoke Pulse access
- Configure Trial auto-assignment, Trial banner and Trial duration
- Strategy creation, weighting and global enable/disable controls
- Supported-market management and Binance filter synchronization
- Signal and trade review
- Platform execution, automation and emergency controls
- Audience-based alert broadcasts
- Pulse audit-log review

## Default Pulse memberships

A clean V13.7 installation prepares three internal plan records:

- **Pulse Trial**: onboarding entitlement for eligible new accounts; it is not a paid pricing card.
- **Pulse Intelligence**: public membership focused on scanner, explainable signals, alerts and reporting without exchange execution.
- **Pulse Professional**: public membership with expanded limits and advanced permission-ready workspace/execution capabilities.

The two public paid memberships are seeded with an unpublished zero commercial price. Payment-required memberships cannot be requested until Admin publishes a real price. Admin also publishes the USDT wallet/network before transfer-based requests can be submitted.

Trial usage is recorded with `user_service_access.trial_used_at`; customer-facing pages do not expose the internal one-time eligibility rule.

## Mobile-ready API

The versioned `/api/v1/pulse` API covers access, public plans, membership status/requests/quotes, member-specific offers, effective capabilities, dashboard, user settings, markets, scanner runs, signals, execution requests, orders, positions, trades, synchronization, closing, Binance connections, alerts and reports.

The API returns `effective_capabilities` for the authenticated user so a future mobile app can present the same plan-controlled interface as the web application. Restricted endpoints also enforce the capability server-side.

See `docs/API_GUIDE.md` and `docs/openapi.yaml`.

## Responsible operation

Practice is the default exchange environment. Live and automatic execution are disabled by default at the installation level. A Pulse plan can make those capabilities eligible, but it cannot bypass the global safety controls. Pulse provides market-analysis and execution tools; it does not guarantee signal accuracy, profit or protection from loss.
