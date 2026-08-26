---
description: /pr-ready runtime Phase 4 — lost-work proof, push, PR state, and the CI watcher arm. Loaded by SKILL.md via git show at Phase 4.
last_verified: 2026-08-26
---

# /pr-ready runtime Phase 4 — push, PR state, and the CI watcher

Purpose: the full Phase 4 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase4-push-and-ci.md`.

`<MASTER_SHA>`, `<N>`, and `<HEAD_SHA>` below are **literals to substitute** with the values pinned in Phase 1.3 — a value captured in one Bash call does not survive into the next.

**Phase 4 — prove nothing was lost, push, watch CI.**

1. **Load the deferred watcher tool here, not in Phase 0:** `ToolSearch("select:TaskStop")`. Deferring keeps its schema out of context for the phases that never use it. `Monitor` is deliberately **not** loaded — step 5 explains why this skill never arms a CI watcher under it.

2. **Lost-work proof, two signals.** `git cherry -v origin/<branch> HEAD` is the weak signal: after a squash **every** replayed commit shows `+` by design, so `git cherry` alone cannot carry the proof. The authoritative check is content equivalence of the tree diff captured before and after the rebase (the Phase 2 include wrote `/tmp/pr-ready-diff-pre-<N>.patch`, keyed to the PR number — **never `$$`**, which differs between that call's shell and this one's).

   `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/lostwork.sh > /tmp/pr-ready-lostwork-<N>.sh && test -s /tmp/pr-ready-lostwork-<N>.sh && bash /tmp/pr-ready-lostwork-<N>.sh <N>`. The proof still fails closed — every path prints `TREE-EQUIVALENT` or `TREE DIVERGED — …`, and `test -s` stops a failed materialize reading as silence.

   `TREE DIVERGED` is expected **only** when Phase 3 resolved a real conflict; in that case name each diverging path in the Phase 6 verdict. Any other divergence — including a guard trip, which means the comparison never happened — stops the run.

3. **Push — one Bash call.** A bare `--force-with-lease` publishes nothing on a worktree branch with no upstream (measured 2026-08-24 against a local bare repo: `fatal: The current branch <b> has no upstream branch`, remote ref unchanged; the exit status is `push.default`-dependent, so never treat "no recognised error" as "pushed"). `scripts/push.sh` supplies the explicit refspec and the explicit lease itself, derives that lease from `refs/remotes/origin/<branch>` — what *we* last knew origin held, never a fresh `ls-remote`, which would already reflect a concurrent push and so degrade the lease into a plain `--force` — and then verifies against origin instead of trusting the exit status:

   ```bash
   git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/push.sh > /tmp/pr-ready-push-<N>.sh && test -s /tmp/pr-ready-push-<N>.sh && bash /tmp/pr-ready-push-<N>.sh <N>
   ```

   Verdict words. `PUSHED PR #<N> <sha>` (rc 0) — origin holds this HEAD; proceed. `STALE LEASE` (rc 2) — origin holds something this worktree has never seen (someone pushed into the branch, or it was never fetched here); git rejected with `stale info`, or the script refused for want of a lease, and **nothing was clobbered**. Stop and re-run Phase 3; never drop the lease, never retry with `--force`. `PUSH FAILED` (rc 1) — origin does **not** hold this HEAD. Print it and stop; do not continue to Phase 5. **"No error printed" is not evidence of a push.** Any `STOP:` line — stop the run and make no further tool call.

   **Do not use `git ls-remote origin HEAD`** — that returns origin's default-branch (master) tip, not this branch's, and the lease would never match. The script reads `refs/heads/<branch>` fully qualified for exactly that reason.

