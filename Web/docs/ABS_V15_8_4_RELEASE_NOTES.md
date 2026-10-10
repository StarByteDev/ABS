# ABS V15.8.4 Release Notes

## Purpose

V15.8.4 makes the Pulse strategy analytics backfill practical on HostGator/shared hosting without SSH and without changing the existing production cron entry.

## Admin backfill control

Strategy Intelligence now contains a Shared-Hosting Maintenance card. Admin can queue 7, 30 or 90 days of retained resolved validation history. The UI reports queued/running/completed/failed/cancelled state, current date, processed/total dates and progress percentage.

## Existing cron remains untouched

The release does not require a second cPanel cron and does not require editing the existing `schedule:run` entry. The already-scheduled `abs:pulse-maintenance` command consumes queued backfill work in bounded slices.

Each maintenance invocation processes at most three retained dates and observes an approximately 20-second work budget. This is intended to reduce shared-hosting timeout and resource pressure.

## Learning/optimizer efficiency

`PulseLearningService::rebuildDate()` now supports deferring the expensive global learning-state refresh. During backfill, individual dates rebuild their derived daily metrics without re-running the full optimizer after every date. Learning state and adaptive R:R evidence are refreshed once when the selected history window is complete. The legacy CLI backfill command receives the same optimization.

## Data safety

V15.8.4 adds no database schema migration. Job state is stored in the existing `pulse_system_settings` table. Historical validations/trades are read as evidence; they are not deleted or rewritten. Cancellation preserves completed chunks.

## Mobile/API

No existing mobile API route or field is removed or renamed. The API build identity is 15.8.4. The V15.8.3 TLS guidance remains applicable because TLS certificate validation occurs before Laravel/API code executes.
