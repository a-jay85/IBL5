---
name: post-plan
description: Fallback for bin/post-plan-now when the compiled harness crashes. Runs only what the harness lacks: crash triage (report and hold, never arm), backlog housekeeping, the E2E verify track, Phase 10 preview, and background cleanup.
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
  - Skill
last_verified: 2026-10-23
---

# Post-Plan Fallback

This skill is the fallback engine for `bin/post-plan-now`. The compiled harness in `tools/postplan-harness` owns the real pipeline: commit, push and PR, review, security audit, conformance, fidelity, manual-testing triage, CI watch, retrospective, and auto-merge arming. This file carries none of that.

This skill never runs `gh pr merge`, never edits auto-merge, and never pushes a new branch or opens a PR. Arming is decided only by `tools/postplan-harness/harness/armable.py`.

Execute all phases below sequentially in a single response. Do NOT stop, ask for input, or return control between phases.

**No background-completion re-invocation exists here.** Post-plan runs headless (`claude -p` under the automouse runner), NOT in the interactive harness. When a `run_in_background` task finishes there is **nothing that re-invokes you** — emitting `end_turn` ends the run for good. So **"Waiting for PHPUnit/PHPStan/E2E to complete" is NEVER a valid final message or a stopping point.** If you launch any background work, you MUST drain it **within the same turn** — poll `BashOutput` until every background shell reports a terminal result — before you compute that phase's status and move on. Ending the turn with a background task still alive is the exact failure that stall-kills the run and (after 3 burns) poison-pills the plan.

Phase 11 (Background Process Cleanup) is mandatory and must always be the last thing you execute before ending your turn.

## Incremental Checkpoints

After any phase that modifies files, commit and push before moving to the next phase, unless that phase's own push rule says to hold. Never carry uncommitted work across phase boundaries. If the session dies mid-skill (usage limit, crash), the latest checkpoint is preserved on the branch and a fresh session can resume.

```bash
cd <worktree>
git add -A
if ! git diff --cached --quiet; then
    git commit -m "<scope>: <what this phase changed>"
    git push origin HEAD
fi
```

Phase 5 is the only phase here that may modify files. Its E2E fix is committed and pushed per the block above only when Phase 2 reported `state=OPEN` and `armed=no`. Any other value holds the fix uncommitted.

---

## Phase 0: Worktree guard

The bash block below stops the model as well as the shell. On `MAIN-CHECKOUT` or `WRONG-WORKTREE` the run ends immediately at Phase 0 and does **not** continue to Phase 1. `exit 1` ends the bash block. It does not end the model's turn by itself; the instruction to end the run is explicit in the printed `STOP:` message. **Phase 11 does not apply to a Phase-0 guard stop**, because no phase has run and no background process has been launched.

```bash
# --- post-plan worktree guard (Phase 0) ---
TOP=$(git rev-parse --show-toplevel 2>/dev/null) || { echo "STOP: not inside a git repository."; exit 1; }
[ -r "$TOP/bin/lib/git-helpers.sh" ] || { echo "STOP: $TOP/bin/lib/git-helpers.sh is missing — cannot resolve is_in_worktree."; exit 1; }
. "$TOP/bin/lib/git-helpers.sh"
HERE=$(git rev-parse --abbrev-ref HEAD)
case "$HERE" in
  master|main|HEAD) echo "STOP: MAIN-CHECKOUT or detached HEAD — branch '$HERE' is not a feature branch. Do NOT continue to Phase 1; end the run."; exit 1 ;;
esac
WANT="${PLAN_SLUG:-$HERE}"
if ! is_in_worktree; then
  echo "STOP: MAIN-CHECKOUT — cwd is the main checkout ($TOP). /post-plan commits and pushes; ADR-0062 forbids that here. Do NOT continue to Phase 1; end the run."
  exit 1
elif [ "$HERE" = "$WANT" ]; then
  echo "ALREADY-IN-TARGET — worktree $TOP is on branch '$HERE'. Continue to Phase 1."
else
  echo "STOP: WRONG-WORKTREE — this worktree is on '$HERE' but the run targets '$WANT'. Re-run as: bin/post-plan-now --pr <n>. Do NOT continue to Phase 1; end the run."
  exit 1
fi
# --- end post-plan worktree guard ---
```

