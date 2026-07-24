---
description: Next scheduled simulation date and upcoming game display.
last_verified: 2026-07-24
---

# NextSim

Displays the date of the next scheduled simulation run and the upcoming games for that sim. Has no repository of its own — `NextSimService` reads from the existing Season and LeagueSchedule classes. `NextSimTabApiHandler` serves tab-level API requests for the page. Entry point: `ibl5/modules/NextSim/index.php`.
