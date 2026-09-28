# ABS Pulse Mobile V1.5.1+151 — Flutter Compile Hotfix

**Backend target:** ABS V15.7.4

This release is a focused compile hotfix for V1.5.0. The supplied template contained `FontWeight.w650`, but Flutter only exposes FontWeight values in 100-step constants (`w100` ... `w900`). The invalid value caused `kernel_snapshot_program` / `compileFlutterBuildDebug` to fail before the app could launch.

## Fix

- `lib/template_ui/common.dart`: `FontWeight.w650` → `FontWeight.w700`.
- Added source validation for unsupported FontWeight constants.
- Version bumped to `1.5.1+151`.

No ABS Pulse backend/API contract or feature behavior was changed.
