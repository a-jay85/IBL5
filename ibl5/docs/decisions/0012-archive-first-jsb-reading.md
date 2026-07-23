---
description: JSB file reading uses archive-first strategy via JsbSourceResolver — reads all JSB file types directly from backup ZIP without extracting to disk.
last_verified: 2026-07-22
---

# ADR-0012: Archive-first JSB file reading

**Status:** Accepted
**Date:** 2026-04-25
**Updated:** 2026-05-03

## Context

The `updateAllTheThings.php` pipeline extracted all 14 JSB file types from the backup archive to `ibl5/` on disk (via `ExtractFromBackupStep`), then subsequent steps read `.lge` and `.sch` from those extracted files. This left binary files lingering in the web-accessible `ibl5/` directory and coupled the pipeline to a disk-extraction intermediate step for files that could be read directly from the archive.

## Decision

Introduce `JsbSourceResolver` (archive-first, disk-fallback) and `ArchiveExtractor::extractToString()` so that all JSB file data is read directly from the backup archive without writing to disk. Parsers and services gain `*Data(string $data)` sibling methods to their file-based counterparts. The disk path remains as a fallback for manual uploads or when no backup archive exists.

The migration was completed in three PRs:

1. **PR 1 (#648):** Routed `.lge`, `.sch`, and 10 JSB-parser file types through `JsbSourceResolverInterface`.
2. **PR 2 (#653):** Routed `.plr` files — `PlrParserService` gained `processPlrData()` and `processPlrDataForYear()`.
3. **PR 3:** Routed `.sco` files — `BoxscoreProcessor` gained `processScoData()` and `processAllStarGamesData()`. `ExtractFromBackupStep::EXTENSIONS` is now empty; no file types are extracted to disk.

## Alternatives Considered

- **Extract all to disk, then read** — the status quo. Rejected because: files linger in `ibl5/`, no reason for the I/O round-trip on files already available in the archive.
- **Read all 14 file types from archive in one PR** — full migration. Rejected because: higher blast radius; `.sco` and `.plr` have more complex calling patterns. The resolver pattern made it easy to extend incrementally.

## Consequences

- Positive: No JSB files are written to the web-accessible `ibl5/` directory during pipeline runs.
- Positive: `*Data(string $data)` methods on parsers/services make them testable without disk I/O.
- Positive: `ExtractFromBackupStep` no longer extracts any files — it only handles backup locating and auto-renaming.
- Negative: `ArchiveExtractorInterface` gained a new method (`extractToString`), breaking any out-of-tree implementations.
- Negative: RAR archives still require a temp file for `extractToString()` (shell tools cannot stream to PHP memory).

## References

- `ibl5/classes/Updater/JsbSourceResolver.php` — archive-first resolver
- `ibl5/classes/Updater/Contracts/JsbSourceResolverInterface.php` — resolver contract
- `ibl5/classes/BulkImport/Contracts/ArchiveExtractorInterface.php` — `extractToString()` addition
- `ibl5/classes/Boxscore/BoxscoreProcessor.php` — `processScoData()`, `processAllStarGamesData()` methods
- `ibl5/classes/LeagueConfig/LgeFileParser.php` — `parse(string $data)` method
- `ibl5/classes/JsbParser/SchFileParser.php` — `parse(string $data)` method
- `ibl5/classes/LeagueConfig/LeagueConfigService.php` — `processLgeData(string $data)` method
- `ibl5/classes/PlrParser/PlrParserService.php` — `processPlrData()`, `processPlrDataForYear()`, `calculateFoulBaselineFromData()` methods