---

## Phase 1: Locate Plan

Locate the plan backing this branch. Phase 2.5 reads its `## Out of Scope` section.

```bash
# Authoritative when a path was handed to this run (automouse handoff JSON's plan_file, or
# `bin/post-plan-now --plan <abs-path>`): use that path verbatim and skip the derivation below.
# Otherwise derive from the branch slug via the shared resolver — variant-aware (highest-numbered
# wins) and drift-aware (a single <prefix>-<slug>.md is adopted when no exact match exists, holding
# auto-merge via condition (13)). Mirrors bin/lib/plan-resolve.sh (shared with condition (7)/(13)
# blocks) and harness/planfile.py::_resolve_variant/_resolve_drift.
SLUG=$(git rev-parse --abbrev-ref HEAD)
PLAN_FILE="${PLAN_FILE:-}"   # honor automouse pre-set; empty triggers slug derivation
PLAN_SLUG_DRIFT=""
source "$(git rev-parse --show-toplevel)/bin/lib/plan-resolve.sh"
resolve_plan_file
# PLAN_FILE: resolved absolute path, or empty string (plan-blind)
# PLAN_SLUG_DRIFT: non-empty when a drift match was adopted (condition (13) holds auto-merge)
if [ -n "$PLAN_FILE" ] && [ -z "$PLAN_SLUG_DRIFT" ] && [ "$(basename "$PLAN_FILE")" != "$SLUG.md" ]; then
    echo "PLAN_VARIANT_SELECTED=$PLAN_FILE (highest-numbered; pass --plan <abs-path> to override)"
fi
if [ -n "$PLAN_FILE" ]; then
    echo "PLAN_FOUND=$PLAN_FILE"
    grep -qiE '^\s*\|.*Test type' "$PLAN_FILE" && echo "HAS_MATRIX=true" || echo "HAS_MATRIX=false"
    grep -qiE '^#+ *Security'    "$PLAN_FILE" && echo "HAS_PLAN_SECURITY=true" || echo "HAS_PLAN_SECURITY=false"
    grep -qiE 'Reuse'            "$PLAN_FILE" && echo "HAS_PLAN_REUSE=true" || echo "HAS_PLAN_REUSE=false"
else
    echo "PLAN_FOUND=none — plan-blind mode: skip every plan-conformance step below; behave exactly as before."
fi
```

When the automouse postplan prompt supplied an authoritative plan path, use it instead of the branch-slug derivation above. Record `$PLAN_FILE` and the flags. **Plan conformance is additive, never a hard dependency** — when `PLAN_FOUND=none` (any PR with no `/plan` file), every "if a plan exists" gate below is skipped and post-plan runs as it does today.

---

## Phase 2: Harness-crash triage (report and hold; never arm)

This phase runs only when `bin/post-plan-now` reached the fallback, which exports `POSTPLAN_HARNESS_RC`. An empty or unset value means no harness ran (interactive `/post-plan`, or `POST_PLAN_SKILL=1`): the block prints `CRASH-TRIAGE: skipped (no harness exit code)` and does nothing else; continue to Phase 2.5. Run this block once:

