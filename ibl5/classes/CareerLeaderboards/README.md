---
description: Displays all-time career statistical leaderboards with a database-cache decorator for performance.
last_verified: 2026-07-24
---

# CareerLeaderboards

Displays all-time career statistical leaderboards. Follows the Repository/Service/View pattern, with `CachedCareerLeaderboardsRepository` providing a `DatabaseCache`-backed decorator around the live repository to avoid expensive repeated queries. Entry point: `ibl5/modules/CareerLeaderboards/index.php`.

| Class | Purpose |
|-------|---------|
| `CareerLeaderboardsRepository` | Live database queries for career stats |
| `CachedCareerLeaderboardsRepository` | DatabaseCache decorator wrapping the live repository |
| `CareerLeaderboardsService` | Business logic and ranking |
| `CareerLeaderboardsView` | Renders the leaderboard HTML |
