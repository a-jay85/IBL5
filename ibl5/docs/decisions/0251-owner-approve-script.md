---
description: An owner's chat approval of a PR arms auto-merge only through bin/owner-approve, which posts the approval first and then arms only when a post-plan READY verdict covers the PR head's tree.
last_verified: 2026-11-13
---

# ADR-0251: Arm owner-approved PRs through `bin/owner-approve`, bound to the head's tree

**Status:** Accepted
**Date:** 2026-11-13
**Deciders:** ajaynicolas

## Context

The owner-approval rule told a session to post a comment, and `pr-burndown` told a different session to add `human-approved` and run `gh pr merge --auto` by hand. Nothing checked that the post-plan verdict covered the head being armed. Nothing waited out a live post-plan run. The comment needed hand-escaped quoting. A push after the verdict, or a rebase, could be armed on a verdict for older content.

## Decision

An owner's chat approval goes through `bin/owner-approve N "<words>"`. The script posts the approval comment first. It then waits up to `--timeout` seconds (default 3600) for any live post-plan run on the PR. It adds `human-approved` and runs `gh pr merge --squash --auto --match-head-commit <head>` only when four checks pass:

1. The newest verdict comment was posted by the current `gh` login.
2. Its banner word is `READY` or `READY WITH NOTES`.
3. Its reviewed tree (the last `**Re-reviewed tree:**` line, else the last `**Reviewed tree:**` line) equals the tree of the PR head commit.
4. GitHub accepts the same head through `--match-head-commit`.

Every other outcome fails closed: a missing or `unrecorded` tree line, a `gh` read error, a foreign author, a `NOT READY` banner, or a tree mismatch such as a rebase produces. The script posts a reason comment and exits 4. If the merge call fails after the label was added, it removes the label again. It never uses `--admin`.

When the owner wants a fix first, `--fix-first="<condition>"` records the condition and the head in a hidden `<!-- owner-approve mode=… head=… -->` line of the approval comment and exits 6. After the fix is pushed, `--finish` requires a new head, the PR's worktree clean at that head, and a pass from `bin/lib/prefire-verify` there. It then waits, checks the verdict, and arms. The comment is the record, so no state file exists. `CLAUDE_HEADLESS` makes the script refuse and post nothing.

The human-signoff hold and its classifiers do not change. The script adds `human-approved` at the moment `pr-burndown` added it by hand, after the owner's words. It adds three refusals that did not exist before: the verdict check, the tree binding, and the live-run wait. Enforcement and tests: `bin/owner-approve`, `bin/test-owner-approve`, and CI leg 1 in `.github/workflows/harness-legs.txt`.

## Alternatives Considered

- Binding on the head sha. The sticky records only a tree, so this needs a post-plan harness edit, and it refuses an empty rebase that left the content identical. Rejected because: the harness is out of scope and a squash merge depends on content.
- Reading `reviewed_head_sha` from the harness state file. Rejected because: the file lives on one machine and is missing on another.
- Holding fix-first state in a file under `~/.cache`. Rejected because: it is a second source of truth the PR never shows. The approval comment already survives a session change.
- Blocking in the background until the fix is pushed. Rejected because: a fix has no time bound, so the process would outlive its caller. The wait for a post-plan run is bounded, so the script owns that wait.

## Consequences

- Positive: a push or rebase after the verdict can no longer be armed on the old verdict.
- Positive: every session arms through one tested path, and the comment's quoting is no longer hand-escaped.
- Negative: a sticky layout without a tree line refuses. That is the safe failure and needs a human to re-run post-plan.
- Negative: the rule now widens who runs the arm, from `pr-burndown` sessions to any session that sees an owner approval.

## References

- `bin/owner-approve`
- `bin/test-owner-approve`
- `.claude/rules/auto-commit.md`
- `.claude/skills/pr-burndown/SKILL.md`
- `bin/lib/held-prs.sh`
- `bin/lib/prefire-verify`