```bash
# --- harness-crash triage ---
# Reached only when the harness exited with a code should_fallback accepts (never 0, 3 or 7).
# This block reports and holds. It never pushes, never opens a PR, never arms or disarms.
# It sends no DM and posts no comment. After this session exits, bin/post-plan-now posts the
# one failure comment (conclude_status_badge) and sends the one crash DM (FAILDM_SEG).
HRC="${POSTPLAN_HARNESS_RC:-}"
if [ -z "$HRC" ]; then
echo "CRASH-TRIAGE: skipped (no harness exit code)"
else
PR_JSON=$(gh pr view --json number,state,autoMergeRequest 2>/dev/null || true)
PR_NUM=$(printf '%s' "$PR_JSON" | jq -r '.number // empty' 2>/dev/null)
PR_STATE=$(printf '%s' "$PR_JSON" | jq -r '.state // empty' 2>/dev/null); PR_STATE="${PR_STATE:-NONE}"
ARMED=$(printf '%s' "$PR_JSON" | jq -r 'if .autoMergeRequest then "yes" else "no" end' 2>/dev/null); ARMED="${ARMED:-unknown}"
echo "CRASH-TRIAGE: harness-rc=$HRC pr=${PR_NUM:-none} state=$PR_STATE armed=$ARMED"
fi
# --- end harness-crash triage ---
```

Record `PR_NUM` from the `CRASH-TRIAGE:` line (`pr=none` means no PR exists). On the skipped path, read it with `gh pr view --json number --jq .number`; an error there means no PR exists.

