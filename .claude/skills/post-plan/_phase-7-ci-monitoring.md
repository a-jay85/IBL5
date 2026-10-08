---
description: /post-plan Phase 7: the all-Opus CI fix procedure, the BEHIND re-rebase loop, and the harness background-CI-outcome short-circuit.
last_verified: 2026-10-07
---

# Phase 7 — CI Monitoring (post-plan reference)

This file holds the Phase 7 CI fix procedure. Every attempt runs on Opus 5.5.

**Outcome already known.** If `$HARNESS_RUN_DIR/ci-<head-sha>.json` exists for the current head
(SKILL.md Phase 7.0), its `failed_checks` and `evidence` replace the first `gh pr checks --watch`
call. Start the fix procedure below from its failure list instead of re-measuring. The fix
procedure itself, the BEHIND re-rebase loop, and the MERGED early exit are unchanged and still
apply to every re-watch after a fix commit.

Headless safety is preserved: reading the file is a foreground `cat`, not a background job.

   **All attempts run on Opus 5.5.** Make up to 3 attempts, each a fresh Opus agent. The failing check's name does not change the model.

   Capture context to temp files and pass the **paths** — the agent `Read`s them; never summarize the log/diff into the prompt (summarizing → garbage fix, per the Phase 4 review-agent rule):
   ```bash
   gh run view <id> --log-failed > /tmp/post-plan-ci-fail-$PPID.log
   git -C <worktree> diff origin/master...HEAD > /tmp/post-plan-diff-$PPID.patch
   ```
   Spawn **one** `Agent(model: "opus")` with: the two paths, PR number, worktree path, plan path, failing check names, what earlier attempts tried. Tell it master is green, so this diff caused the failure, and that it must not edit a test's expected value or hardcoded count to match new output unless the PR meant to change that count. It fixes, runs the relevant track locally if it can, **commits and pushes itself**, returns a one-line summary. Don't forward CLAUDE.md (auto-loaded). Loop back to step 1.

   Every attempt counts toward the 3-iteration ceiling. When an attempt makes no code change, run `gh run rerun <run-id> --failed` once and re-watch. A green re-run means a flaky check; say so in a PR comment and go to Phase 8. The re-run does not count as an attempt. After 3 attempts, report the surviving failures in a PR comment and continue to Phase 8. Stay inside the Phase 7 budget.

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

1. **Record prior arm state**, before anything is disarmed. Write once — do not overwrite on
   subsequent iterations (the disarm in step 2 changes what GitHub reports, so a second write
   would record the post-disarm state, and the loop-exit re-arm gate would never fire):

   ```bash
   test -e /tmp/postplan-automerge-was-<KEY>.txt || \
     gh pr view <N> --json autoMergeRequest --jq 'if .autoMergeRequest then "armed" else "unarmed" end' > /tmp/postplan-automerge-was-<KEY>.txt
   cat /tmp/postplan-automerge-was-<KEY>.txt
   ```

   Without this file the run cannot tell, after the push, whether re-arming restores prior
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

5. **Re-run the `# phase 2 merge` block** from `.claude/skills/post-plan/SKILL.md`, unmodified.
   Every arm behaves exactly as it does at Phase 2.

6. **Branch on the result.**
   - `REBASE=clean` / `REBASE=merged` → push, then re-probe:

     ```bash
     git push origin HEAD:<BRANCH>
     ```

     `<BRANCH>` is the branch name literal. Worktrees have no upstream, so always name the
     remote and the ref. A merge only adds commits on top of the pushed head, so this push is a
     fast-forward and needs no force flag.
   - `REBASE=indeterminate` → **halt** with a `STOP:` line, fail-closed, exactly as at Phase 2.
     Do not force-push, do not re-arm.
   - `REBASE=conflict` → go to § Disarm-on-conflict below.

7. **Re-probe.** `gh pr view <N> --json mergeStateStatus --jq .mergeStateStatus`. Not `BEHIND`
   ⇒ leave the loop. Still `BEHIND` ⇒ next iteration, up to the bound.

### On loop exit

Re-arm only if the prior arm state reads `armed`, **and** either the conflict flag is absent or step 7.5's verdict for `<POST_RESOLUTION_SHA>` reads clean.

