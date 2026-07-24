---
description: Full league game schedule with results, used by the Schedule module.
last_verified: 2026-07-24
---

# LeagueSchedule

Provides schedule data and game results across the full league season. `Game` is a value object representing a single scheduled game. Follows the Repository/Service/View pattern and is consumed by the Schedule module rather than having its own module entry point.

| Class | Role |
|---|---|
| `LeagueScheduleRepository` | Fetches schedule and game result data |
| `LeagueScheduleService` | Business logic and data assembly |
| `LeagueScheduleView` | Renders the schedule page |
| `Game` | Value object for a single game |
