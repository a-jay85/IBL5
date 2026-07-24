---
description: Displays a single team's game schedule with results and upcoming games.
last_verified: 2026-07-24
---

# TeamSchedule

Provides the per-team schedule view used by the Schedule module. `TeamScheduleService` assembles a team's past results and upcoming games from `TeamScheduleRepository`, and `TeamScheduleView` renders the schedule table. This module is composed into the full schedule page by `Schedule\ScheduleController` rather than having its own top-level entry point.

| Class | Role |
|---|---|
| `TeamScheduleRepository` | Queries game results and upcoming games for a team |
| `TeamScheduleService` | Assembles schedule data for a given team |
| `TeamScheduleView` | Renders the per-team schedule table |
