# ABS V14.8.4 Mobile API Additions

V14.8.4 preserves all V14.8.3 endpoints and adds package-upgrade state to existing Pulse access, plans and membership responses.

## GET /api/v1/pulse/access

Adds `next_upgrade_plan`.

## GET /api/v1/pulse/plans

Each returned plan includes:

- `is_current_plan`
- `is_next_upgrade`
- `subscription_state` (`active`, `next_upgrade`, or `available`)
- existing capability matrix

The response also includes top-level `next_upgrade_plan`.

## GET /api/v1/pulse/membership

Adds `next_upgrade_plan` and returns the same package-state fields in the plan collection.

This lets the Flutter app mirror the web behavior: current tier visibly active, next tier promoted, and downgrade-only tiers omitted from the active user's upgrade path.
