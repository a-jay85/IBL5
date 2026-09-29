---
description: Phase 6.5 remediation procedure for the post-plan harness. Fixes every Phase 6 finding in-PR; harness commits and pushes after exit.
last_verified: 2026-09-28
---

# Phase 6.5 in-PR remediation

Purpose: the full Phase 6.5 procedure, lifted out of the post-plan orchestrator so it is resident only from the turn it loads.

Read at runtime via `git show <MASTER_SHA>:.claude/review-shared/_phase65-remediation.md`.

`<MASTER_SHA>` and `<N>` below are **literals to substitute** with the values pinned in Phase 1.3. A value captured in one Bash call does not survive into the next.

**Phase 6.5 Remediation.**

Every Phase 6 finding gets fixed and its prevention filed, in this PR's existing worktree. After the verdict is posted the run stops; the complete list of forbidden post-verdict actions is in step 4 of `_phase7-verdict.md`. The compiled post-plan harness loops this procedure up to three remediation rounds per run; this skill path stays single-shot.

1. **Load the shared procedure.** `git show <MASTER_SHA>:.claude/skills/fix-and-prevent/_remediation.md`. Same pin as Phase 2 and Phase 6 (the `git show` include invariant in the orchestrator). Declared fallback, per the include-fallback clause: if `git show` fails and the file is genuinely present in this worktree, `Read` it by path and record `include-source: worktree (pin predates skill)` in the verdict. If neither source yields it, print `STOP: cannot load _remediation.md from <MASTER_SHA> or from the worktree` and stop.

2. **Cleanliness check, before the first edit.** Run `git status --porcelain`. If it prints anything, print `STOP: worktree dirty before remediation` followed by that output, and stop. Phase 0.3 may have entered a worktree that is a peer's active workspace; the harness would push uncommitted peer work once it exits. A clean tree here is the normal case. The harness pushed this HEAD before review. Run it once at Phase 6.5 entry. From step 3 onward the tree is dirty by design.

3. **Remediate every finding.** For each Phase 6 finding (notes as well as blocking, across all six 6d classes) follow `_remediation.md` in **`Mode: in-PR`**. State that mode line out loud before its step 1; the procedure refuses to run without a declared mode. In-PR mode overrides `/fix-and-prevent`'s § Calibration "Out of scope" carve-outs: a finding too small to name a defect class still gets an entry, with `class: n/a — <reason>`. Never zero entries.
"Never zero entries" binds the findings Phase 6 actually emitted. A Phase 5.9 outcome of `REPLACED`, `APPENDED` or `UNCHANGED` is a routine refresh. It produces no remediation entry, no backlog row, and no `last_verified:` bump. Only `AMBIGUOUS` reaches this phase, as the 6d.4 finding it is, and that one does get an entry.

   - **Worktree:** this PR's existing one. Do not run `bin/wt-new`, do not create a second worktree, do not tear down the existing one.
   - **Backlog:** file an issue only for a finding with work left after this pass: a `not fixed — filed` row or a prevention rung 1-5 (`_remediation.md` step 5, `Mode: in-PR`). A finding fixed here with no gate warranted gets its entry in the Phase 7 verdict and no issue. Do not run the full `/backlog` chain. Consolidate findings sharing a surface into one issue. Apply these filing rules in order:
     - **File after the last fix.** Write issues only once every step 3 fix for this pass is in the tree. Any issue filed earlier in this run gets re-checked against `git diff HEAD` now. Close each one the final diff resolved with `bin/backlog close <n> "resolved in-PR by <pr-url>"`.
     - **Dedup against this PR first.** Run `bin/backlog for-pr <pr-url>`, taking the URL from `gh pr view --json url -q .url`. When a listed issue covers the same surface, add a comment to it with `gh issue comment <n> --repo a-jay85/IBL5-backlog` and file nothing new. Then run `bin/backlog search <keywords>` for older issues from other PRs.
     - **Skip retired files.** A finding whose only location is a file this PR deletes or renames away gets no issue. `git diff --name-status master...HEAD` shows those as `D` rows and the old path of `R` rows.
     - **Require a failure scenario.** The body names a concrete input or state and the wrong output, crash, or cost that follows. A finding with no nameable failure scenario gets no issue.
     - **No cosmetic-only issues.** Wording, formatting, comment style, or naming with no behavior change is fixed in this pass or dropped. It is never filed.
     - **Command.** `bin/backlog new <label> "<title>" "<body>"`. Line 1 of the body is the PR URL alone. Line 2 cites `path/to/file.ext:LINE`. The rest states the failure scenario. `bin/backlog` exits 2 and names the failed rule when the body misses any of these. Fix the body and re-run.
   - **Fifth-file gate.** `~/.claude/hooks/plan-gate-edit.sh` Check 1 denies the 5th distinct repo file edited on the main thread in one turn. When the Agent tool is available, route remaining fixes to one `subagent_type: "sonnet-5-5"` sub-agent (omit `model`). Before spawning, state the delegate boundary: remaining code fixes and backlog-row appends only. The delegate does not commit, push, arm auto-merge, change worktrees, or spawn further delegates. When the Agent tool is absent (harness context), apply the overflow rule instead.
   - **Overflow rule.** Fix what is clearly in scope of this PR; file the remainder as backlog rows marked `not fixed — filed`; say so in the Phase 7 verdict. A remediation run never expands into a sweep.

