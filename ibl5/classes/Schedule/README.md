---
description: Page controller that composes the league and team schedule views into a unified schedule page.
last_verified: 2026-07-24
---

# Schedule

`ScheduleController` is a thin page controller that wires together the `LeagueSchedule` and `TeamSchedule` modules with `Standings` to produce the full schedule page. It does not own repositories or views directly — it composes the sub-modules' services and views into a single response. Entry point: `ibl5/modules/Schedule/index.php`.
