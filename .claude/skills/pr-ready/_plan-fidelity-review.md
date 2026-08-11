# /pr-ready runtime Phase 6 — plan-intent fidelity review

Purpose: the criteria and verdict shape for the semantic judgment this skill exists to produce — does the implementation do what the plan *intended*, not merely what its tests assert?

**6a. This review runs on the orchestrator. It is NEVER delegated.** Do not spawn an agent for it. Do not delegate any part of it. Do not summarise the diff via a sub-agent and review the summary. `.claude/rules/agent-tiering.md` reserves this class for the Opus column — "Never delegate understanding" — and the cost of delegating is not the delegate's raw capability but that the orchestrator loses the findings the delegate filtered out. Spawning a sub-agent for any part of this phase is a defect in the run.

**6b. Inputs.** Gather all five before judging anything:

1. The plan file read in runtime Phase 1 (`~/claude-plans/<branch>.md`). This input is never missing: `SKILL.md` Phase 1.1 hard-stops the run when the plan is absent. If you have reached this phase without it, the run skipped a `STOP:` — say so and emit `NOT READY`; do not substitute the PR body.
2. The full **post-rebase** diff — `gh pr diff <N>`, or `git diff origin/master...HEAD` locally.
3. The PR body.
4. The list of conflict-resolved paths recorded in runtime Phase 3.
5. `PHASE_4B_RAN`, the boolean from runtime Phase 1's prior-review probe.

**6c. Two mandatory statements.** The review is incomplete without both, worded explicitly in the verdict:

- **(a) Whether Phase 4B structured code review ran on this PR** (from `PHASE_4B_RAN`). If it did not, say so plainly and recommend running `/pr-review <N>` before the PR is merged. `/pr-ready` deliberately does **not** substitute for structured code review — it neither defines nor references the shared review-agent definitions, and it produces no scored findings.
- **(b) That Phase 4B, when it ran, reviewed the PRE-REBASE diff.** Therefore every line produced by runtime Phase 3 conflict resolution is code no structured review has ever covered, and this fidelity review is its only coverage. **Name each conflict-resolved path** in the statement — do not summarise them as a count.

**6d. The fidelity checks.** Each produces an explicit finding or an explicit "matches" — never silence. Each check names what makes its finding **blocking** (`NOT READY`) rather than a note (`READY WITH NOTES`); when a finding is blocking, say which clause below made it so:

1. **Intent coverage** — for each of the plan's implementation phases, does a corresponding change exist in the diff? Name any phase with no diff footprint. **Blocking** unless the omission is *declared* — the plan, the PR body, or a posted comment says that phase was descoped, deferred, or split to a follow-up. An undeclared missing phase is `NOT READY`; a declared one is a note.
2. **Intent fidelity** — where a change exists, does it do what the phase *said*, or a different thing that merely satisfies the phase's tests? This is the semantic question `/post-plan` Phase 5.0 (`.claude/skills/post-plan/_phase-5-final-verification.md`) structurally cannot ask: it verifies the declared test *paths* were written, never that the behavior matches intent. **Blocking** whenever the implemented behavior differs from the stated intent in a way a reader of the plan would not predict — this divergence is the defect class the whole skill exists to catch, so resolve doubt toward `NOT READY`. A divergence that still satisfies the phase's stated goal by a better route is a note, and must say why it is better.
3. **Scope creep** — changes in the diff that no plan phase asked for. Name each one; do not assume they are harmless. **Blocking** when an unasked-for change touches a security surface (SQL, a POST/form endpoint, an auth/authz-gated route, user-facing output rendering), a migration, a ship-pipeline gate (`.claude/skills`, `.claude/rules`, `~/.claude/hooks`), or user-visible behavior — those are the surfaces `/plan` would have designed a defense for and no plan phase did. Otherwise a note, listed individually.
4. **PR body vs. reality** — check the PR body's factual claims (files touched, tests added, counts, "no behavior change") against the actual diff. A body claim the diff contradicts is a **finding**, and it is **blocking**: it must be corrected before the PR is endorsed as ready. Never endorse readiness on the strength of the body alone.
5. **Verification Matrix realisation** — for each `PHPUnit` / `API-test` / `E2E` / `Visual-regression` row, confirm the declared path exists in the diff; a declared automated path absent from the diff is **blocking**. For each `Truly-manual` row, state whether the PR *claims* it was performed. You cannot verify **who** performed it — no artifact this skill reads establishes that — so report the claim as a claim, and never upgrade an unperformed row to performed. An unperformed manual row is a **note**, not a blocker: the human-signoff hold in Phase 7.1 is what holds it.
6. **Conflict-resolution audit** — for every path on 6b's conflict list, re-read the resolution against the three stages **as captured during runtime Phase 3**, in the run notes written at `_rebase-and-conflicts.md` step 2e.5. Do not try to re-read the git index stages (`:1:` / `:2:` / `:3:`) here — the rebase committed in Phase 3 and pushed in Phase 4.3, so those stages no longer exist and `git show :1:<path>` errors with *path is not in the index*. If the rebase was clean and the list is empty, this check **passes** — say so and move on. For a counted document, confirm rows are additive and roll-up totals were recomputed. **Blocking** when a resolution dropped a side's content, took `--ours`/`--theirs` wholesale, or left a roll-up total inconsistent with its rows — this is code no structured review has ever seen (see 6c(b)).

**6e. Verdict shape.** Emit exactly one of:

- **`READY`** — every check above says "matches", with the evidence named.
- **`READY WITH NOTES`** — findings exist but none matched a blocking clause in 6d; list each note.
- **`NOT READY`** — at least one finding matched a blocking clause in 6d; each one must name the clause it matched and the concrete next action.

A green CI plus a green Phase 5.0 conformance run is **not** sufficient for `READY` on its own — that combination is exactly the state this skill exists to look past.
