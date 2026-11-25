---
description: Retired /post-plan skill (ADR-0284). bin/post-plan-now runs the compiled harness only; this stub keeps path citations resolving.
last_verified: 2026-11-24
---

# /post-plan (retired)

bin/post-plan-now ships every run through the compiled harness in tools/postplan-harness/. A harness crash posts a failure comment on the PR, sends the crash DM and stops. The retired skill never runs `gh pr merge`, and no runner invokes it.

If this skill loads, stop and tell the user to run bin/post-plan-now from the worktree.

Tests and docs still cite the sibling files in this directory, so they stay.