```bash
# phase 7 re-arm gate: restore the pre-loop arm state, and only when this run had no
# conflict or step 7.5 cleared the resolution for <POST_RESOLUTION_SHA>.
CONFLICT_OK=no
if [ -r /tmp/postplan-conflict-verdict-<KEY>-<POST_RESOLUTION_SHA>.ok ]; then
  read -r VL < /tmp/postplan-conflict-verdict-<KEY>-<POST_RESOLUTION_SHA>.ok || VL=""
  [ "$VL" = "CONFLICT-REVIEW=CLEAN" ] && CONFLICT_OK=yes
fi
test "$(cat /tmp/postplan-automerge-was-<KEY>.txt)" = armed \
  && { ! test -e /tmp/postplan-conflict-resolved-<KEY> || [ "$CONFLICT_OK" = yes ]; } \
  && gh pr merge <N> --squash --auto
```

Never add `--delete-branch`. A PR that was `unarmed` before the loop stays unarmed.

### At the ceiling (after iteration 3)

Iteration 3 exits through `On loop exit` like every other exit, so the re-arm gate above has already run: the PR is armed again when its prior state read `armed` and the conflict flag is absent or cleared, and it stays unarmed otherwise. Then print this NOTE and continue to Phase 8:

```
NOTE: BEHIND re-rebase loop hit its 3-iteration ceiling. master is moving faster than this run can rebase onto it. Nothing is broken: the PR is correct and CI passed on the rewritten branch. The re-arm gate has already run, so auto-merge is back in its pre-loop state. An armed PR is carried to merge by .github/workflows/update-behind-prs.yml (ADR-0081), which refreshes the head of the merge line on every push to master. Loop bound lives in .claude/skills/post-plan/_phase-7-ci-monitoring.md.
```

The ceiling is a terminal state for this run. Three consecutive losses to a moving master mean the contention is structural, and an unbounded loop in a headless run is how a `MAX_PP_SECS` timeout becomes an abandoned half-pushed branch. The run stops rebasing; it does not disarm. Before `.github/workflows/update-behind-prs.yml` existed an armed PR left BEHIND waited forever, so the ceiling disarmed to make a human look. Today the merge line owns that wait (ADR-0081, head-of-line addendum), and a disarmed PR is the one that sits open until a human re-arms it by hand, which is what happened to two READY PRs on 2026-10-04. The per-iteration disarm before each rewrite (step 2) is unchanged: a branch is never rewritten while armed.

### Disarm-on-conflict

An iteration whose rebase printed `REBASE=conflict` has already had its arm write
`/tmp/postplan-conflict-resolved-<KEY>` (the Phase 2 block does this at detection time). Then:

1. Enter `.claude/skills/post-plan/_phase-2-conflict-resolution.md` exactly as at Phase 2, now
   including step 7's `POST_RESOLUTION_SHA` capture and step 7.5's review. Record the sha step 7
   printed as this iteration's `<POST_RESOLUTION_SHA>` literal before running the step 6.1
   re-arm block. The pinned-SHA merge, three-way resolution, and `lostwork.sh` gate are unchanged.
   `TREE-EQUIVALENT` remains the precondition for the push.
2. Auto-merge stays disarmed unless step 7.5 cleared the resolution for `<POST_RESOLUTION_SHA>`
   and step 1 of this iteration recorded `armed`. Condition (14) cannot help here; Phase 6.5
   already ran. This is the second entry point into the hold, and it is enforced by the step 6.1
   re-arm gate rather than by a condition evaluation.
3. Post the sticky comment through the **same** `<!-- post-plan-conflict-hold -->` marker
   (Phase 6.5 step 0), adding one line naming the CI-watch re-rebase as the trigger.
   `post_sticky()` updates in place, so a PR that conflicted at both Phase 2 and Phase 7 ends
   with one comment, not two.
   The branch the sticky renders follows the rule in the conflict-resolution appendix.
4. Leave the loop and continue to Phase 8. The PR is correct and held — that is the intended
   terminal state.

### What is deliberately not re-run

A conflict-free re-rebase that proves `TREE-EQUIVALENT` leaves the branch's contributed content
byte-for-byte what Phase 4 reviewed and Phase 5.5 judged; only the base commit moved. **Nothing
is re-run** — no re-review, no re-fidelity, no re-conformance. A re-rebase that *conflicts*
invalidates both verdicts, and the loop does not try to re-run them either; it leaves auto-merge
disarmed, posts the hold, and routes the PR to a human. The held sticky comment carries the
"this needs re-review" signal.

A conflict-free re-rebase that runs after a Phase 2 conflict already cleared clean pushes
a new HEAD. The step 6.1 re-arm gate fires: the verdict file is keyed to the recorded
`<POST_RESOLUTION_SHA>` literal, so it still exists and reads CLEAN after the subsequent
conflict-free re-rebase. That is the same `TREE-EQUIVALENT` outcome described above: the
branch's contributed content is unchanged and only the base moved. Re-arming on that tree is
consistent with the no-re-run policy above.
