---
description: Builds and serializes the engine input bundle JSON from the live database for a sim window.
last_verified: 2026-07-24
---

# EngineBundle

Builds the JSON input bundle that the native sim engine consumes. `EngineBundleService` assembles the bundle from live database state for a given sim window and fails fast via `EmptyRosterException` or `EmptyScheduleException` if the data is incomplete. `BundleSerializer` serializes the resulting DTO to JSON for the engine binary. `EngineBundleRepository` handles all database reads for bundle construction.

| Class | Purpose |
|-------|---------|
| `EngineBundleService` | Assembles the engine input bundle from live DB state |
| `EngineBundleRepository` | Database reads for bundle data |
| `BundleSerializer` | Serializes the bundle DTO to JSON |
| `EmptyRosterException` | Thrown when roster data is missing |
| `EmptyScheduleException` | Thrown when schedule data is missing |
