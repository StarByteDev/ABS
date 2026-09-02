# ABS V14.6 Complete Pulse Workspace

V14.6 completes the signed-in Pulse customer workspace on top of the approved V14.5 dashboard. The implementation uses the final Alpha Block Solutions logo, global navigation, Pulse sidebar and navy–cyan–gold visual system across every page.

## Page map

| Page | Purpose | Primary data source |
| --- | --- | --- |
| Dashboard | Account-level operational overview | Cached public market data, Pulse records, settings and tested connection state |
| Market Scanner | Run an account-specific public-market scan | Binance public USD-M candles and enabled Pulse strategies |
| Signals | Filter and review generated evidence | `pulse_signals` and stored strategy breakdowns |
| Strategies | Explain enabled scoring rules | Plan strategy assignments and `pulse_strategies` |
| Trade Execution | Check readiness and open a reviewed signal | Plan/access controls, settings, tested connection, signals and active trades |
| Open Positions | Compare exchange state with Pulse records | Binance Account V3, Position V3, open normal orders, open algo orders and local trades |
| Trade History | Audit local order/trade lifecycle | `pulse_trades` synchronized from Binance fills and orders |
| Risk Controls | Configure future-execution limits | Pulse user settings, plan maximums and open local trade records |
| Alerts & Watchlists | Review notices and saved markets | `pulse_alerts`, `watchlists`, selected pairs and cached public data |
| Reports & P&L | Period reporting | Created, generated, started and closed timestamps on synchronized records |
| Binance Connection | Manage encrypted credentials | `binance_connections`, Binance Account V3 balances and Futures Account Configuration permission tests |
| Settings | Configure environment, execution, sizing and preferences | Plan-aware Pulse user settings |

## Accuracy rules

- No page inserts sample prices, balances, trades, positions, signals, P&L or performance metrics.
- Missing provider data is shown as unavailable rather than replaced by synthetic values.
- Cached public-market data is labelled separately from direct authenticated exchange snapshots.
- Local Pulse records are never described as exchange-authoritative.
- A signal score expresses weighted evidence strength, not a probability of profit.
- Realized P&L reporting uses synchronized trades closed inside the reporting period.
- Trade activity reporting uses record creation time, so a record can appear in one activity period and a later closed-result period.
- Commission assets remain separate; mixed-asset commissions are stored as a structured breakdown.
- Binance Account Information V3 and Position Information V3 are used for direct account/position snapshots. Futures Account Configuration is used for the authoritative `canTrade` permission because Account Information V3 does not return that field.
- Normal open orders and conditional TP/SL orders remain separate because Binance exposes them through separate endpoints.

## Execution safeguards

- Plan entitlement and user-level restriction checks.
- Installation-wide execution and emergency-stop controls.
- Separate Practice/Testnet and Live eligibility.
- Tested Futures trading permission on the selected connection.
- User-selected signal-only, manual or automatic execution mode.
- Maximum concurrent local order/position records.
- Daily manual/automatic trade limits from the active plan.
- Closed-date daily realized-loss limit.
- User confirmation before an order request.
- Exchange-side TP/SL creation, ID storage, verification and emergency-close handling.
- Clear reminders that acceptance, fill, protection and closure are different exchange events.

## Deployment note

Run the standard MySQL-first setup, migration/repair and queue/scheduler steps from the main README. Live and automatic execution remain disabled unless both environment configuration and platform-wide administrator settings explicitly enable them.

## Binance terminology and endpoint verification

The V14.6 wording and authenticated snapshot behavior were checked against the official Binance USD‑M Futures API documentation current on 11 August 2026:

- Account Information V3: `GET /fapi/v3/account`
- Futures Account Configuration: `GET /fapi/v1/accountConfig`
- Position Information V3: `GET /fapi/v3/positionRisk`
- Current normal open orders: `GET /fapi/v1/openOrders`
- Current conditional algo orders: `GET /fapi/v1/openAlgoOrders`
- New conditional TP/SL order: `POST /fapi/v1/algoOrder`

Official reference: https://developers.binance.com/docs/derivatives/usds-margined-futures/
