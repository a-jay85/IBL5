---
description: Provides the repository backing the Discord bug report ingestion pipeline.
last_verified: 2026-07-24
---

# BugPipeline

Backs the Discord bug report pipeline with a single repository class, `BugReportRepository`. It handles queue reads and writes for incoming Discord bug reports, including correct handling of snowflake column precision by casting BIGINT Discord IDs to strings.
