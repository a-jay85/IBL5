---
description: Phase 6.5 remediation procedure for the post-plan harness. Fixes every Phase 6 finding in-PR; harness commits and pushes after exit.
last_verified: 2026-09-23
---

# /pr-ready runtime Phase 6.5 — in-PR remediation

Purpose: the full Phase 6.5 procedure, lifted out of `SKILL.md` so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/review-shared/_phase65-remediation.md`.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in Phase 1.3. A value captured in one Bash call does not survive into the next.

**Phase 6.5 — Remediation.**

Every Phase 6 finding gets fixed and its prevention filed, in this PR's existing worktree. This is the one amendment to the stop-at-verdict invariant; everything that invariant still forbids stays forbidden. The compiled post-plan harness loops this procedure up to three remediation rounds per run; this skill path stays single-shot.

1. **Load the shared procedure.** `git show <MASTER_SHA>:.claude/skills/fix-and-prevent/_remediation.md` — same pin, same reason as the Phase 2 and Phase 6 includes (the `git show` include invariant in `SKILL.md`). Declared fallback, per the include-fallback clause: if `git show` fails and the file is genuinely present in this worktree, `Read` it by path and record `include-source: worktree (pin predates skill)` in the verdict. If neither source yields it, print `STOP: cannot load _remediation.md from <MASTER_SHA> or from the worktree` and stop.

2. **Cleanliness check, before the first edit.** Run `git status --porcelain`. If it prints anything, print `STOP: worktree dirty before remediation` followed by that output, and stop. Phase 0.3 may have entered a worktree that is a peer's active workspace; the `git add -A` in step 4 would sweep their uncommitted work into this commit and the harness would push those changes too. A clean tree here is the normal case. The harness pushed this HEAD before review. Run it once at Phase 6.5 entry. From step 3 onward the tree is dirty by design.

3. **Remediate every finding.** For each Phase 6 finding — **notes as well as blocking**, across all six 6d classes — follow `_remediation.md` in **`Mode: in-PR`**. State that mode line out loud before its step 1; the procedure refuses to run without a declared mode. In-PR mode overrides `/fix-and-prevent`'s § Calibration "Out of scope" carve-outs: a finding too small to name a defect class still gets an entry, with `class: n/a — <reason>`. Never zero entries.
"Never zero entries" binds the findings Phase 6 actually emitted — it does not manufacture one. A Phase 5.9 outcome of `REPLACED`, `APPENDED` or `UNCHANGED` is a routine refresh, not a finding: it produces no remediation entry, no backlog row, and no `last_verified:` bump. Only `AMBIGUOUS` reaches this phase, as the 6d.4 finding it is, and that one does get an entry.

   - **Worktree:** this PR's existing one. Never `bin/wt-new`, never a second worktree, never a teardown.
   - **Backlog:** run `bin/backlog new <label> "<title>"` for each finding (the `gh issue create` wrapper; search before filing with `bin/backlog search`). Do not run the full `/backlog` chain. Consolidate findings sharing a surface into one issue.
   - **Fifth-file gate.** `~/.claude/hooks/plan-gate-edit.sh` Check 1 denies the 5th distinct repo file edited on the main thread in one turn. When the Agent tool is available, route remaining fixes to one `subagent_type: "sonnet-4-6"` sub-agent (omit `model`). When the Agent tool is absent (harness context), apply the overflow rule instead.
   - **Overflow rule.** Fix what is clearly in scope of this PR; file the remainder as backlog rows marked `not fixed — filed`; say so in the Phase 7 verdict. A `/pr-ready` run never expands into a sweep.

4. **Scope reconciliation.** Step 2 already proved the tree carried nothing but this phase's own edits.

   Run `git diff --numstat HEAD` and compare it against every explicit **file count, line count, or diff stat** written in the PR body's hand-authored Scope prose. Step 3's remediation routinely adds files and expands test cases past the plan's estimates, so a Scope written at Phase 4 is stale by default here. Any number the numstat contradicts gets corrected in the body via `gh pr edit` in this step. Deferring to Phase 7 is wrong: it posts a comment and never touches the body. This is the 6d.4 "PR body vs. reality" check applied to this phase's own output; leaving it stale re-creates the exact blocking finding Phase 6 just cleared. Before writing the body: `Read .claude/skills/post-plan/_pr-body-claims.md` to apply the negative-claim re-check and the files-changed block rules. The machine-generated `<!-- files-changed:begin -->` block is out of scope. Phase 5.9 owns it.

   The harness commits and pushes after this phase exits. Use `gh pr edit` for any Scope number corrections confirmed above. Do not call `git commit` or `git push`.

   The commit type is `chore:` per `.claude/rules/commit-conventions.md`. A fidelity remediation plus a backlog row is invisible to a league GM.

Then proceed to Phase 7, which posts the single verdict comment covering both the findings and this remediation.