4. **Scope reconciliation.** Step 2 already proved the tree carried nothing but this phase's own edits.

   Run `git diff --numstat HEAD` and compare it against every explicit **file count, line count, or diff stat** written in the PR body's hand-authored Scope prose. Step 3's remediation routinely adds files and expands test cases past the plan's estimates, so a Scope written at Phase 4 is stale by default here. Any number the numstat contradicts gets corrected in the body via `gh pr edit` in this step. Deferring to Phase 7 is wrong: it posts a comment and never touches the body. This is the 6d.4 "PR body vs. reality" check applied to this phase's own output; leaving it stale re-creates the exact blocking finding Phase 6 just cleared. Before writing the body: `Read .claude/skills/post-plan/_pr-body-claims.md` to apply the negative-claim re-check and the files-changed block rules. The machine-generated `<!-- files-changed:begin -->` block is out of scope. Phase 5.9 owns it.

   Numbers are one class of claim. The same reconciliation covers **every factual assertion** this phase writes into the body, numeric or prose: that an Issue was created, that a migration is complete, that a test was run, that a follow-up was filed, that a file was deleted. Each such sentence must satisfy one of two shapes before it is written. **Shape A, verified:** the sentence names the proof command that was run in this step and whose output supports it (`gh issue view <N>`, `git diff --stat HEAD -- <path>`, `vendor/bin/phpunit --filter <Test>`, `bin/backlog search <term>`). **Shape B, explicit uncertainty:** the sentence says what was not checked, in those words, for example `Issues created: #A–#B; migration completeness not verified`. A sentence whose proof command cannot be named is written in Shape B or is deleted. It is never written as fact. Correct an offending sentence already in the body with the same `gh pr edit` call used for number corrections. This closes the gap where a prose claim clears a Phase 6 blocker that no later phase can test: `/pr-ready` 6d.4 reads the body against the diff, and a claim about external state (an Issue tracker, a deploy target, a sibling repo) is undecidable from the diff alone, so an unproven one is a 6d.4 finding on the next run.

   The harness commits and pushes after this phase exits. Use `gh pr edit` for any Scope number corrections confirmed above. Do not call `git commit` or `git push`.

   The commit type is `chore:` per `.claude/rules/commit-conventions.md`. A fidelity remediation plus a backlog row is invisible to a league GM. Never retitle a commit to route around a hold; classify by what the diff is.

Then proceed to Phase 7, which posts the single verdict comment covering both the findings and this remediation.

