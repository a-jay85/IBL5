---
description: ADR-0189. Every bin/ script resolves the plans directory through plans_dir in bin/lib/plan-resolve.sh, pinned by bin/test-plans-dir.
last_verified: 2026-10-08
---

# ADR-0189: One plans_dir resolver for every plans-directory lookup

**Status:** Accepted
**Date:** 2026-10-08

## Context

Six `bin/` scripts spelled the plans folder themselves as `${SEAM:-$HOME/claude-plans}` or a bare `$HOME/claude-plans`. Each one carried its own test seam variable (`PLAN_NOW_PLANS_DIR`, `PLANS_DIR`, `BUG_PIPELINE_PLANS_DIR`, `PLAN_DIR`). Moving the folder or changing the fallback meant editing every copy, and nothing caught a copy that drifted.

## Decision

`plans_dir [SEAM_VAR ...]` in `bin/lib/plan-resolve.sh` is the only place that spells `$HOME/claude-plans`. A caller passes the names of its own seam variables. The first non-empty one wins, else the default. `$HOME` is read at call time. `bin/test-plans-dir` runs in `.github/workflows/tests.yml`. It tests the function and evaluates each caller's real resolution line, so a caller that goes back to a hardcoded path fails CI.

## Alternatives Considered

- One shared seam variable for all scripts. Rejected because existing tests set the per-script names, and one name would let one script's test leak into another.
- A shared constant set at source time. Rejected because it caches `$HOME` and cannot express seam precedence.

## Consequences

- Positive: changing the plans location is a one-line edit.
- Positive: each script keeps its own test seam.
- Negative: a test sandbox that copies one of these scripts must also copy `bin/lib/plan-resolve.sh`, or the script fails at startup.

## References

- `bin/lib/plan-resolve.sh`
- `bin/test-plans-dir`
- `bin/post-plan-now`
- `bin/plan-now`
