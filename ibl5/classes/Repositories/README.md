---
description: Cross-cutting repositories for team/player/salary lookups, plus shared SQL fragments, used across the codebase
last_verified: 2026-10-23
---

# Repositories

Cross-cutting repositories — narrow lookup interfaces used across the codebase. `TeamIdentityRepository` (team/user/GM lookups), `PlayerLookupRepository` (pid/name resolution), `SalaryCapRepository` (cap calculations). Per-module repositories live with their modules.

`TeamAwardSql` holds the SQL fragments shared by `SeasonArchive\SeasonArchiveRepository::getTeamAwardsByYear()` and `Team\TeamRepository::getTeamAccomplishments()` (award hierarchy `CASE`, HEAT winner subselect, conference-champion join). Constants only; each builder keeps its own `WHERE` predicates and bind order.
