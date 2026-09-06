---
description: /pr-ready runtime Phase 6.7 — peer-safe worktree bring-up, observable manual-test row execution, PR-body row ticking. Loaded by SKILL.md via git show at Phase 6.7.
last_verified: 2026-09-04
---

# /pr-ready runtime Phase 6.7 — manual-testing execution

Purpose: the full Phase 6.7 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/skills/pr-ready/_phase67-manual-testing.md`.

`<MASTER_SHA>`, `<N>` and `<slug>` below are **literals to substitute** with the values pinned in Phase 1.3 — a value captured in one Bash call does not survive into the next.

**Phase 6.7 — manual-testing execution.**

**This phase is mandatory and always runs** — reaching it is never conditional on Phase 6.5. It is non-fatal to the verdict by construction: every failure inside it degrades to unticked rows plus a stated reason, and the verdict still posts.

1. **Decide whether the phase does anything.** Read the PR body's `## Manual Testing` section with the `bin/lib/pr-armable.sh` `pr_manual_testing_clearance` predicate's own idiom: `gh pr view <N> --json body --jq '.body' | sed -n '/^## Manual Testing/,/^## /p'`. If the section is absent, or its content prefix-matches `No manual testing needed`, print `MANUAL-TESTING: CLEARED — nothing to run`, record that line in the run notes, and go to Phase 7. Reading the body directly here is deliberate: the `manual-testing-clearance` hold line is produced by `scripts/holds.sh` in **Phase 7 step 1**, after this phase has already had to decide.

2. **Reuse the slug already pinned in Phase 1.3.** `<slug>` is a literal the run pinned long before this phase — the same one every `scripts/*.sh` invocation takes as its second argument, including the `scripts/holds.sh` call in Phase 7 step 1. Substitute it by hand. **Do not re-derive it here:** Phase 0.3 already crossed the `EnterWorktree` boundary, so no Bash in this phase may contain a command substitution, and this include therefore contains zero command-substitution tokens. The hostname is `<slug>.localhost` under the `/ibl5/` path prefix per `.claude/rules/worktree-hostname.md` — never hardcoded, never `main.localhost`.

3. **Bring the stack up.** `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/wt-bring-up.sh > /tmp/pr-ready-bringup-<N>.sh && test -s /tmp/pr-ready-bringup-<N>.sh && bash /tmp/pr-ready-bringup-<N>.sh <N> <slug>`. Record the `BRINGUP:` line verbatim in the run notes. The script is peer-safe by construction: a dirty target worktree yields `BRINGUP: SKIP peer-dirty` and `bin/wt-up` is never invoked, because Phase 0.3 may have entered a peer's active workspace and a rebuild there would destroy their work; and an already-running stack short-circuits to `BRINGUP: ALREADY-UP`, because `bin/wt-up` unconditionally purges the worktree's DB volume before rebuilding. On `SKIP peer-dirty`, `NOT-READY`, `FAILED`, or a missing `BRINGUP-COMPLETE`, set the run-note literal `MANUAL_TESTING_STACK_FAILED=true`, skip steps 4-5, and carry the reason into Phase 7. **Never tear the stack down** — it is left up for the user, and the Phase 7 terminator's no-teardown clause is unchanged.

4. **Classify and execute the rows.** `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/manual-rows.sh > /tmp/pr-ready-rows-<N>.sh && test -s /tmp/pr-ready-rows-<N>.sh && bash /tmp/pr-ready-rows-<N>.sh <N> <slug>`. Skip this step entirely when the run notes carry `MANUAL_TESTING_STACK_FAILED=true`. Record every `ROW` line verbatim. A run that prints no `MANUAL-ROWS-COMPLETE` is itself a finding: report it and tick nothing.

   The script owns the ordered nine-branch classifier. This include restates only the two invariants a future editor must not relax: **human-perception wins on any row matching both classes, and an unclassifiable row is human-perception** — a wrongly-ticked row is a false clearance, while a wrongly-skipped one only costs the user a manual look; and **classification alone never ticks — a row is ticked only when its assertion was actually executed and passed.** A `SKIP-AUTH` (a 30x to a login page) is never a pass, which is why the executor's curl runs without `-L`.

5. **Tick the passing rows.** `git show <MASTER_SHA>:.claude/skills/pr-ready/scripts/tick-rows.sh > /tmp/pr-ready-tick-<N>.sh && test -s /tmp/pr-ready-tick-<N>.sh && bash /tmp/pr-ready-tick-<N>.sh <N> <slug>`. Ticking edits the **PR body only, never the plan file**; the script re-fetches the body fresh immediately before editing, so it cannot clobber the `gh pr edit` Phase 6.5 step 4 already performed, and with zero `PASS` ids it performs no `gh pr edit` at all. Record the `TICKED:` count.

   **Why the PR body and not the plan file.** A plan-file tick would be a repo-file edit inside a phase that already touches `SKILL.md` and two includes, walking straight into `~/.claude/hooks/plan-gate-edit.sh` Check 1's five-distinct-repo-files deny — and it would dirty the worktree after Phase 6.5's push, stranding an uncommitted change nothing in the run commits. The PR body carries the checkboxes `/post-plan` wrote and is what a reviewer actually reads.

6. **Carry the results into Phase 7.** Add these to the run notes as literals, for the verdict comment and the DM: the `BRINGUP:` line; the stack URL `http://<slug>.localhost/ibl5/`, or the failure reason when the stack never came up; the per-row `ROW` lines grouped as passed / failed / left-for-human; and the `TICKED:` count. State that the bring-up used `--seed` and not `--prod`, so a row left unticked for want of production data is legible rather than mysterious. Phase 7's comment gains one **manual testing** section carrying exactly these, placed after the **hold predicates** section and before the READY / NOT READY line.
