# Phase 7 — CI Monitoring (post-plan reference)

Purpose: the Opus-escalation procedure for Phase 7 CI fixes.

   **Escalate to Opus when out of depth.** Failures in the Opus row of agent-tiering — failing-check `name` matching `mutation|MSI|engine|golden|migration` (case-insensitive), or any FK-ordering / cross-track failure you can't localize from the log in one read. Triggers: category match → Opus on attempt 1; otherwise Sonnet does attempts 1–2, Opus takes attempt 3 instead of giving up.

   Capture context to temp files and pass the **paths** — the agent `Read`s them; never summarize the log/diff into the prompt (summarizing → garbage fix, per the Phase 4 review-agent rule):
   ```bash
   gh run view <id> --log-failed > /tmp/post-plan-ci-fail-$PPID.log
   git -C <worktree> diff origin/master...HEAD > /tmp/post-plan-diff-$PPID.patch
   ```
   Spawn **one** `Agent(model: "opus")` with: the two paths, PR number, worktree path, plan path, failing check names, what Sonnet tried. It fixes, runs the relevant track locally if it can, **commits and pushes itself**, returns a one-line summary. Don't forward CLAUDE.md (auto-loaded). Loop back to step 1.

   The Opus attempt **counts toward** the 3-iteration ceiling; after 3 total, report surviving failures in a PR comment and continue to Phase 8. Keep it inside the Phase 7 budget — one bounded Opus attempt fits.

---

## BEHIND re-rebase loop

Entered **only** from Phase 7 step 3.5, and only when the strict probe returned `true` **and**
`mergeStateStatus` was `BEHIND`. On any other probe result this section is never read.

`<N>`, `<KEY>` and `<MASTER_SHA>` are **literals the orchestrator substitutes** — the PR number,
the `key=` value printed by the Phase 2 pre-rebase capture block, and the SHA re-pinned in
step 3 of each iteration. Nothing survives between Bash blocks except exported env vars, which
is also why the prior arm state below is written to a **file**, never a shell variable.

**Bound: 3 iterations.** Iteration 4 does not run. This is the same ceiling the step-4 CI fix
loop already uses — Phase 7 has one bound, not two.

**Headless safety:** every step is a foreground Bash call. No `run_in_background`, no spawned
agent, no `--watch` inside the loop — a headless `claude -p` run has no background-completion
re-invocation, so a turn that ends with work still running is a stall-kill.

### Each iteration, in this order

1. **Record prior arm state**, before anything is disarmed:

   ```bash
   gh pr view <N> --json autoMergeRequest --jq 'if .autoMergeRequest then "armed" else "unarmed" end' > /tmp/postplan-automerge-was-<KEY>.txt
   cat /tmp/postplan-automerge-was-<KEY>.txt
   ```

   Without this file the run cannot tell, after the force-push, whether re-arming restores prior
   state or newly arms a PR that Phase 6.5 deliberately held.

2. **Disarm, before any history rewrite.** If step 1 printed `armed`:

   ```bash
   gh pr merge <N> --disable-auto
   ```

   Rewriting the branch under an armed auto-merge is the one sequence that could merge a tree
   nobody watched land. `--disable-auto` always precedes the rebase in this iteration; never
   reorder them.

3. **Re-pin master.** The Phase 2 pin is stale — master moving is why we are here, and rebasing
   onto the old pin would leave the PR `BEHIND` and spin the loop to its bound for nothing.

   ```bash
   git fetch origin master --quiet
   git rev-parse origin/master
   ```

   Record the printed SHA as the run-note literal `<MASTER_SHA>` for this iteration.

4. **Re-capture the pre-rebase diff.** Re-run the `# phase 2 pre-rebase capture` block from
   `.claude/skills/post-plan/SKILL.md` verbatim, including its literal `origin/master...HEAD`
   base. The proof compares *this* rebase's before and after; a stale pre-patch from Phase 2
   would compare across two rebases and report `TREE DIVERGED` on a perfectly good run.

5. **Re-run the `# phase 2 rebase` block** from `.claude/skills/post-plan/SKILL.md`, unmodified.
   All four arms behave exactly as they do at Phase 2.

