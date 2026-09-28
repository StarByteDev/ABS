# ABS V15.3.0 — Pulse Strategy Lab & Validation Engine

V15.3.0 turns the Admin strategy area into one guided, Admin-only validation workflow. The goal is to let a non-technical administrator answer five questions quickly: Are prices current? Did the engine scan? Which paper trades entered? Which trades won or lost? Which strategies are actually contributing positive evidence?

## Guided workflow

1. **Market Data** — refreshes the enabled market universe and records each price synchronization run.
2. **Strategy Scan** — runs the existing Pulse scanner against the enabled markets/timeframes and stores scan-level evidence.
3. **Paper Trades** — tracks published research signals from waiting-for-entry through entry observation. No real order is implied.
4. **Results** — resolves eligible paper signals as TP, SL, ambiguous, expired-before-entry or expired-after-entry using subsequent stored one-minute candles.
5. **Strategy Evidence** — rebuilds retained strategy metrics and learned reliability from resolved evidence so the Admin can compare win/loss, Net R, model return and reliability.

Every scan remains traceable through its scanner run and signal/validation records. Real Binance execution remains a separate evidence layer and is not mixed with paper results.

## Market-data modes

The Strategy Lab exposes three modes:

- **Production Cron** — for the current HostGator deployment. Effective interval is never below 60 seconds.
- **ABS Internal Scheduler** — for local development or a future VPS. Admin can select 5s, 30s, 60s, 120s or 300s while `php artisan schedule:work` is running.
- **Manual Only** — disables the automatic market/research/validation loop. Admin can refresh prices independently or run one complete research cycle on demand.

`RUN-ABS-LARAGON.bat` starts the Laravel web server and a separate `schedule:work` window for local testing. Automatic cadence schedules the single `abs:pulse-strategy-cycle` pipeline so market refresh → scan → paper reconciliation remain ordered in the same cycle.

## Full-cycle action

**Run Full Cycle** performs, in order:

1. Central market-price/candle refresh.
2. One user-independent Pulse strategy scan.
3. Validation of unresolved research signals against newly stored one-minute candles.
4. Immediate learning rebuild for dates on which a validation resolves.

The action never enables or places live exchange orders.

## Duplicate research protection

A 5-second or 30-second cadence can repeatedly evaluate the same closed 15M/4H candle. If a new research scan produces an equivalent unresolved system signal with the same symbol, timeframe, direction, entry, stop and target, V15.3.0 reuses the existing open signal instead of creating a second paper sample. The scan itself is still stored for audit, but paper win/loss statistics are not inflated by duplicate copies of the same setup.

## Admin UX

The main sidebar now exposes only three Strategy Engine destinations:

- **Pulse Strategy Lab** — normal day-to-day validation overview.
- **Engine Setup** — strategy catalog, market universe and safety controls.
- **Audit & History** — scan-level traceability and deeper evidence.

Inside Strategy Lab, a compact sub-navigation provides Overview, Market Data, Scan Runs and Engine Setup. Technical detail remains available through drill-down links instead of occupying the primary dashboard.

## Main validation measures

The Strategy Lab prioritizes:

- cycles and scans performed;
- paper entries observed;
- TP wins and SL losses;
- decisive win rate = TP / (TP + SL);
- ambiguous/expired outcomes kept outside decisive win rate;
- risk-normalized Net R;
- unlevered research model return percentage;
- per-strategy wins/losses, win rate, Net R, model return and learned reliability.

Model return is research evidence, not an account ROI forecast. It excludes leverage, fees, funding, slippage and compounding. Actual Binance realized P&L remains separate.

## Future Binance automation boundary

V15.3.0 does not turn on automatic trading. Existing server/admin trading gates remain authoritative. A future Binance automation layer can consume the same qualified signals after paper evidence is considered sufficient, while maintaining separate real-order/fill/fee/P&L records for comparison with research results.

## Database and compatibility

No new database migration is required for V15.3.0. Runtime mode/cadence and cycle-state values use the existing `pulse_system_settings` table. Existing V15.1.6 mobile/API contracts remain compatible. Detailed resolved paper-validation evidence now defaults to 90-day retention (override with `PULSE_SIGNAL_VALIDATION_RETENTION_DAYS`) so the Admin can validate strategy results beyond a one-week window. The core `PulseScannerService.php` is intentionally unchanged.

## Fresh local configuration

The release includes a safe `.env.example` with local MySQL defaults, 90-day paper-validation retention and live/automatic trading disabled. `setup-local.bat` copies it only when `.env` does not already exist, so an existing local/production environment file is not overwritten.
