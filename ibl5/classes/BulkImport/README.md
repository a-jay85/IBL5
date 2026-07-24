---
description: Orchestrates bulk import of JSB sim files from season archives into the database.
last_verified: 2026-07-24
---

# BulkImport

Orchestrates batch ingestion of JSB sim files from season archives. `BulkImportRunner` drives the full import sequence — locating archives, extracting files, dispatching them by type, and writing results to the database. `BulkImportSummary` aggregates per-run counts (filesProcessed, inserted, updated, skipped, errors). Entry point: `ibl5/scripts/bulkJsbImport.php`.

| Class | Purpose |
|-------|---------|
| `BulkImportRunner` | Top-level import orchestrator |
| `BulkImportSummary` | Aggregates import result counts |
| `ArchiveExtractor` | Extracts files from season archive packages |
| `BackupArchiveLocator` | Finds archive files to process |
| `FileTypeHandler` | Dispatches files to the correct importer by type |
| `ImportEntry` | Value object representing one file import record |
| `JsbFileType` | Handles JSB-format file imports |
