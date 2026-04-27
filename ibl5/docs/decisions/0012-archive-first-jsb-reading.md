---
description: JSB file reading uses archive-first strategy via JsbSourceResolver — reads .lge and .sch directly from backup ZIP without extracting to disk.
last_verified: 2026-04-27
---

# ADR-0012: Archive-first JSB file reading

**Status:** Accepted
**Date:** 2026-04-25

## Context

The `updateAllTheThings.php` pipeline extracted all 14 JSB file types from the backup archive to `ibl5/` on disk (via `ExtractFromBackupStep`), then subsequent steps read `.lge` and `.sch` from those extracted files. This left binary files lingering in the web-accessible `ibl5/` directory and coupled the pipeline to a disk-extraction intermediate step for files that could be read directly from the archive.

## Decision

Introduce `JsbSourceResolver` (archive-first, disk-fallback) and `ArchiveExtractor::extractToString()` so that `.lge` and `.sch` data is read directly from the backup archive without writing to disk. Both `LgeFileParser` and `SchFileParser` gain a `parse(string $data)` method accepting raw bytes, and `LeagueConfigService` gains a `processLgeData(string $data)` sibling to the existing `processLgeFile()`. The disk path remains as a fallback for manual uploads or when no backup archive exists.

`ExtractFromBackupStep` no longer extracts `.lge` or `.sch` (12 extensions instead of 14). Other file types (`.plr`, `.sco`, etc.) continue to be extracted to disk because their consumers are not yet refactored.

## Alternatives Considered

- **Extract all to disk, then read** — the status quo. Rejected because: files linger in `ibl5/`, no reason for the I/O round-trip on files already available in the archive.
- **Read all 14 file types from archive** — full migration. Rejected because: higher blast radius; `.plr` and `.sco` consumers have more complex calling patterns. The resolver pattern makes it easy to extend later.

## Consequences

- Positive: `.lge` and `.sch` no longer written to the web-accessible `ibl5/` directory during pipeline runs.
- Positive: `parse(string $data)` on both parsers makes them testable without disk I/O.
- Negative: `ArchiveExtractorInterface` gained a new method (`extractToString`), breaking any out-of-tree implementations.
- Negative: RAR archives still require a temp file for `extractToString()` (shell tools cannot stream to PHP memory).

## References

- `ibl5/classes/Updater/JsbSourceResolver.php` — archive-first resolver
- `ibl5/classes/Updater/Contracts/JsbSourceResolverInterface.php` — resolver contract
- `ibl5/classes/BulkImport/Contracts/ArchiveExtractorInterface.php` — `extractToString()` addition
- `ibl5/classes/LeagueConfig/LgeFileParser.php` — `parse(string $data)` method
- `ibl5/classes/Utilities/SchFileParser.php` — `parse(string $data)` method
- `ibl5/classes/LeagueConfig/LeagueConfigService.php` — `processLgeData(string $data)` method
