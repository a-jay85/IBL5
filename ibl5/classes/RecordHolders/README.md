---
description: Tracks all-time IBL statistical records, detects record-breaking performances after each sim, and sends Discord notifications.
last_verified: 2026-07-24
---

# RecordHolders

Maintains and displays the canonical list of all-time IBL statistical records. After each simulation, `RecordBreakingDetector` scans for broken or tied records and dispatches announcements via `DiscordAnnouncementDispatcher`. `CachedRecordHoldersService` decorates the live service with a `DatabaseCache`-backed layer for fast page loads. `RecordStatDefinitions` is the single source of truth for all tracked record categories. Entry point: `ibl5/modules/RecordHolders/index.php`.

| Class | Role |
|---|---|
| `RecordBreakingDetector` | Detects broken/tied records post-sim; triggers Discord |
| `CachedRecordHoldersService` | Cache-backed decorator for the live service |
| `RecordStatDefinitions` | Canonical list of all tracked record categories |
| `RecordHoldersService` | Live record retrieval and assembly |
| `RecordHoldersRepository` | Database queries for record data |
| `RecordHoldersView` | Renders the record holders page |
| `DiscordAnnouncementDispatcher` | Sends record-break announcements to Discord |
| `StreakCalculator` | Calculates consecutive-game streaks for records |
