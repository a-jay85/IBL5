---
description: Core league entity, multi-league context (IBL vs Olympics), and team filtering.
last_verified: 2026-07-24
---

# League

Provides league-wide data and multi-league support. `League` is the core entity — it exposes conference/division constants, voting candidates, and team data, extending `BaseMysqliRepository`. `LeagueContext` enables multi-league operation by reading the `ibl_league` cookie and mapping IBL table names to their Olympics equivalents when needed. `OlympicsTeamFilter` filters team lists to Olympics-only teams using an in-memory cache.

| Class | Role |
|---|---|
| `League` | Core entity: conference/division constants, team data |
| `LeagueContext` | IBL vs Olympics context; table-name mapping |
| `OlympicsTeamFilter` | Filters to Olympics-only teams |
