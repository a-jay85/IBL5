---
description: Phase 4 review depth is standard or deep, picked from the PR body's Merge danger line. One-way doors, site-wide or external blast radius, and a missing line select deep, which raises agent tiers and never skips an agent.
last_verified: 2026-10-20
---

# ADR-0202: Phase 4 review depth follows the Merge danger line

**Status:** Accepted
**Date:** 2026-10-19
**Deciders:** a-jay85

## Context

`/post-plan` Phase 4 picks review agents by diff shape only: PHP or not, more than 50 PHP lines changed, E2E specs present. A one-line change to an arming path gets the same review as a one-line CSS tweak. Since PR [#3151](https://github.com/a-jay85/IBL5/pull/3151) the PR body carries a `**Merge danger:**` line (Door one-way or two-way, Blast radius localized, module, site-wide or external), written in Phase 2 before Phase 4 runs. Until now only Phase 6.5 condition (18) read it, and only its Door.

## Decision

Two tiers. `standard` is the review that ran before this ADR, unchanged. `deep` applies when Door is one-way, or Blast radius is site-wide or external, or the line is missing, malformed or unreadable.

Deep runs Agent A and the security agent one tier up (harness: Sonnet to Opus for both; skill: Agent A to Opus, 4C Sonnet 5.5 to Opus). It forces Agent B's history check on any code PR, and in the skill it launches Agent C regardless of PHP line count. Deep never skips an agent standard runs, and there is no lighter tier.

The harness reads the deeper of the Phase-2 body and the live PR body (`tools/postplan-harness/harness/review_depth.py`, wired in `tools/postplan-harness/runner.py`). The skill runs master's copy of `bin/lib/review-depth.sh` via `git show <MASTER_SHA>:`, and treats any failure, including the script being absent on master, as deep. The harness already runs from the main checkout (ADR-0092). Neither engine lets a PR lower its own review depth. A deep run adds a `depth` key to the review gates, so a cached standard review never satisfies a deep run.

## Alternatives Considered

- A third `quick` tier for two-way localized PRs. Rejected because it would skip agents that run today and weaken the review gate.
- Extend `pr_merge_danger_hold` in `bin/lib/pr-armable.sh`. Rejected because that file is an arming path, so the edit would make this change a bootstrap hazard.
- Read only the live PR body in the harness. Rejected because on an existing PR, Phase 2 does not update the live body, so it can be stale.

## Consequences

- Negative: deep PRs cost more review tokens (Opus calls in place of Sonnet).
- Positive: findings still pass the unchanged 4D scorer and thresholds and reach arming only through the existing conditions (2) and (11).
- Positive: the new depth logic lives outside the three arming paths in `_ARMING_PATHS` in `tools/postplan-harness/harness/gate_backtest.py`. `bin/lib/pr-armable.sh` and `tools/postplan-harness/harness/armable.py` are unchanged.
- Positive: Python and shell share one case table, `tools/postplan-harness/tests/fixtures/review_depth_cases.json`, pinned by a parity test.

## References

- [`bin/lib/review-depth.sh`](../../../bin/lib/review-depth.sh): the shell twin the skill runs from master.
- `tools/postplan-harness/harness/review_depth.py`: the Python classifier.
- `tools/postplan-harness/tests/test_review_depth_parity.py`: the parity test over the shared case table.
- `tools/postplan-harness/harness/review_depth.py`: the harness selects the depth.
