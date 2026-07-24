---
description: Displays season-high statistical performances for players and teams.
last_verified: 2026-07-24
---

# SeasonHighs

Tracks and displays the best single-game statistical performances of the current season for players and teams. `SeasonHighsService` assembles the data from `SeasonHighsRepository` and passes it to `SeasonHighsView` for rendering. Entry point: `ibl5/modules/SeasonHighs/index.php`.

| Class | Role |
|---|---|
| `SeasonHighsRepository` | Queries for peak single-game performances |
| `SeasonHighsService` | Assembles season-high data for display |
| `SeasonHighsView` | Renders the season highs page |
