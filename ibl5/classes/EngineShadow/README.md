---
description: Orchestrates a full-season shadow sim, loading each game result into shadow boxscore tables.
last_verified: 2026-07-24
---

# EngineShadow

Orchestrates full-season shadow simulations for validation. `EngineShadowRunService` drives the full sequence: build the engine bundle, stream NDJSON output, and load each game into shadow boxscore tables. `ShadowProcessLauncher` handles spawning the sim process. `EngineShadowRunSummary` aggregates run results. Entry point: `ibl5/scripts/runEngineShadow.php`.

| Class | Purpose |
|-------|---------|
| `EngineShadowRunService` | Top-level shadow sim orchestrator |
| `EngineShadowRepository` | Database reads and writes for shadow tables |
| `EngineShadowLoader` | Loads NDJSON game results into shadow DB tables |
| `ShadowProcessLauncher` | Spawns the sim process |
| `EngineShadowRunSummary` | Aggregates run statistics |
