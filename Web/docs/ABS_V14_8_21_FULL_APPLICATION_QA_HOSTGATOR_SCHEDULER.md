# ABS V14.8.21 — Full Application QA & HostGator Shared Scheduler

V14.8.21 is a stability and deployment-hardening release built directly on V14.8.20.

## Application-wide QA hardening

- Corrected build identity shown by `abs:about` and aligned web/API/OpenAPI release metadata.
- Added `abs:scheduler-check` for deployment-level scheduler validation.
- Expanded `abs:production-check` to cover the database/environment doctor, every Blade/named-route reference, scheduler profile, public market connectivity, Binance Futures public connectivity, central market-data ingestion and signal validation. It does not submit trades.
- Preserved the V14.8.18 schema-readiness and dashboard runtime hardening.
- Preserved the V14.8.19 single Open Trade flow and V14.8.20 managed trading setup.

## HostGator Shared/Baby scheduler profile

Set `PULSE_SCHEDULER_PROFILE=hostgator_shared` on HostGator Shared/Baby. Laravel then uses a 15-minute cadence for central price/candle ingestion, signal validation, trade synchronization and automation, while heavier maintenance/public-cache jobs are reduced.

A standard/VPS profile remains available and keeps the original once-per-minute scheduler behavior.

## Central market-data optimization

To reduce shared-hosting HTTP load, scanner candle ingestion is still central but prioritizes the union of user-selected Pulse markets, recent signal symbols and core markets. The all-ticker price snapshot remains one shared Binance Futures fetch.

Stored-price freshness is now profile-aware. HostGator production defaults allow the central price snapshot to remain readable across the 15-minute scheduler cycle, while 1-minute validation candles are fetched in batches on the next cycle so Entry/TP/SL event ordering is still evaluated from 1-minute bars.

## Database

No new V14.8.21 migration is required. Run `php artisan abs:repair --seed` after upload to reconcile the existing V14.8.17+ schema.
