---
description: /pr-ready runtime Phase 4 — lost-work proof, push, PR state, and the CI watcher arm. Loaded by SKILL.md via git show at Phase 4.
last_verified: 2026-09-04
---

# /pr-ready runtime Phase 4 — push, PR state, and the CI watcher

Purpose: the full Phase 4 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase4-push-and-ci.md`.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in Phase 1.3 — a value captured in one Bash call does not survive into the next. `<HEAD_SHA>` is **not** a Phase 1.3 pin: it is captured fresh at the step that names it, and re-captured after every push. Never carry one in from an earlier phase.

**Phase 4 — prove nothing was lost, push, watch CI.**

1. **No deferred watcher tool is loaded here.** Neither `Monitor` nor `TaskStop` is needed: step 5 runs the CI wait inside an `Agent` delegate, which has already finished by the time you are re-invoked, so there is never a live watcher to stop and never a progress stream to consume. Keeping both schemas out of context is free. Step 5 explains why this skill never arms a CI watcher under `Monitor`.

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

   **Never insert a foreground `sleep` to bridge the gap.** The harness refuses one (`Blocked: sleep 30 … To wait for a condition, use Monitor with an until-loop … Do not chain shorter sleeps to work around this block`), so a `sleep`-based wait does not merely cost time — it hard-fails the call and stalls the run. That message's suggested `Monitor` is **not** the remedy here; step 5's `Agent`-delegate watcher is, for the reason step 5 gives.

5. **CI watcher — run it inside an `Agent` delegate. Never background a `Bash` call.** This is the only shape that survives a headless run, and it still wakes you exactly once.

   **Why the background `Bash` shape is banned.** `bin/pr-ready-now` fires this skill as `claude -p` with `CLAUDE_HEADLESS=1`. Print mode's wind-down sweep counts only `local_agent` and `local_workflow` tasks as awaitable — a backgrounded `Bash` shell is `local_bash` and is **never** waited for, so the moment you stop making foreground calls the harness kills it after a 5s grace and exits. `~/.claude/hooks/bash-guard.sh` Check 17 therefore **denies** `run_in_background: true` outright whenever `CLAUDE_HEADLESS` is set. The foreground fallback is no better on its own: `Bash`'s `timeout` caps at 600000ms (10 min) while this repo's CI runs ~10–15 min, so a single foreground arm reliably times out into an auto-backgrounded `local_bash` task — and the *"You will be notified when it completes"* message that timeout prints is **false** in print mode. PR #1825 (2026-08-29) believed it, said it would wait, and was killed mid-Phase-4: all 46 checks green, no verdict comment, exit status 0, log 117 bytes. An `Agent` delegate is `local_agent`, which **is** awaited across turns, so ending your turn to wait on one is correct — Check 17's own closing note says so.

   **Never arm this under `Monitor`, and never inline the loop.** `Monitor`'s contract is *every stdout line is a conversation message*; this repo's PRs carry ~46 checks that land in waves across a ~10-minute run, so a per-check emitter woke the orchestrator a dozen-odd times and re-sent the whole `/pr-ready` context on each wake — the watching cost more than the work. `scripts/watch-ci.sh` polls in **silence** and prints one verdict block on exit, and the delegate below returns exactly that block, so you are woken once. Do not "improve" it back into a progress feed. **Never** poll with `sleep N; gh pr checks` on the main thread either: that re-reads the full orchestrator context on every call, the spend bug `.claude/rules/work-triage.md` names.

   First, run `git rev-parse HEAD` bare and record the printed SHA as `<HEAD_SHA>` in the run notes (single-value capture — it does not survive to the next Bash call). Then materialize the watcher in the foreground, so a failed materialize is loud rather than an exit code on a background task. The filename is PR-keyed, not `$`-keyed — the same reason every other `/tmp` path in this skill uses `<N>`:

   ```bash
   git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/watch-ci.sh > /tmp/pr-ready-ciwatch-<N>.sh.part && test -s /tmp/pr-ready-ciwatch-<N>.sh.part && mv /tmp/pr-ready-ciwatch-<N>.sh.part /tmp/pr-ready-ciwatch-<N>.sh && echo MATERIALIZED
   ```

   **The `.part` staging is load-bearing, and `MATERIALIZED` is a required output.** `>` truncates its target *before* `git show` runs, so materializing straight onto the real path leaves a **0-byte** `/tmp/pr-ready-ciwatch-<N>.sh` behind whenever the `git show` fails. `test -s` correctly stops this `&&` chain — but the empty file survives in `/tmp`, and `bash` on an empty file exits **0** printing nothing. The delegate packet below reads a no-output run as a killed window and re-runs it, so an empty watcher burns all 6 windows in seconds, returns no verdict, and the orchestrator idles until `timeout 5400` kills it with no verdict comment posted (PR #2077, 2026-09-04). Staging through `.part` means a failed `git show` leaves the real path exactly as it was — absent, or the previous good copy — and never a silent 0-byte trap. If `MATERIALIZED` does not print, **stop**: do not spawn the delegate; report the failed materialize in the Phase 7 verdict.

   Then spawn **one** delegate and end your turn. Haiku tier: it runs a command and repeats its output, with no relevance judgment to make (`.claude/rules/agent-tiering.md`). Pass `model: "haiku"` and omit `subagent_type`:

   ```
   Agent(description: "Watch CI for PR <N>", model: "haiku", prompt: <the packet below>)
   ```

   Delegate packet — substitute `<N>` and `<HEAD_SHA>`, change nothing else:

   > Run this command in the FOREGROUND with `timeout: 600000`. Never pass `run_in_background` — it is denied here and its results are discarded.
   >
   > ```bash
   > if test -s /tmp/pr-ready-ciwatch-<N>.sh; then bash /tmp/pr-ready-ciwatch-<N>.sh <N> <HEAD_SHA> 540; else echo "STOP: watcher /tmp/pr-ready-ciwatch-<N>.sh is missing or empty"; fi
   > ```
   >
   > It polls in silence for up to 540s, then prints one verdict block whose first word is `CI COMPLETE`, `CI FAILED`, `MERGE CONFLICT`, `STALE`, `CI TIMEOUT`, or `STOP`.
   >
   > `CI TIMEOUT` here means only that the 540s window elapsed with checks still pending — it is **not** a verdict. Re-run the identical command, unchanged, up to **6 windows total** (~54 min of watching). The script is safe to re-run: it re-reads live state every time. Any other first word is terminal — stop immediately and do not re-run.
   >
   > If the command returns **no verdict block at all** — the tool killed it, the output is empty, or it says the command was moved to the background — that counts as one window and you re-run it, unchanged. Never treat a missing verdict as a result, and never wait to "be notified": in this run nothing will notify you.
   >
   > Your final message must be the terminal verdict block **verbatim** and nothing else: every line of it, including each indented failing-check line. If all 6 windows returned `CI TIMEOUT`, report the last one verbatim. Never summarise it, never re-order it, never add commentary. Do not edit any file, do not push, do not comment on the PR.

   The 540s window is deliberately under the 600000ms `Bash` cap, so the delegate never meets the auto-background message at all. Read its report exactly as if the script had printed it here.

   The verdict block's first word is one of six, each with its own exit code: `CI COMPLETE` (0), `CI FAILED` (1 — one indented line per check that did not end `pass` or `skipping`), `MERGE CONFLICT` (2 — `mergeStateStatus=DIRTY`, so stop and re-run Phases 2–3), `STALE` (3 — the head moved, so this watcher is obsolete), `CI TIMEOUT` (4 — only after all 6 windows), `STOP` (5 — a usage error). **Silence is not success**, so no path exits quietly and no verdict is a success-only filter: naming *every* red check is what makes one wake enough to drive Phase 6.5 remediation instead of one round trip per failure. Every verdict also carries the last `mergeStateStatus` the script read, which is what resolves step 4's `UNKNOWN` without a second main-thread read.

   The script's own header carries the rest — why it does not fail fast, and why the `seen` grace gate (the false `CI COMPLETE` on the pre-push head, observed on PR #1830) must not be removed. **The script is the only copy of that logic**; do not paste a reference loop back into this file, or the two drift and the reader follows the stale one.
