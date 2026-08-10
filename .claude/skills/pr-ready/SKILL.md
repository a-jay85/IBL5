---
name: pr-ready
description: Take one open PR from conflicted-or-unknown state to a posted readiness verdict — rebase onto master, resolve conflicts, wait for CI, judge plan fidelity, post a sticky verdict comment, then stop.
disable-model-invocation: true
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
  - Skill
last_verified: 2026-08-10
---
<!-- NO `model:` KEY — DELIBERATE, DO NOT ADD ONE.
     Runtime Phase 6 (plan-intent fidelity review) is Opus-column judgment:
     .claude/rules/agent-tiering.md — "Never delegate understanding." A `model:`
     pin would force this skill onto a fixed tier regardless of the invoking
     session's model. Do NOT "harmonise" this file with /pr-review's
     `model: claude-sonnet-4-6` — that skill is a pure diff review with no
     fidelity judgment, so a Sonnet pin is correct there and wrong here. -->

# /pr-ready — PR readiness and plan-fidelity checker

Drives **one** open PR from "conflicted / unknown state" to a posted readiness verdict: enter the PR's existing worktree, delegate the rebase chore, resolve conflicts and push, watch CI, judge the implementation against its plan's stated intent, post a sticky verdict comment — then stop.

This skill adds **semantic** judgment the existing pipeline does not cover. `/post-plan` Phase 5.0 checks mechanical declared-artifact conformance; Phase 4B runs structured code review over the **pre-rebase** diff. Neither asks whether the code does what the plan *intended*. `/pr-ready` asks exactly that and nothing else — it does not re-run structured code review, does not define review agents, and does not reference the shared review-agent definitions.

## Invariants — stated once; later phases cite these rather than repeat them

- **One PR per invocation.** No batch mode, no PR-list iteration.
- **The orchestrator never delegates runtime Phase 6.** The fidelity judgment is the deliverable; delegating it is a defect.
- **Sub-agent returns are thin pointers** — `path:line`, SHAs, status words. Never pasted diffs or file bodies.
- **Flat fan-out only.** A delegate may not spawn a delegate.
- **Every glob is quoted** — `--include="*.md"`, never `--include=*.md`.
- **The run STOPS at the verdict.** No merge, no auto-merge arming, no backlog housekeeping, no `/post-plan` chain, no worktree teardown.
- **`STOP:` lines are hard stops for the model, not just for the shell.** Three blocks below print a `STOP:` line: the Phase 0 argument gate, the Phase 0 canonical-root guard, and the Phase 1 plan check. Where such a block runs `exit 1`, that `exit` terminates **only that Bash invocation** — it does not terminate the skill, because a skill's blocks are run by the model. The contract is therefore: **on a non-zero rc, or on printing a `STOP:` line, stop the run and make no further tool call.** None of the three relies on shell exit status to halt anything but the shell.

## Runtime phases

**Phase 0 — enter the worktree.**

1. **Argument gate.** `/pr-ready` takes exactly one argument, the PR number. If it is missing or does not match `^[0-9]+$`, print

   `STOP: /pr-ready needs exactly one PR number, e.g. /pr-ready 1742`

   and stop without any further tool call.

2. **Load the deferred tool first.** `ToolSearch("select:EnterWorktree")` is the **first** tool call of the run, before any worktree read. `EnterWorktree` is deferred, so calling it without loading the schema fails with `InputValidationError`; and a worktree read attempted first trips the cross-worktree straddle gate in `~/.claude/hooks/plan-gate-edit.sh`.

3. **Canonical-root guard.** `EnterWorktree`'s `path:` form only accepts a target listed in `git worktree list` *for the current repo* and, from inside a worktree, only targets under that repo's `.claude/worktrees/` (example) directory. This repo's worktrees live at `~/GitHub/IBL5-worktrees/<slug>`, so a worktree→worktree switch is rejected — the skill must be invoked from the main checkout. Run this block first:

   ```bash
   source "$(git rev-parse --show-toplevel)/bin/lib/git-helpers.sh"
   if is_in_worktree; then
     echo "STOP: /pr-ready must be invoked from the main checkout (/Users/ajaynicolas/GitHub/IBL5), not from a worktree. EnterWorktree cannot switch worktree-to-worktree for IBL5-worktrees paths."
     exit 1
   fi
   ```

   Source via `$(git rev-parse --show-toplevel)`, not a bare relative path: the skill's cwd is not guaranteed to be the repo root. `is_in_worktree()` is defined in `bin/lib/git-helpers.sh` and compares `--absolute-git-dir` against `--git-common-dir`. Per the invariants above, a non-zero rc here ends the run.

