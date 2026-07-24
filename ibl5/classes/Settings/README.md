---
description: Typed accessors for league-wide on/off settings stored in the ibl_settings database table.
last_verified: 2026-07-24
---

# Settings

Provides typed boolean accessors for league-wide settings without requiring callers to know the raw `Yes`/`No` or `On`/`Off` values stored in the `ibl_settings` table. `SettingName` is a backed enum of all canonical setting keys (`AllowTrades`, `AllowWaiverMoves`, `ShowDraftLink`, `TriviaMode`, `FreeAgencyNotifications`, `ASGVoting`). `SettingsService` wraps a `Season` object and exposes each setting as a typed boolean method.

| Class | Role |
|---|---|
| `SettingName` | Backed enum of all canonical `ibl_settings` keys |
| `SettingsService` | Typed boolean accessors over the season settings |
