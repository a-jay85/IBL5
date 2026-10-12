---
description: A post-plan harness change that adds or alters concurrency must name the fail-fast change in its PR body and test every enabled agent path in submission order.
last_verified: 2026-10-01
---

# ADR-0156: Disclose fail-fast changes in post-plan harness concurrency PRs

**Status:** Accepted
**Date:** 2026-10-01

## Context

The review fan-out in `tools/postplan-harness/harness/review.py` moved from a sequential loop to a `ThreadPoolExecutor`. The old loop stopped at the first terminal `HarnessError`. The new block submits every agent first, so one terminal error surfaces only after the executor joins every other worker. The remaining agents run to completion and are billed. The diff did not show this change in failure timing, and the PR body did not name it ([backlog#710](https://github.com/a-jay85/IBL5-backlog/issues/710)). The first tests also skipped the gated agents and kept a test class from an abandoned approach.

## Decision

A change that adds or alters concurrency under `tools/postplan-harness/harness/` names the change in failure timing in the Behavior section of its PR body. Its tests exercise every enabled agent path, including the gated agents A, B, D, and security, and assert that aggregate order equals submission order. The convention lives in the path-scoped rule `.claude/rules/post-plan-concurrency-docs.md`. It loads only when a file under `tools/postplan-harness/` is touched. Review enforces it. No gate checks it.

## Alternatives Considered

- **A mechanical gate on the PR body.** Rejected because detecting "this diff changes failure timing" needs judgment a grep cannot make.
- **Fold the guidance into an always-loaded rule.** Rejected because the guidance matters only for harness edits, and an always-loaded rule costs context on every session.
- **Restore fail-fast with explicit cancellation.** Rejected as out of scope. The concurrent behavior is acceptable once it is disclosed.

## Consequences

- Positive: a reviewer sees the billing and failure-timing change without having to infer it from the executor semantics.
- Positive: the test pattern in `tools/postplan-harness/tests/test_review_parallel.py` gives future concurrent blocks a model to copy.
- Negative: compliance depends on the author and reviewer reading the rule. Nothing blocks a PR that skips the disclosure.

## References

- `.claude/rules/post-plan-concurrency-docs.md`
- `tools/postplan-harness/harness/review.py`
- `tools/postplan-harness/tests/test_review_parallel.py`
- [backlog#710](https://github.com/a-jay85/IBL5-backlog/issues/710)
