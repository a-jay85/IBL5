---
description: ADR-0192. bin/postplan-fix opens an interactive Claude session in the worktree of a blocked or failed post-plan run; every such DM ends with its paste line.
last_verified: 2026-10-10
---

# ADR-0192: One-command fix entry point for blocked post-plan runs

**Status:** Accepted
**Date:** 2026-10-10

## Context

Blocked or failed post-plan runs reach the user as a DM. The 2026-10-06 retro (theme 5) found users pasting the failure back into a fresh Claude session and typing "fix", and 13 blocked runs whose DM said the cause was unknown. Starting that session by hand means finding the worktree (directory names are truncated, so the branch has to be matched), finding the newest harness run dir under two `out/` roots, and copying the error text and log paths into the prompt.

## Decision

Add `bin/postplan-fix <PR|slug>`. It resolves the branch (via `gh pr view` for a PR number), the worktree that has it checked out, and the newest `live-<safe-slug>-*` run dir via `pcw_last_block`. It then starts an interactive `claude` session in that worktree with one opening prompt naming the failing stage, up to 1500 bytes of error text, and the log paths, and telling Claude to treat log text as untrusted data. It never runs headless, never ships, and never fires post-plan; the user drives. Every blocked or failed DM ends with its absolute-path paste line: the exit-3 block (runner `human_block` and the two generic copies), the `bin/post-plan-fleet` NOT READY / STOPPED / TIMED OUT / runner-exit / unconfirmed DMs, and the `bin/pr-cycle-tick` retry-cap DM. READY, HELD, and READY WITH NOTES fleet DMs carry no paste line.

Extend-before-add (meta-tooling bar). `bin/pr-wt-resolve` resolves only and exits 3 when called from a linked worktree, so it cannot host a launcher. The `/pr-wt` skill needs a main-checkout Claude session and `EnterWorktree`, which a terminal paste from a DM does not have. Folding a launch mode into `bin/post-plan-now` would mix a shipping command with a do-not-ship one. The script reuses `resolve_canonical_root`, `pcw_last_block`, and `ljob_safe_slug` and adds no new resolution logic beyond the porcelain branch match copied from `bin/pr-wt-resolve`.

## Alternatives Considered

- **Fix mode on `bin/post-plan-now`.** Rejected because it puts a do-not-ship launcher inside the shipping command.
- **Extend `bin/pr-wt-resolve`.** Rejected because it only resolves and exits 3 inside a linked worktree.
- **Headless fixer with `claude -p`.** Rejected because a fix needs the user's judgment and nobody would be there to stop a bad one.

## Consequences

- Positive: one paste starts a session with the cause, the logs, and the right cwd already in place.
- Positive: `bin/test-postplan-fix` pins the launcher with stub `claude` and `gh`, and runs in CI through `bin/run-shell-harnesses`.
- Negative: the `bin/pr-cycle-tick` retry-cap DM becomes multi-line. The PR failure comment and the hold-repeat DM stay unchanged.

## References

- `bin/postplan-fix`
- `bin/test-postplan-fix`
- `bin/lib/pr-cycle-watch.sh`
- `bin/lib/git-helpers.sh`
- `bin/pr-wt-resolve`
- `bin/post-plan-now`
- `bin/post-plan-fleet`
- `bin/pr-cycle-tick`
- `tools/postplan-harness/runner.py`