4. **Resolve the slug and enter.**

   ```bash
   SLUG=$(gh pr view <N> --json headRefName --jq .headRefName)
   git worktree list
   ```

   Confirm `~/GitHub/IBL5-worktrees/$SLUG` appears in the `git worktree list` output. If it does not, print

   `STOP: no existing worktree for branch $SLUG — /pr-ready enters an existing worktree, it never creates one.`

   and stop. Otherwise call `EnterWorktree(path: "/Users/ajaynicolas/GitHub/IBL5-worktrees/$SLUG")`.

5. **Docker note** — only if a later step needs the app running. Derive the slug from the tree you are actually in, `basename "$(git rev-parse --show-toplevel)"`, then `docker start ibl5-db-<slug> ibl5-php-<slug>`. Never hardcode a slug from a previous session; never use `main.localhost` from a worktree; always navigate `/ibl5/` paths, never bare `/`.

**Phase 1 — plan, master pin, protection, prior-review probe.**

1. **Read the plan.** `PLAN=~/claude-plans/"$(git rev-parse --abbrev-ref HEAD)".md`. If it does not exist, print loudly

   `STOP: no plan at $PLAN. /pr-ready's Phase 6 judges implementation against the plan's stated intent; without the plan there is nothing to judge against. Re-run once the plan file is restored, or run /pr-review instead for a plain code review.`

   and stop. Do **not** fall back to the PR body for plan intent — the PR body is one of the things Phase 6 audits.

2. `git fetch origin`. Nothing in this skill ever runs a bare `git rebase` against `origin/master`; see the `--onto` recipe in the Phase 2 include.

3. **Pin master before spawning anything:** `MASTER_SHA=$(git rev-parse origin/master)`. Every later step, and the Phase 2 delegate, use this pinned SHA — never a re-resolved `origin/master`.

4. **Branch-protection strict flag.**

   ```bash
   STRICT=$(gh api "repos/{owner}/{repo}/branches/master/protection" --jq '.required_status_checks.strict // false')
   ```

   On a 403/404 (a token without admin read), set `STRICT=true` and say so in the verdict. Failing closed costs one extra divergence check; failing open ships a stale-base merge.

5. **Prior-Phase-4B probe.** `gh pr view <N> --json comments,reviews` and look for a `### Code review` heading from the post-plan bot in **both** the issue comments and the review bodies — findings are posted as a review body with inline threads, not only as issue comments. Record the boolean as `PHASE_4B_RAN`. This is a **probe, not a gate**: the value is reported in Phase 6 and never used to skip work.

**Phases 2 and 3 — rebase and conflict resolution.**

Read `.claude/skills/pr-ready/_rebase-and-conflicts.md` now and follow it end-to-end before continuing. It holds the Phase 2 delegation packet and the Phase 3 three-way conflict-resolution procedure. Pass the delegate the pinned `$MASTER_SHA` — never let it resolve `origin/master` itself.

**Phase 4 — prove nothing was lost, push, watch CI.**

1. **Load the deferred watcher tools here, not in Phase 0:** `ToolSearch("select:Monitor,TaskStop")`. Deferring keeps `Monitor`'s long schema out of context for the phases that never use it.

2. **Lost-work proof, two signals.** `git cherry -v origin/<branch> HEAD` is the weak signal: after a squash **every** replayed commit shows `+` by design, so `git cherry` alone cannot carry the proof. The authoritative check is content equivalence of the tree diff captured before and after the rebase (the Phase 2 include wrote `/tmp/pr-ready-diff-pre-$$.patch`):

   ```bash
   git diff origin/master...HEAD > /tmp/pr-ready-diff-post-$$.patch
   diff <(git apply --numstat /tmp/pr-ready-diff-pre-$$.patch | sort) \
        <(git apply --numstat /tmp/pr-ready-diff-post-$$.patch | sort) \
     && echo "TREE-EQUIVALENT" || echo "TREE DIVERGED — inspect before pushing"
   ```

   `TREE DIVERGED` is expected **only** when Phase 3 resolved a real conflict; in that case name each diverging path in the Phase 6 verdict. Any other divergence stops the run.

3. `git push --force-with-lease` — never a bare `--force`. The lease is what catches a concurrent push into the same branch.

4. **`mergeable=UNKNOWN` handling — bounded, no loop.** GitHub computes mergeability asynchronously, so the first read right after a push is usually `UNKNOWN`. Check once; if `UNKNOWN`, wait ~30s and check exactly once more; then report whatever the second read says. Do **not** loop:

   ```bash
   gh pr view <N> --json mergeable,mergeStateStatus
   sleep 30
   gh pr view <N> --json mergeable,mergeStateStatus,state
   ```

