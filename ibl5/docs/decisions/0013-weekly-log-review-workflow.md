---
description: ADR for weekly production log review via GitHub Action and Discord DM notification.
last_verified: 2026-04-26
---

# ADR-0013: Weekly Log Review Workflow

**Status:** Accepted
**Date:** 2026-04-26

## Context

Production logs (Monolog JSON, daily rotation, 30-day retention) were never reviewed systematically. Slow queries, recurring warnings, and error patterns went unnoticed until they caused visible user-facing problems. The existing monitoring (smoke-prod health checks) only catches hard failures, not gradual degradation.

## Decision

Add a weekly GitHub Action (`log-review.yml`) that SSHes to production, runs `bin/log-fetch-prod` to aggregate logs server-side into a compact digest, and sends the summary as a Discord DM via IBLbot. The digest script runs all aggregation remotely to minimize data transfer, with a `jq` fast path and a `grep`/`sed`/`awk` fallback for hosts without `jq`.

## Alternatives Considered

- **Claude Code scheduled routine (CronCreate)** — Runs locally with full SSH access. Rejected because: 7-day auto-expiry makes it unreliable for persistent monitoring.
- **Claude Code remote routine (/schedule)** — Persistent but runs on Anthropic infrastructure. Rejected because: no SSH access to production server.
- **Cron job on production server** — No token cost, fully persistent. Rejected because: no Claude analysis in the loop, and server-side cron management is manual.

## Consequences

- Positive: Weekly visibility into slow queries and error patterns with zero manual effort.
- Positive: Reuses existing CI secrets and SSH/Discord patterns from smoke-prod.
- Negative: No AI-powered analysis in the automated loop (digest is plain text, not interpreted). Analysis requires manual review or a follow-up local Claude session.

## References

- `.github/workflows/log-review.yml` — the weekly workflow
- `bin/log-fetch-prod` — server-side log aggregation script
- `.github/workflows/smoke-prod.yml` — SSH and Discord DM patterns reused
- `ibl5/classes/Logging/LoggerFactory.php` — log format and channel configuration
- `ibl5/classes/BaseMysqliRepository.php` — slow query logging (channel `perf`)
