---
description: A committed corpus of blame-confirmed bug cases and clean cases scores the post-plan reviewer offline, and bin/check-reviewer-eval requires before/after score lines on any PR that changes the review surface.
last_verified: 2026-10-19
---

# ADR-0201: Reviewer eval corpus and before/after score check

**Status:** Accepted
**Date:** 2026-10-19
**Deciders:** a-jay85

## Context

The post-plan reviewer's prompts, agent definitions, and model pins change often, and nothing measured whether a change found more real bugs or raised more false alarms. `bench/benchmark.py` checks output schema only and has no ground-truth bug locations. Master is a linear squash history, so a past PR's reviewer input is reproducible offline as `git diff <commit>^ <commit>`, and `git blame` on a later fix's removed lines ties a bug back to the PR that introduced it.

## Decision

A committed corpus (`tools/postplan-harness/fixtures/reviewer-eval/corpus.json`) holds confirmed bug cases (introducing commit, fix commit, expected line ranges) and clean cases. A case counts as confirmed only when blame corroborates it, which `bin/reviewer-eval validate` checks in CI. `bin/reviewer-eval run` drives the unchanged `ReviewPhase` with a no-op GitHub adapter and prints one score line carrying a review-surface hash and a corpus digest. `bin/check-reviewer-eval` runs on every PR in `pr-meta-checks.yml`. It does nothing when the surface hash is unchanged. When the hash changes, the PR body must carry before and after score lines that match the base and head hashes and the head corpus digest, show no degraded agent, and show no regression unless a reasoned waiver is present.

## Alternatives Considered

- **Extend `bench/benchmark.py`.** Rejected because schema checks give no recall.
- **A committed score ledger.** Rejected because every surface PR would conflict on it.
- **A nightly cron run.** Rejected because it spends LLM money when there is no change to measure.
- **A post-plan arming condition.** Rejected because it would hold only harness-shipped PRs and leave hand-shipped ones unchecked.

## Consequences

- Negative: a review-surface PR pays for two eval runs. The shared cache makes the base run free when the base surface was already scored.
- Negative: the check trusts that the pasted numbers came from a real run. It binds a claimed score to exact surface and corpus content and cannot prove the run happened.
- Positive: `classify.py` and `schemas.py` sit outside the surface hash, so an edit there needs no score.

## References

- `tools/postplan-harness/harness/reviewer_eval.py`
- `tools/postplan-harness/fixtures/reviewer-eval/corpus.json`
- `bin/reviewer-eval`
- `bin/check-reviewer-eval`
- `.github/workflows/pr-meta-checks.yml`
- `ibl5/docs/reviewer-eval.md`
