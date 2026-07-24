---
description: Parses and writes the JSB simulation engine's .plr fixed-width player-record files.
last_verified: 2026-07-24
---

# PlrParser

Parses and reconstructs the JSB simulation engine's `.plr` format — 607-byte fixed-width records describing every player's accumulated stats and ratings. `PlrParserService` runs a two-pass import: the first pass calculates a league-wide foul baseline, and the second upserts each player record into the database. `PlrLineParser` handles a single record using documented byte offsets. `PlrReconstructionService` is the inverse — it reads database state and writes `.plr` files back for engine input.

| Class | Role |
|---|---|
| `PlrParserService` | Two-pass import orchestrator; upserts parsed records |
| `PlrLineParser` | Pure parser for a single 607-byte record |
| `PlrReconstructionService` | Reconstructs `.plr` files from database state |
| `PlrBoxScoreRepository` | Box score data access for reconstruction |
| `PlrFileWriter` | Writes serialized `.plr` output to disk |
| `PlrFieldSerializer` | Serializes individual fields to fixed-width format |
| `PlrSimDateInferrer` | Infers the sim date from file contents |
