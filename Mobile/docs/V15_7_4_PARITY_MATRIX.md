# ABS V15.7.4 → Mobile V1.4.1 Parity Matrix

| Web / backend capability | Mobile V1.4.1 treatment |
|---|---|
| Pulse scanner / 15M + 4H | Native scanner, server-authoritative |
| Qualified signals / Entry Watch | Native queue + detail + validation disclosure |
| Free Signal rewarded access | Native rewarded flow with server cooldown |
| Free Signal fallback | Persisted reveal → eligible member signal → BTC 4H context only |
| BTC 4H fallback | No fabricated Entry / SL / TP |
| Strategy validation / learning | Native reports / strategies |
| Trade execution / risk / TP-SL | Native controls, ABS server-authoritative |
| Market intelligence | ABS News, live headlines, research, learning, calendar |
| Direct USDT plan requests | Native membership workflow |
| Help / support | Beginner Help Center + contact workflow |
| Private Investor terms | Native original-currency principal, rate, effective date |
| Automatic monthly performance | Native progress display from backend payload |
| Profit Paid | Displayed separately; documented as zero principal effect |
| Capital Withdrawal | Displayed separately; documented as principal return only |
| Multi-currency | Original principal currency + USD-at-effective-date supported |
| Statements | Native statement list/detail; handles auto-reconciled fields |
| Investor requests | Mobile endpoint fallback with secure web fallback |
| Admin investor controls | Remain web-admin only by design |
| Admin Strategy Lab / engine setup | Remain web-admin only by design |
| Admin reconciliation / destructive controls | Remain web-admin only by design |

## Design rule

Mobile is a client of the ABS backend. It never duplicates Binance secrets, signal qualification,
trading limits, membership enforcement or investor accounting logic locally.