An armed PR stays armed: the harness armed it under its own conditions before the stop, and the fallback has no standing to reverse that. An unarmed PR stays unarmed and open. No PR means nothing is pushed; the human re-runs `bin/post-plan-now`, which is the only path that can open or arm one. The 2026-10-11 crash (PR #3159, a transient `gh pr edit` timeout with the PR already open) is the case this handles.

---

## Phase 2.5: Backlog Housekeeping

Out-of-Scope deferral sweep. Skip when `PLAN_FOUND=none`. The sweep files one backlog issue per deferral phrase in the plan's `## Out of Scope` section and dedups against every existing backlog issue, open or closed. It shares its code with the harness, so a harness run that already filed an item makes this a no-op for that item. A non-zero exit or a missing module never stops the run. Substitute `<PLAN_FILE>` (from Phase 1) and `<PR_NUMBER>` (from Phase 2). The original Phase 2.5 housekeeping was retired by ADR-0121; this slot now holds only the sweep.

```bash
WT=$(git rev-parse --show-toplevel)
SLUG=$(git rev-parse --abbrev-ref HEAD)
if [ -f "$WT/tools/postplan-harness/harness/outofscope.py" ]; then
  (cd "$WT/tools/postplan-harness" && python3 -m harness.outofscope \
      --plan "<PLAN_FILE>" --slug "$SLUG" --pr "<PR_NUMBER>" \
      --worktree "$WT") || echo "oos-sweep: exit $? (non-blocking)"
else
  echo "oos-sweep: module absent on this branch (non-blocking)"
fi
```

The harness runs the same sweep in `runner.py` (`outofscope.file_deferral_issues`), so on the crash path this is a dedup-safe repeat. Substitute `<PR_NUMBER>` with `$PR_NUM` from Phase 2 when set; skip this phase when no PR exists, since the module needs a PR to cite.

---

## Phase 5: E2E verify track

The harness runs PHPUnit, PHPStan and the Go track (`tools/postplan-harness/harness/adapters/verify.py`) and has no E2E track; this is the only verify work the fallback does. Skip when no PR exists (nothing to fix against), when the PR's `state=` is not `OPEN` (a merged PR has no `autoMergeRequest`, so it reads `armed=no`, and a push would resurrect the deleted branch), or when `bin/e2e-for-pr` maps no tests. A red E2E run is fixed in the worktree. Commit and push the fix per `## Incremental Checkpoints` only when the Phase 2 `CRASH-TRIAGE:` line exists and reports `state=OPEN` and `armed=no`. On `armed=yes`, `armed=unknown`, or the `CRASH-TRIAGE: skipped` line, leave the fix uncommitted and unpushed, because a push to an armed PR can merge with no review. Report the E2E result, and any held fix, in one `gh pr comment` of this phase's own. It is never used to arm. When E2E is red and the line reports `armed=yes`, open the comment with `E2E RED ON AN ARMED PR: a human should disarm auto-merge on the PR page until the held fix lands`. The skill itself never disarms; the comment is the warning.

**E2E agent (Haiku):**

Steps:
1. Run `bin/wt-down <worktree-name>` then `bin/wt-up <worktree-name> --seed`
2. Run `bin/e2e-for-pr <worktree-name>` and capture both stdout and exit code
3. Branch on the result:
   - **Exit 0, empty stdout** → print "No E2E tests map to changed files — skipping E2E" and stop
   - **Exit 2** → run full suite: `bin/e2e-wt <worktree-name>`
   - **Exit 0, test file list on stdout** → run targeted: `bin/e2e-wt <worktree-name> <test-files-from-stdout>`

Prompt MUST include: "Run these commands and report the summary output. Do NOT investigate, re-run, or diagnose individual test failures — just report the pass/fail counts and any error output." <!-- slop-ok -->

Prompt MUST ALSO include this long-run handling rule: "`bin/e2e-wt` can exceed the Bash tool's 600s cap. If it does, invoke Bash with `run_in_background: true` and poll via the **BashOutput** tool — do NOT pipe to a file and shell-loop on `grep`. If you absolutely must shell-poll, the terminator must accept every Playwright outcome (`grep -qE 'passed|failed|did not run|timed out|error'` scanning `tail -10`, not a single last-line match): Playwright's trailing line is often `N did not run` after an early setup failure, which will hang a `passed|failed`-only check forever." <!-- slop-ok -->

If it fails, fix in the worktree and re-run the failing track. Commit and push only under the `armed` rule above.

---

## Phase 10: Preview Environment

**Skip entirely if** `$CLAUDE_HEADLESS` is set (automouse autonomous mode — no human present to verify).

Check the PR state using the PR number captured in Phase 2 (`$PR_NUM`); skip when none:

```bash
PR_STATE=$(gh pr view <PR_NUMBER> --json state --jq '.state')
# Engine-only: the PR touches engine/ and nothing under ibl5/.
ENGINE_ONLY=$(gh pr diff <PR_NUMBER> --name-only 2>/dev/null \
    | awk '/^engine\//{e=1} /^ibl5\//{i=1} END{print (e && !i) ? "true" : "false"}')
echo "PR_STATE=$PR_STATE ENGINE_ONLY=$ENGINE_ONLY"
```

**Branch on `$PR_STATE`:** if `MERGED` → **Path A** (sync local, then rebuild the main Docker stack with fresh prod data. Skip Path A entirely if `ENGINE_ONLY=true`, since an engine-only change touches no `ibl5/` PHP. It requires `REMOTE_*` prod credentials in `.env`). Otherwise → **Path B** (worktree preview via `bin/wt-down`/`bin/wt-up --prod`; skip if the worktree pre-existed or earlier phases left uncommitted fixes). **Read `.claude/skills/post-plan/_phase-10-preview-environment.md`** for the full step-by-step of whichever path applies before running it. Do NOT run `wt-remove` or `git branch -D`.

---

## Phase 11: Background Process Cleanup (MANDATORY — never skip)

**You MUST execute this phase before ending your turn.** This is not optional even if all earlier phases succeeded cleanly.

`bin/e2e-wt` from Phase 5 may still be running. If it holds the pipeline open after you emit your final response, the automouse runner's stall-kill fires after 30 minutes and burns an attempt.

Kill the lingering patterns so their tool results deliver immediately:

```bash
pkill -f 'bin/e2e-wt' 2>/dev/null
pkill -f 'bunx.*playwright' 2>/dev/null
echo "Background process cleanup complete"
```

This is a single Bash call — no agent needed. Ignore any "no matching processes" output; the `2>/dev/null` suppression handles it. The killed processes deliver their tool results immediately as errors, which is expected and harmless.
