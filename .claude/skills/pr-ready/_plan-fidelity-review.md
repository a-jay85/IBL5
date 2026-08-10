# /pr-ready runtime Phase 6 — plan-intent fidelity review

Purpose: the criteria and verdict shape for the semantic judgment this skill exists to produce — does the implementation do what the plan *intended*, not merely what its tests assert?

**3a. This review runs on the orchestrator. It is NEVER delegated.** Do not spawn an agent for it. Do not delegate any part of it. Do not summarise the diff via a sub-agent and review the summary. `.claude/rules/agent-tiering.md` reserves this class for the Opus column — "Never delegate understanding" — and the cost of delegating is not the delegate's raw capability but that the orchestrator loses the findings the delegate filtered out. Spawning a sub-agent for any part of this phase is a defect in the run.

**3b. Inputs.** Gather all five before judging anything:

1. The plan file read in runtime Phase 1 (`~/claude-plans/<branch>.md`).
2. The full **post-rebase** diff — `gh pr diff <N>`, or `git diff origin/master...HEAD` locally.
3. The PR body.
4. The list of conflict-resolved paths recorded in runtime Phase 3.
5. `PHASE_4B_RAN`, the boolean from runtime Phase 1's prior-review probe.

**3c. Two mandatory statements.** The review is incomplete without both, worded explicitly in the verdict:

- **(a) Whether Phase 4B structured code review ran on this PR** (from `PHASE_4B_RAN`). If it did not, say so plainly and recommend running `/pr-review <N>` before the PR is merged. `/pr-ready` deliberately does **not** substitute for structured code review — it neither defines nor references the shared review-agent definitions, and it produces no scored findings.
- **(b) That Phase 4B, when it ran, reviewed the PRE-REBASE diff.** Therefore every line produced by runtime Phase 3 conflict resolution is code no structured review has ever covered, and this fidelity review is its only coverage. **Name each conflict-resolved path** in the statement — do not summarise them as a count.

**3d. The fidelity checks.** Each produces an explicit finding or an explicit "matches" — never silence:

1. **Intent coverage** — for each of the plan's implementation phases, does a corresponding change exist in the diff? Name any phase with no diff footprint.
2. **Intent fidelity** — where a change exists, does it do what the phase *said*, or a different thing that merely satisfies the phase's tests? This is the semantic question `/post-plan` Phase 5.0 (`.claude/skills/post-plan/_phase-5-final-verification.md`) structurally cannot ask: it verifies the declared test *paths* were written, never that the behavior matches intent.
3. **Scope creep** — changes in the diff that no plan phase asked for. Flag them; do not assume they are harmless.
4. **PR body vs. reality** — check the PR body's factual claims (files touched, tests added, counts, "no behavior change") against the actual diff. A body claim the diff contradicts is a **finding**, and it must be corrected before the PR is endorsed as ready. Never endorse readiness on the strength of the body alone.
5. **Verification Matrix realisation** — for each `PHPUnit` / `API-test` / `E2E` / `Visual-regression` row, confirm the declared path exists in the diff. For each `Truly-manual` row, say whether it has been performed and by whom.
6. **Conflict-resolution audit** — re-read every path from 3b's conflict list against its three-way stages. For a counted document, confirm rows are additive and roll-up totals were recomputed.

**3e. Verdict shape.** Emit exactly one of:

- **`READY`** — every check above says "matches", with the evidence named.
- **`READY WITH NOTES`** — findings exist but none blocks a merge; list each note.
- **`NOT READY`** — at least one blocking finding; each one must name the concrete next action.

A green CI plus a green Phase 5.0 conformance run is **not** sufficient for `READY` on its own — that combination is exactly the state this skill exists to look past.
