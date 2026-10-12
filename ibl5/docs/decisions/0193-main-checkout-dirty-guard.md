---
description: Alerts once per new dirty state of the main checkout from the wt-sync tick and captures a forensic snapshot, because the writer behind the 2026-10-05 drift left too little evidence to name.
last_verified: 2026-10-10
---

# ADR-0193: Alert on a dirty main checkout and capture forensics before fixing a writer

**Status:** Accepted
**Date:** 2026-10-10
**Deciders:** repo tooling maintainers

## Context

The main checkout at `/Users/ajaynicolas/GitHub/IBL5` is read-only by rule (ADR-0062, `.claude/rules/workflow-continuity.md`). On 2026-10-05 it drifted twice and nobody noticed for a day. At 09:18:59 its HEAD moved to a detached `origin/master` (reflog: `checkout: moving from master to origin/master`). At 13:32:05 `.git/index` was rewritten so that 17 files were staged while the working files matched HEAD. No reflog entry in the main checkout or in any worktree moved a ref in that window, so the write came from an index-only command (`read-tree`, `update-index`, `add`, a mixed `reset`, `checkout <ref> -- <paths>`) or from a leaked `GIT_DIR`/`GIT_INDEX_FILE`. The user found the state on 2026-10-06 through a Stop-hook warning and cleaned it, which removed what evidence remained.

The writers known to run in the main checkout were each checked and ruled out. `bin/pr-cycle` only fetches and updates `refs/pr-cycle/*`. The post-merge hook installed by `bin/install-git-hooks` writes logs and a gitignored stylesheet. `bin/wt-sync-tick` skips the main checkout in its loop. `bin/automouse/run` and `bin/automouse/prompt-impl` fast-forward master, which can neither detach HEAD nor stage files. No code under `bin/` checks out `origin/master`, runs `read-tree` on the main index, or sets `GIT_DIR`/`GIT_INDEX_FILE` for it. The writer cannot be named from what survives.

The only existing signal is the Stop hook `~/.claude/hooks/auto-commit-reminder.sh` (example). It fires at turn end inside a Claude session and sends no DM, so drift caused while no session is open stays invisible.

## Decision

1. `bin/main-checkout-guard` alerts when the main checkout's `git status --porcelain` is non-empty (untracked files count, gitignored files do not) or its HEAD is anything other than branch `master`. It is read-only on git: every call runs with `GIT_OPTIONAL_LOCKS=0`, so it never refreshes the index it inspects.
2. It sends one Discord DM per distinct state through `bin/discord-dm`. The state key is a git hash of the branch state plus the sorted porcelain, kept in a state file under the per-project Claude directory outside the repo. A repeated key is silent. A clean tree on `master` removes the state file, so a later re-dirty alerts again. The state is written only after a successful send, so a failed send retries on the next tick. A tick that finds `index.lock` is skipped, so an in-flight git operation is never reported.
3. On each new state it writes a snapshot next to the state file and names its path in the DM. The snapshot holds the index mtime, HEAD, the main reflog tail, every worktree's reflog entries within 300 seconds of the index mtime, in-progress operation markers, the stash list, the staged diff stat, running git, claude, and python processes, and the loaded `com.ibl5.*` launchd jobs.
4. `bin/wt-sync-tick` calls the guard on every 15-minute fleet tick, after its kill switch and before its HID-idle presence gate, and logs the guard's exit code without acting on it.
5. No writer changes in this PR. When a snapshot names the writer, a follow-up PR moves that writer into a worktree, a temp dir, or a gitignored path.

### Extend before add

`.claude/rules/meta-tooling-bar.md` asks for an existing host first. Two were weighed.

- `bin/launchd-health-check` already DMs, but it runs once a day and dedupes with per-day stamps. It would repeat the same alert every day and could wait up to a day to send the first one. Changing its dedupe to once-per-state would change the contract of every check it already runs.
- `bin/wt-sync-tick` has the right schedule. Its job is syncing worktrees, and its loop skips the main checkout on purpose. Inlining detection, dedupe, and forensic capture would add a second responsibility to a script whose harness already holds 52 cases. A separate script called from one block in the tick reuses the schedule and keeps both scripts single-purpose.

A rule or doc note cannot raise an alert, and the 10-05 drift went unseen because nobody was looking. The new upkeep is one script, one harness wired into CI, and one call site.

## Consequences

- Drift in the main checkout reaches the operator within 15 minutes, with a snapshot taken at the first tick that sees it.
- Deliberate work in the main checkout, which the rules already forbid, now produces a DM.
- Snapshots accumulate in the state dir at one small file per distinct state; nothing prunes them yet.
- An operator-present tick now runs one `git status` on the main checkout every 15 minutes.

## Alternatives considered

- **Fix a guessed writer now** (for example the `pull origin master` step in `bin/automouse/prompt-impl`). Rejected: no evidence ties it to the drift, and a wrong fix would end the search.
- **Auto-heal the main checkout.** Rejected: it destroys the evidence and may discard real work.
- **A new launchd job.** Rejected: the wt-sync job already fires every 900 seconds.
- **A git hook.** Rejected: no hook fires on `read-tree` or `update-index`, the shape of the 13:32 write.
