---
description: Entity and repository for current season phase, dates, settings, and configuration; consumed by nearly every module.
last_verified: 2026-07-24
---

# Season

`Season` is the central entity for the current season — it exposes season phase, sim dates, and league settings, delegating database queries to `SeasonQueryRepository`. Almost every module that depends on season state injects a `Season` instance rather than querying the database directly. `SeasonQueryRepository` handles the underlying queries for season settings, sim dates, and phase calculations.

| Class | Role |
|---|---|
| `Season` | Entity: season phase, dates, settings, configuration |
| `SeasonQueryRepository` | Database queries for season settings and phase |