6. **Branch on the result.**
   - `REBASE=clean` / `REBASE=rebased` → force-push, then re-probe:

     ```bash
     git push --force-with-lease origin HEAD:<BRANCH>
     ```

     `<BRANCH>` is the branch name literal; worktrees have no upstream, so a bare
     `--force-with-lease` silently no-ops there — always name the remote and the ref.
   - `REBASE=indeterminate` → **halt** with a `STOP:` line, fail-closed, exactly as at Phase 2.
     Do not force-push, do not re-arm.
   - `REBASE=conflict` → go to § Disarm-on-conflict below.

7. **Re-probe.** `gh pr view <N> --json mergeStateStatus --jq .mergeStateStatus`. Not `BEHIND`
   ⇒ leave the loop. Still `BEHIND` ⇒ next iteration, up to the bound.

### On loop exit without a conflict

Re-arm **only if both** hold: `/tmp/postplan-automerge-was-<KEY>.txt` reads `armed`, **and** the
conflict flag `/tmp/postplan-conflict-resolved-<KEY>` is still absent.

```bash
test "$(cat /tmp/postplan-automerge-was-<KEY>.txt)" = armed \
  && ! test -e /tmp/postplan-conflict-resolved-<KEY> \
  && gh pr merge <N> --squash --auto
```

Never add `--delete-branch`. A PR that was `unarmed` before the loop stays unarmed.

### On iteration 4 — halt

```
STOP: BEHIND re-rebase loop hit its 3-iteration ceiling. master is moving faster than this run can rebase onto it. Nothing is broken — the PR is correct and CI passed; it is simply behind a master that keeps advancing. Auto-merge has been left disarmed. Rebase and merge by hand, or re-run /post-plan when master is quieter. Loop bound lives in .claude/skills/post-plan/_phase-7-ci-monitoring.md.
```

The ceiling is a terminal state, not a retry hint: three consecutive losses to a moving master
means the contention is structural, and an unbounded loop in a headless run is how a
`MAX_PP_SECS` timeout becomes an abandoned half-pushed branch. Leaving auto-merge **disarmed**
at the ceiling is deliberate — the last thing the loop did was rewrite the branch, and arming a
rewritten branch it then walked away from is exactly the unattended-merge risk the disarm exists
to prevent.

### Disarm-on-conflict

An iteration whose rebase printed `REBASE=conflict` has already had its arm write
`/tmp/postplan-conflict-resolved-<KEY>` (the Phase 2 block does this at detection time). Then:

1. Enter `.claude/skills/post-plan/_phase-2-conflict-resolution.md` exactly as at Phase 2 — same
   `--onto` recipe, same three-way resolution, same `lostwork.sh` gate. `TREE-EQUIVALENT` remains
   the precondition for the force-push.
2. Auto-merge **stays disarmed**. It was disarmed in step 2 of this iteration, and the re-arm
   above is gated on the conflict flag being absent, so it cannot come back. Condition (14)
   cannot help here — Phase 6.5 already ran. This is the second entry point into the hold, and
   it is enforced by the re-arm gate rather than by a condition evaluation.
3. Post the sticky comment through the **same** `<!-- post-plan-conflict-hold -->` marker
   (Phase 6.5 step 0), adding one line naming the CI-watch re-rebase as the trigger.
   `post_sticky()` updates in place, so a PR that conflicted at both Phase 2 and Phase 7 ends
   with one comment, not two.
4. Leave the loop and continue to Phase 8. The PR is correct and held — that is the intended
   terminal state.

### What is deliberately not re-run

A conflict-free re-rebase that proves `TREE-EQUIVALENT` leaves the branch's contributed content
byte-for-byte what Phase 4 reviewed and Phase 5.5 judged; only the base commit moved. **Nothing
is re-run** — no re-review, no re-fidelity, no re-conformance. A re-rebase that *conflicts*
invalidates both verdicts, and the loop does not try to re-run them either; it leaves auto-merge
disarmed, posts the hold, and routes the PR to a human. The held sticky comment carries the
"this needs re-review" signal.
