# ABS V14.8.9 Scanner Action Consistency

The Scanner Action column uses a clean trade workflow instead of configuration/admin wording:

- **Review & Trade** — active actionable signal, Manual Execution enabled, selected Binance environment verified.
- **Review Setup** — active actionable signal is available, but one or more execution prerequisites still need attention. The user can still open the protected Trade Execution ticket and see the exact blocked check.
- **Upgrade Access** — current plan does not include manual execution.
- **Daily Limit** — setup qualifies but the daily signal allowance prevented creation of a new signal.
- **Monitor** — evaluated row is not currently trade-ready.

Existing active signals are refreshed against the latest scan instead of being omitted from the latest-run action mapping. Their original generated timestamp is preserved, so a refresh does not consume another signal quota slot.

No database migration is required.
