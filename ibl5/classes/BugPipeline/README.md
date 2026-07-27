---
description: Provides the repository backing the Discord bug report ingestion pipeline.
last_verified: 2026-07-26
---

# BugPipeline

Backs the Discord bug report pipeline. `BugReportRepository` is a thin facade that delegates to three focused sub-repositories, each owning one table and sharing a single `mysqli` handle so cross-table work stays atomic within one transaction:

- **`BugReportClaimRepository`** (`ibl_bug_reports`) — the claim/state-machine queue: huntable claims, blocked/backoff handling, reminders, PR-open tracking, and active-conversation reads.
- **`BugReporterProfileRepository`** (`ibl_bug_reporter_profile`) — reporter profile upserts and lookups.
- **`BugPipelineStateRepository`** (`ibl_bug_pipeline_state`) — per-pipeline state reads and writes.

Each sub-repository implements an interface under `Contracts/`, and the shared `BugReportRowCasting` trait handles snowflake column precision by casting BIGINT Discord IDs to strings. Call sites construct `BugReportRepository` exactly as before.
