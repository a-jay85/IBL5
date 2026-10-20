---
description: Entity and repository for current season phase, dates, settings, and configuration; consumed by nearly every module.
last_verified: 2026-10-20
---

# Season

`Season` is the central entity for the current season — it exposes season phase, sim dates, and league settings, delegating database queries to `SeasonQueryRepository`. Almost every module that depends on season state injects a `Season` instance rather than querying the database directly. `SeasonQueryRepository` handles the underlying queries for season settings, sim dates, and phase calculations.

| Class | Role |
|---|---|
| `Season` | Entity: season phase, dates, settings, configuration |
| `SeasonQueryRepository` | Database queries for season settings and phase |

## Request-scoped instance (`SharedSeason`)

Web and API code gets its Season from `\Season\SharedSeason::get($db, $leagueContext)`. Do not write `new Season(...)` outside `ibl5/classes/Updater/`, `ibl5/scripts/`, and tests.

- **Armed** (web and API requests, by `Bootstrap\SharedSeasonBootstrap`): one Season per database connection and argument league (`''` for no context, `ibl`, `olympics`). Every consumer in the request shares it, so the settings, schedule, and sim-date queries run once.
- **Unarmed** (default; CLI, PHPUnit, the Updater): `get()` returns a fresh Season on every call.
- **Writes.** Every `LeagueControlPanelRepository` settings write calls `SharedSeason::reset()`, so a read later in the same request rebuilds. `ibl5/scripts/updateAllTheThings.php` calls `SharedSeason::disable()` before its first Season build.
- **Never mutate the shared instance.** Compute a local value instead (see `UI\Tables\Contracts::render`).
- **Tests** that call `enable()` must call `SharedSeason::disable()` in `tearDown()`.
