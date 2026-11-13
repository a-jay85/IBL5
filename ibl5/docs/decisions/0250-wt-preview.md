---
description: ADR-0250. bin/wt-preview switches the one running worktree preview. It stops every other idle worktree stack with bin/wt-down --stop, keeps stacks an agent may be using, and resumes the target's stopped containers with the same DB before it falls back to bin/wt-up.
last_verified: 2026-11-13
---
> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0250: One command switches the running worktree preview

**Status:** Accepted
**Date:** 2026-11-13
**Deciders:** post-plan harness (auto-draft)

## Context

The owner looks at one worktree preview in the browser at a time. Before this PR, `bin/wt-new` told them to run `bin/wt-up <name>` for a preview. Each `bin/wt-up` call built a new stack with a fresh DB and left every earlier preview running. To switch, the owner had to stop the old stacks by hand with `bin/wt-down`, and then pay for a full rebuild of a stack whose stopped containers already held a usable DB. Agents also start worktree stacks through `bin/wt-stack`, and automouse queue entries and launchd post-plan jobs may be using a stack. A blind stop of every other stack would break those runs.

## Decision

`bin/wt-preview <worktree-name | PR number> [--fresh] [bin/wt-up args...]` switches the preview in two steps.

1. It walks the same `<worktrees parent>/*/.wt-slug` list that the stop-stacks step of `bin/wt-gc-tick` walks, so it never sees the main stack. For each other worktree whose PHP container is running, it calls `bin/wt-down <name> --stop`, which keeps the data. It skips `_pool-*` slots. It keeps a stack up when any of these hold:
   - the marker that `bin/wt-stack` writes under `$HOME/.cache/ibl5-wt-stack` matches the live PHP container ID;
   - an automouse queue entry is named after the worktree or its slug;
   - a `com.ibl5.*` launchd job label contains the worktree name or its slug.
2. It brings the target up. A running target stays as it is. A target with stopped containers gets `docker start` on every container in its compose project, so the DB from last time comes back. A target with no containers, or a call with `--fresh` or any `bin/wt-up` argument, goes to `bin/wt-up`.

A number such as `3890` or `#3890` resolves through `gh pr view` to the PR's head branch, and then through `git worktree list --porcelain` to the worktree checked out on it. When no worktree has that branch, the script exits 2 and points at `/pr-wt`. A failed stop of another stack prints a warning and does not block the switch. `bin/wt-new` now prints `bin/wt-preview <name>` as its preview hint.

`bin/test-wt-preview` runs 12 cases with `docker`, `launchctl`, `gh`, `git`, `bin/wt-up` and `bin/wt-down` stubbed, so it touches no real container. It is listed in `.github/workflows/harness-legs.txt`, so CI runs it.

## Alternatives Considered

- **A switch flag on wt-up.** Rejected because `bin/wt-up` builds a stack with a fresh DB. Teaching it to stop other stacks and resume stopped containers would mix three jobs into the script every agent and skill already calls to build a stack.
- **Stop all, no check.** Rejected because an agent run from `bin/wt-stack`, an automouse job, or a launchd post-plan run would lose its stack mid-step.
- **Always rebuild.** Rejected because a `bin/wt-up` rebuild costs minutes and drops the DB state the owner set up in the earlier preview. `docker start` on the stopped containers brings it back in about 10 seconds.
- **Several previews at once.** Rejected because the owner can only look at one, and each idle stack holds memory and CPU on the host.

## Consequences

- Positive: switching previews is one command, and the earlier preview keeps its data for the next switch back.
- Positive: stacks that an agent may be using stay up, so a switch does not break a running post-plan or automouse step.
- Negative: the in-use check matches names and slugs as substrings of launchd labels. A short worktree name can match an unrelated `com.ibl5.*` job and keep a stack up that could have stopped.
- Negative: the automouse queue path defaults to a path under the owner's home directory. Another machine has to set `WT_PREVIEW_QUEUE_DIR`.
- Negative: a resumed stack runs the DB and image from its last build. A schema change on the branch since then needs `--fresh`.

## References

- `bin/wt-preview`: the switch script.
- `bin/test-wt-preview`: stubbed behavior cases.
- `bin/wt-up`, `bin/wt-down`, `bin/wt-stack`, `bin/wt-gc-tick`: the stack tools this script calls or mirrors.
- `bin/wt-new`: prints the new preview hint.
- `bin/lib/git-helpers.sh`: `worktrees_parent_dir`.
- `.github/workflows/harness-legs.txt`: runs the test in CI.
- `ibl5/docs/DOCKER_SETUP.md`: section "Switching the preview you look at".
- `.claude/rules/worktree-hostname.md`: the `<slug>.localhost` URL the script prints.
