---
description: ADR for advisory 500-LOC hot-file threshold rule and bin/check-hot-files script
last_verified: 2026-05-16
---

# ADR-0026: Hot-File Threshold Rule

**Status:** Accepted
**Date:** 2026-05-16

## Context

A codebase audit (2026-05-14) found 23 files in `ibl5/classes/` exceeding 500 lines of code. Several god-class hotspots crossed this threshold silently over time. Nothing in the plan workflow flags hotspot creep at plan-write time, meaning the same hotspots rediscovered in one audit will reappear in the next unless addressed structurally.

## Decision

1. Add a "Hot-file thresholds" section to `.claude/rules/plan-verification.md` requiring plans that add > 100 LOC to a > 500 LOC file to either propose extraction or justify the addition inline.
2. Add `bin/check-hot-files` — an advisory script that lists current hotspots and, with `--pr`, flags files in the current PR that grew > 100 LOC past the threshold.
3. Add a CI workflow (`.github/workflows/hot-files.yml`) that runs the script on PRs and posts a sticky comment. Non-blocking (advisory only).

## Threshold Rationale

500 LOC is approximately one-third the size of the largest hotspot (RecordHoldersRepository at 995 LOC) and well above typical Service/View sizes (200–400 LOC). The > 100 LOC growth trigger avoids noise from minor additions while catching significant expansion.

## Alternatives Considered

- **Hard-blocking PRs on threshold violation** — rejected as too restrictive. Growth is acceptable when justified; the goal is to force a structural conversation, not enforce a hard ceiling.
- **Lower threshold (300 LOC)** — too noisy; most healthy Services land in the 200–400 range.
- **Higher threshold (750 LOC)** — only catches the most extreme cases, missing mid-range creep.

## Consequences

- Positive: Plans must acknowledge hotspot growth explicitly, preventing silent creep.
- Positive: PR comments surface the current hotspot list, keeping visibility high.
- Negative: Advisory comments may cause minor fatigue until existing hotspots are addressed by other plans.
- Negative: The threshold is arbitrary and may need tuning after 6 months of data.

## Review Trigger

After 6 months (2026-11-16), evaluate whether 500 LOC is the right threshold. If the comment fires on > 50% of PRs, raise it; if it never fires, lower it.
