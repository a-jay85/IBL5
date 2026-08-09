---
description: Provides the repository backing the Discord bug report ingestion pipeline.
last_verified: 2026-08-08
---

# BugPipeline

Backs the Discord bug report pipeline. `BugReportRepository` is the entry point every call site constructs. It keeps the `ibl_bug_reports` read path (`findById`, `findByThreadId`, `listPrOpen`, `listActiveConversations`, …), the enqueue writers, and the `ibl_bug_report_attachments` child table on itself, and delegates the rest to three focused sub-repositories. All four share a single `mysqli` handle, so `enqueueAuthorizedAndAdvance()`'s insert-plus-watermark work stays atomic within one transaction:

- **`BugReportClaimRepository`** (`ibl_bug_reports`) — the claim/state-machine writers: single-flight claims, stale-lease reclaim, blocked/backoff resume, `transition()`, and the conditional writers around approvals, thread replies and reminders.
- **`BugReporterProfileRepository`** (`ibl_bug_reporter_profile`) — reporter tech-level upserts and lookups.
- **`BugPipelineStateRepository`** (`ibl_bug_pipeline_state`) — the monotonic per-channel backfill watermark.

Each sub-repository implements an interface under `Contracts/`, and the shared `BugReportRowCasting` trait handles snowflake column precision by casting BIGINT Discord IDs to strings. Call sites construct `BugReportRepository` exactly as before.
