---
description: When a change adds or alters concurrency in the post-plan harness, the PR body names the fail-fast change and the tests cover every enabled agent path in submission order.
last_verified: 2026-10-01
paths: "tools/postplan-harness/**"
---

# Post-Plan Harness Concurrency Changes

This rule applies when a change adds or alters concurrency in `tools/postplan-harness/harness/`. That covers a `ThreadPoolExecutor`, a worker pool, or any edit that changes when a failure surfaces. The incident behind it is [backlog#710](https://github.com/a-jay85/IBL5-backlog/issues/710).

## Disclose the fail-fast change in the PR body

The Behavior section of the PR body names the change in failure timing. The diff alone does not show it.

The review fan-out in `tools/postplan-harness/harness/review.py` shows the mechanism. Every agent task is submitted to the executor before any result is read. When one agent raises a terminal `HarnessError`, `f.result()` re-raises it. The exception leaves the `with ThreadPoolExecutor` block only after the executor joins every other worker. So one terminal failure no longer stops the remaining agents. They run to completion and are billed. The old sequential loop stopped at the first terminal error.

Write that consequence in plain words. For example: "A terminal error in one review agent no longer stops the others. All enabled agents run before the error surfaces."

## Cover every enabled agent path in tests

Tests for a concurrent block exercise every enabled agent path. That includes the gated agents A, B, D, and security. Assert that the aggregate order equals the submission order.

Copy the pattern in `tools/postplan-harness/tests/test_review_parallel.py`. The test `test_all_four_agents_preserve_submission_order` enables all four agents and checks their order. The test `test_agents_overlap_in_time` proves the agents run concurrently.

Delete a test class scaffolded for an approach you abandoned. A leftover class tests code that no longer ships.

This is a standalone lazy rule. No other rule or doc governs it, and it attaches only when a file under `tools/postplan-harness/` is touched.