5. **CI watcher — exactly one, keyed to the head SHA.** If a watcher from an earlier iteration is live, kill it first with `TaskStop(task_id: "<the id recorded when it was armed>")`. **Record the id `Monitor` returns in the run notes at arm time** — `TaskStop` has no "stop all" form, so an unrecorded id is an orphaned watcher. **Never** poll with `sleep N; gh pr checks` on the main thread: that re-reads the full orchestrator context on every call, the spend bug `.claude/rules/work-triage.md` names.

   Arm one `Monitor` with `description`, `persistent`, and `timeout_ms` all set (all three are required):

   ```
   Monitor(
     description: "CI checks for PR <N> @ <HEAD_SHA>",
     persistent: false,
     timeout_ms: 3600000,
     command: <the bash loop below>
   )
   ```

   ```bash
   HEAD_SHA="$(git rev-parse HEAD)"; prev=""
   while true; do
     live="$(gh pr view <N> --json headRefOid --jq .headRefOid 2>/dev/null || echo "")"
     if [ -n "$live" ] && [ "$live" != "$HEAD_SHA" ]; then
       echo "STALE: head moved $HEAD_SHA -> $live; this watcher is obsolete"; break
     fi
     s="$(gh pr checks <N> --json name,bucket 2>/dev/null || echo '[]')"
     cur="$(jq -r '.[] | select(.bucket!="pending") | "\(.name): \(.bucket)"' <<<"$s" | sort)"
     comm -13 <(printf '%s\n' "$prev") <(printf '%s\n' "$cur")
     prev="$cur"
     jq -e 'length > 0 and all(.[]; .bucket!="pending")' <<<"$s" >/dev/null && { echo "CI COMPLETE"; break; }
     sleep 30
   done
   ```

   The emitted line is `"\(.name): \(.bucket)"` and **not** a success-only filter because **silence is not success**. A filter matching only `pass` stays mute through `fail`, `cancel`, `skipping`, and `action_required`, and mute is indistinguishable from "still running". The stale-SHA break is what stops an orphaned watcher from reporting on a superseded push.

**Phase 5 — strict re-check loop.**

If `STRICT` is false, skip this phase. If true, then after CI reports complete and green, re-check divergence against the *current* master:

```bash
git fetch origin
gh pr view <N> --json mergeStateStatus --jq .mergeStateStatus   # BEHIND => must re-base
```

If `BEHIND`, re-pin `MASTER_SHA=$(git rev-parse origin/master)` and loop back to Phase 2 with a fresh delegate on the new pin. **Bound the loop at 3 iterations**; on the fourth, stop and report `master is moving faster than this branch can rebase — merge manually or retry when master quiets`. An unbounded loop is the failure mode a strict-protection repo with a busy master produces.

**Phase 6 — plan-intent fidelity review.**

Read `.claude/skills/pr-ready/_plan-fidelity-review.md` now and perform the review **yourself**. This phase is NEVER delegated — `.claude/rules/agent-tiering.md`: "Never delegate understanding." Spawning any sub-agent for this phase is a defect.

**Phase 7 — verdict and stop.**

1. **Run the shared hold predicates.** `bin/lib/pr-armable.sh` is **sourced, not executed** — it carries no `set -euo pipefail` at file scope by design. Reuse its six predicates rather than re-deriving any hold logic:

   ```bash
   source bin/lib/pr-armable.sh
   BODY="$(gh pr view <N> --json body --jq .body)"
   TITLE="$(gh pr view <N> --json title --jq .title)"
   LABELS="$(gh pr view <N> --json labels --jq '.labels')"
   FILES="$(gh pr view <N> --json files --jq '.files')"
   pr_manual_testing_clearance "$BODY"
   pr_golden_hold "$FILES"
   pr_dep_holds "$BODY"
   pr_feat_hold "$TITLE" "$LABELS"
   pr_pipeline_authored_hold "$LABELS"
   pr_unresolved_findings_hold <N>
   ```

   Report each predicate's result as one line in the verdict. These are **advisory inputs to the human's merge decision** — `/pr-ready` never arms auto-merge and never merges.

2. **Post the sticky verdict.** Marker, placed as the **last line of the body** so an update matches:

   `<!-- pr-ready-verdict -->`

   There is no helper in `bin/lib/` for this, so use the find-and-update-else-create shape from `bin/pr-canary-check` (`STICKY_MARKER` at line 19, `post_sticky()` below it):

   ```bash
   id=$(gh api "repos/{owner}/{repo}/issues/<N>/comments" --paginate \
     --jq '.[] | select(.body | contains("<!-- pr-ready-verdict -->")) | .id' | head -1)
   if [ -n "$id" ]; then
     gh api --method PATCH "repos/{owner}/{repo}/issues/comments/$id" -F body=@"$tmpfile"
   else
     gh pr comment <N> --body-file "$tmpfile"
   fi
   ```

   Comment body sections, in order: **rebase result** (the master SHA used, conflicts resolved), **CI result**, **plan-fidelity verdict**, **hold predicates**, and one explicit **READY / NOT READY** line.

3. **STOP — hard terminator.** The run ends at the posted comment. No merge. No auto-merge arming. No backlog housekeeping. No `/post-plan` chain. No worktree teardown. The user reviews every PR deliberately.