4. **`mergeable=UNKNOWN` handling — bounded, and never with a foreground `sleep`.** GitHub computes mergeability asynchronously, so the first read right after a push is usually `UNKNOWN`. Read once:

   ```bash
   gh pr view <N> --json mergeable,mergeStateStatus,state
   ```

   If the answer is anything other than `UNKNOWN`, act on it now — in particular `mergeStateStatus=DIRTY` means the rebase did not actually clear the conflict, so stop and re-run Phases 2–3 rather than pushing on. If it **is** `UNKNOWN`, **do not wait here**: proceed to step 5, whose watcher polls `mergeStateStatus` on every iteration and breaks immediately on `DIRTY`. That is the resolution path — the watcher is the wait, and a conflict surfaces on its first poll rather than after CI finishes. Do not re-read here, and do not loop.

   **Never insert a foreground `sleep` to bridge the gap.** The harness refuses one (`Blocked: sleep 30 … To wait for a condition, use Monitor with an until-loop … Do not chain shorter sleeps to work around this block`), so a `sleep`-based wait does not merely cost time — it hard-fails the call and stalls the run. That message's suggested `Monitor` is **not** the remedy here; step 5's background `Bash` watcher is, for the reason step 5 gives.

5. **CI watcher — exactly one, keyed to the head SHA, and it wakes you exactly once.** If a watcher from an earlier iteration is live, kill it first with `TaskStop(task_id: "<the id recorded when it was armed>")`. **Record the id the background `Bash` call returns in the run notes at arm time** — `TaskStop` has no "stop all" form, so an unrecorded id is an orphaned watcher. **Never** poll with `sleep N; gh pr checks` on the main thread: that re-reads the full orchestrator context on every call, the spend bug `.claude/rules/work-triage.md` names.

   **Never arm this under `Monitor`, and never inline the loop.** `Monitor`'s contract is *every stdout line is a conversation message*; this repo's PRs carry ~46 checks that land in waves across a ~10-minute run, so a per-check emitter woke the orchestrator a dozen-odd times and re-sent the whole `/pr-ready` context on each wake — the watching cost more than the work. `scripts/watch-ci.sh` polls in **silence** and prints one verdict block on exit, so `Bash` with `run_in_background: true` wakes you exactly **once**. Do not "improve" it back into a progress feed.

   First, run `git rev-parse HEAD` bare and record the printed SHA as `<HEAD_SHA>` in the run notes (single-value capture — it does not survive to the next Bash call). Then materialize the watcher in the foreground, so a failed materialize is loud rather than an exit code on a background task. The filename is PR-keyed, not `$`-keyed — the same reason every other `/tmp` path in this skill uses `<N>`:

   ```bash
   git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/watch-ci.sh > /tmp/pr-ready-ciwatch-<N>.sh && test -s /tmp/pr-ready-ciwatch-<N>.sh && echo MATERIALIZED
   ```

   Then arm it with **one** `Bash` call, `run_in_background: true`, and **no `timeout`** — `Bash`'s foreground `timeout` caps at 600000ms (10 min), under a normal CI run, while `run_in_background` has no cap and the script bounds itself at 3600s:

   ```bash
   bash /tmp/pr-ready-ciwatch-<N>.sh <N> <HEAD_SHA>
   ```

   The verdict block's first word is one of six, each with its own exit code: `CI COMPLETE` (0), `CI FAILED` (1 — one indented line per check that did not end `pass` or `skipping`), `MERGE CONFLICT` (2 — `mergeStateStatus=DIRTY`, so stop and re-run Phases 2–3), `STALE` (3 — the head moved, so this watcher is obsolete), `CI TIMEOUT` (4), `STOP` (5 — a usage error). **Silence is not success**, so no path exits quietly and no verdict is a success-only filter: naming *every* red check is what makes one wake enough to drive Phase 6.5 remediation instead of one round trip per failure. Every verdict also carries the last `mergeStateStatus` the script read, which is what resolves step 4's `UNKNOWN` without a second main-thread read.

   The script's own header carries the rest — why it does not fail fast, and why the `seen` grace gate (the false `CI COMPLETE` on the pre-push head, observed on PR #1830) must not be removed. **The script is the only copy of that logic**; do not paste a reference loop back into this file, or the two drift and the reader follows the stale one.

