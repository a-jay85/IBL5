---
description: Displays current-season statistical leaderboards with a DatabaseCache-backed decorator for fast page loads.
last_verified: 2026-07-24
---

# SeasonLeaderboards

Presents per-season statistical leaderboards across multiple stat categories. `CachedSeasonLeaderboardsRepository` wraps the live repository with a `DatabaseCache`-backed layer so leaderboard pages don't hit the database on every request. `SeasonLeaderboardsService` assembles the ranked data and `SeasonLeaderboardsView` renders it. Entry point: `ibl5/modules/SeasonLeaderboards/index.php`.

| Class | Role |
|---|---|
| `SeasonLeaderboardsRepository` | Live database queries for stat rankings |
| `CachedSeasonLeaderboardsRepository` | Cache-backed decorator for the live repository |
| `SeasonLeaderboardsService` | Assembles leaderboard data |
| `SeasonLeaderboardsView` | Renders the leaderboard page |
