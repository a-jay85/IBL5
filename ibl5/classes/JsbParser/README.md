---
description: Parse and write JSB simulation engine file formats for import/export between the DB and the engine.
last_verified: 2026-07-24
---

# JsbParser

Handles all file I/O between IBL5's database and the JSB simulation engine. On import, `JsbImportService` orchestrates ingestion of all JSB file types produced after a sim run — each `*FileParser` reads a specific format (`.asw`, `.awa`, `.car`, `.dra`, `.his`, `.hof`, `.plb`, `.rcb`, `.ret`, `.sch`, `.sco`, `.trn`), and the corresponding class in `Importers/` persists the parsed data. On export, `JsbExportService` builds `.plr` and `.trn` files from DB state as input for the next engine run. `PlayerIdResolver` maps JSB player identifiers to database PIDs; `ScoFileWriter` and `TrnFileWriter` serialize DB state back to the engine's text formats.

| Class | Role |
|---|---|
| `JsbImportService` | Orchestrates post-sim import of all JSB file types |
| `JsbExportService` | Builds engine input files from DB state |
| `*FileParser` classes | Parse individual JSB file formats |
| `Importers/` | Persist parsed data to the database |
| `PlayerIdResolver` | Maps JSB player IDs to database PIDs |
| `ScoFileWriter` / `TrnFileWriter` | Serialize DB state to engine file formats |
