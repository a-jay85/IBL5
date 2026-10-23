---
description: Provides the repositories backing the Discord bug report ingestion pipeline, including the dormant status and the operator wake and context log.
last_verified: 2026-10-22
---

# BugPipeline

Backs the Discord bug report pipeline. `BugReportRepository` is the entry point every call site constructs. It keeps the `ibl_bug_reports` read path (`findById`, `findByThreadId`, `listPrOpen`, `listActiveConversations`, …), the enqueue writers, and the `ibl_bug_report_attachments` child table on itself, and delegates the rest to three focused sub-repositories. All four share a single `mysqli` handle, so `enqueueAuthorizedAndAdvance()`'s insert-plus-watermark work stays atomic within one transaction:

- **`BugReportClaimRepository`** (`ibl_bug_reports`) — the claim/state-machine writers: single-flight claims, stale-lease reclaim, blocked/backoff resume, `transition()`, and the conditional writers around approvals, thread replies and reminders.
- **`BugReporterProfileRepository`** (`ibl_bug_reporter_profile`) — reporter tech-level upserts and lookups.
- **`BugPipelineStateRepository`** (`ibl_bug_pipeline_state`) — the monotonic per-channel backfill watermark.

Each sub-repository implements an interface under `Contracts/`, and the shared `BugReportRowCasting` trait handles snowflake column precision by casting BIGINT Discord IDs to strings. Call sites construct `BugReportRepository` exactly as before.

## Dormant rows and the operator wake

Enqueue writes status `dormant`. A dormant row is never actionable: `listActiveConversations()`, `claimNextHuntable()` and the tick all skip it (ADR-0213). Only the league operator moves it on, through `BugReportRepository::wakeByOperator()`, which returns one of four outcomes:

- `woke`: the row was `dormant` and is now `queued` with its thread id set.
- `restarted`: the row was `needs_human`, `parked_idle` or `dropped`. It returns to `queued` with its class, lease, backoff, approval message and hunt attempts cleared, so classify runs again with the operator's text.
- `appended`: any other status. The context is stored and the status stays put.
- `duplicate`: this Discord message was already recorded. Nothing changes.

An unknown source message returns `unknown` and writes nothing.

`BugReportOperatorContextRepository` owns the `ibl_bug_report_operator_context` child table. Each operator mention is one row, with content capped at 4000 characters. The `uq_operator_message` unique key on the Discord message id makes a replayed wake a no-op, because the insert is an `INSERT IGNORE`. `listForReportIds()` returns message ids as strings so snowflakes keep their precision.
