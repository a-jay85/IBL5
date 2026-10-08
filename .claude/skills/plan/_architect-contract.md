---
description: The plan-architect's full output contract, Read on demand from Step 3 of plan/SKILL.md — the MUST-produce list, the conditional-section catalogue, the agent-tiering labels to inject, and the delegation-packet format.
last_verified: 2026-10-08
---

The `plan-architect` Reads this file when Step 3 of `plan/SKILL.md` points to it, so this contract lands in the architect's own sub-context and never enters the orchestrator's. Mirrors the on-demand convention of the `.claude/review-shared/_*.md` reference files.

## Read budget: read this once, read nothing else

Every byte you load competes with the codebase you must reason about. Three self-enforced bounds:

- **This file is the whole contract.** Read it once. It is your first action of the run, and you never Read it again.
- **Never Read a `*-detail.md` companion.** Every `_architect-contract-detail.md §` and `_plan-verification-detail.md §` pointer below is an **edit-time** address for maintainers. The operative rule is stated in full on the pointer's own line.
- **Never Read `$DRAFT`.** You authored every section in it. Your only permitted draft access is the `grep -c` / `awk` phase count in the fenced block under **Phase count is binding** below. That block returns a count. `Read`, `cat`, `sed -n`, `head`, and `tail` over `$DRAFT` are all out.

For the codebase, the orchestrator's findings are authoritative, confirmations cap at ~2-3, and no file is Read twice.

## What the plan MUST produce

- Implementation steps with tests woven inline (pre-impl before their step, post-impl after)
- A full Verification Matrix in the exact format specified by `$VERIFICATION_RULE`
- File paths for every test to be written or modified
- A **Reuse** note in each implementation step that should call existing code: name the exact helper/service/repository method to use (from Step 2 findings) so the impl agent reuses rather than reinvents. Omit only when the step genuinely introduces new infrastructure.
- An **exact edit anchor** for every step that modifies an existing file: quote the unique surrounding snippet (the exact line(s) the edit lands on or next to) so the impl agent's first `Edit` matches unambiguously. `_architect-contract-detail.md` § Why edit anchors are quoted, not summarized. <!-- slop-ok -->
- **DB literals cite a column type.** Each one in a recipe carries `literal-check: <literal-or-range> -> <table>.<column> <sqltype> (per ibl5/docs/schema/current-schema.sql)` on its line. The literal must fit `<sqltype>` from the dump; gate `[L]` checks it. `_architect-contract-detail.md` § Column-bound literal citations. <!-- slop-ok -->
- For every behavior-changing step, at least one **negative-path, boundary, or failure-case** matrix row, such as "rejects over-cap trade", "returns null for unknown player", or "empty roster". Happy-path-only coverage is insufficient.
- **The mutation statement extends to the whole matrix, not just the negative-path rows above:** every behavioral row names the production-code mutation that makes it fail (e.g. "delete the `--model` arm ⇒ this assertion fails"), as a clause in its **How** cell. `_architect-contract-detail.md` § Why negative-path rows name the mutation they catch. `_architect-contract-detail.md` § Mutation-statement counter-examples. <!-- slop-ok -->
- **One-time-check rows.** A `CLI-executable` row may end its "What to verify" cell with `(one-time-check: <reason>)` only when the check is inherently a single plan-time run: a manual corpus diff over local transcripts, a counterfactual mutation of the working tree, or one command's printed output. Never for behavior a regression test could pin, or a row the diff should realise. The cell still names what was run and what it printed. `bin/check-plan` gate `[Y]` caps tagged rows at one third; past that, write tests. `_architect-contract-detail.md` § One-time-check tag shape. <!-- slop-ok -->
- **Unrealisable rows.** Out-of-repo and post-PR rows carry the tag at write time. A `CLI-executable` row whose location cell runs only `~/`, `$HOME`, or `/tmp` paths (a scratch script under `~/claude-plans/_scripts/`, a memory file, a hook) or queries the open PR with `gh run`, `gh pr`, or `gh api` has no footprint a diff can carry, so Step 6.7 would stop on its tokens. End its What-to-verify cell with `(one-time-check: <reason>)` as you write it; `bin/check-plan` gate `[Z]` fails the plan otherwise. A command that also runs a repo `bin/` script, a repo test, `composer`, `php`, `npx`, `docker`, or `curl` is in-repo and stays untagged. Tokens that sit only inside a `Mutation:` clause (from the label to its `⇒`, sentence end, or cell end) are excluded by `bin/lib/plan-matrix-assertions` and need no tag. A token also named outside the clause is still checked.
- In `## Critical Files`, **mark every entry that will NOT be changed** with a reference marker, e.g. `` `path` (reference) ``. Post-plan Phase 5.0 (`bin/lib/critical-files.sh`) treats each entry as a **must-appear** change target and blocks auto-merge if it misses the diff, **only** exempting an annotation with a **parenthesized** group holding a canonical token (case-insensitive). Canonical markers: `(reference)`, `(read-only)`, `(read-only reference)`, `(verify)`, `(verification)`, `(template)`, `(no-edit)`, `(no-change)`, `(unchanged)`, `(context)`, `(conditional)`. All tokens match as **whole words**. Use `(conditional)` for entries that *may or may not* appear in the diff; `conditional` must open the parenthesized group, followed immediately by `)` or a separator. Use **list form** (`- \`path\` (annotation)`); gate `[F]` rejects a table. Mark a VR `-snapshots` entry `(conditional — only if VR baselines change)`, or `(vr-baseline-change: <what changes>)` when the plan deliberately regenerates or deletes baselines; gate `[VR]` rejects the unmarked form. `_architect-contract-detail.md` § Critical Files parsing — counter-examples. <!-- slop-ok -->
- **Existing tests the spec forces to edit.** When a phase changes a signature, return shape, state field, log string, or fixture that an existing test asserts on, that test file lands in the diff whether you planned it or not. Grep the test tree for each symbol your phases change. List every existing test file you expect to edit in `## Critical Files`, and run it in a Verification Matrix row. Post-plan's scope check (`bin/lib/plan-scope-conformance`) exempts an added test path but flags an undeclared edit to an existing one by design, and the fidelity review reports it as scope creep. Mark the entry `(conditional)` when the spec leaves it open whether the test changes.
- **Non-diff phases.** A phase changing nothing in the tree (e.g. closing an issue) carries the body line `**No diff:** <reason>` (≥ 15 chars, line start, outside fences and code spans); gate `[ND]` rejects others. Phases citing only non-repo tokens (`~/...`, a URL) need none. `_architect-contract-detail.md` § Non-diff phase marker shape.
- **Size budget**: target **< 500 lines and < 12 numbered Step/Phase headings**; gate `[C]` rejects a plan at or over either. Signal a split to the orchestrator instead of compressing correctness out. Use a `context-budget: <one-line justification>` marker only when fenced recipe/reference material inflates the size while the phase count stays small.
- **Phase count is binding after turn 1.** Title each phase in the turn-1 outline `Phase <N>: <title>` (or `Step <N>: <title>`, one form throughout) and repeat it **verbatim** as the `## ` heading in turns 2..N. The orchestrator persists the outline as an HTML comment whose numbered items **fix the plan's phase count**; never add a `## Phase <N>` past it. New work goes in the last phase's body as `**Scope note:** Phase <N> also covers <X>`. Enforced via `bin/check-plan --draft` (gate `[C]`). The per-turn self-check, run in the **same Bash call as the append**:
  ```bash
  cat >> "$DRAFT" <<'EOF_SECTION'
  ## <the exact title>
  <body>
  EOF_SECTION
  SCAF=$(awk '/<!-- PLAN OUTLINE/,/-->/' "$DRAFT" | grep -cE '^[[:space:]]*[0-9]+\.[[:space:]]+(Phase|Step)[[:space:]]*[0-9]')
  BODY=$(grep -cE '^##[[:space:]]+(Phase|Step)[[:space:]]*[0-9]' "$DRAFT")
  if [ "$BODY" -gt "$SCAF" ] && [ "$SCAF" -gt 0 ]; then
    echo "PHASE_DRIFT: $BODY body phases but scaffold declares $SCAF — halt, do not write further phase sections"
  fi
  echo "[phases: $BODY/$SCAF]"
  ```
  Append the result to your thin ack — `section "<title>" appended [phases: $BODY/$SCAF]`. When `PHASE_DRIFT` fired, write no further phase section.
- **Gate-8-safe creation phrasing**: gate `[8]` flags a *new* trigger-surface file (`bin/<x>` (example), `.claude/rules/*.md`, `.github/workflows/*.ya?ml`, `ibl5/phpstan-rules/*.php`, a migration when the plan mentions `DROP`, a `composer.json` require add) lacking an ADR step or `no-adr:` marker. Tokens resolving to an **existing** file are skipped, so say **extend / modify / update** for those and reserve creation verbs (*create*, *new file*, *add a new*, *introduce*, *scaffold*) for new files. A created trigger-surface file ships a pre-filled ADR draft (slug, Context, Decision) or a `no-adr: <reason>` marker.
- **Any path a plan cites that does *not* exist on `master` yet** must sit in one of three safe positions: **(a)** a `## Critical Files` / phase / Verification-Matrix row carrying an explicit creation cue (`— NEW`, `Create`, `Write`, `moved from`, or `→`); **(b)** inside a fenced code block; or **(c)** under a non-dependency heading (`## Out of Scope`, `## Non-Goals`, `## Rejected`, `## Alternatives`). **A `>` blockquote is not a fourth safe position:** quoted prose is scanned exactly like body prose. `_architect-contract-detail.md` § Why blockquotes are not a staleness-guard exemption. Appending ` (example)` right after a closing backtick (`` `path` (example) ``) exempts that one token, for a rejected or intentionally-absent path in prose. The cue must sit on the token's **own physical line**, adjacent to its backtick span; the guard matches line by line.
- **Number placeholders.** Write a new migration as `ibl5/migrations/NNN_slug.sql` (example) and a new ADR as `ibl5/docs/decisions/NNNN-slug.md` (example). Parallel branches can claim the same number, so the builder uses whatever `bin/next-migration` or `bin/next-adr` prints and continues; never tell it to stop, skip, or report back on a mismatch. `bin/check-plan` gate `[A]` fails a creation-cue line that hardcodes a migration or ADR number absent from `origin/master`. Citing an existing migration or ADR by its real number passes. The placeholder on a creation-cue line also resolves gate `[8]`.
- **When this plan is one unit of a multi-PR split** — open the plan with a single pointer line to the shared-context artifact and **reference** it for anything shared; do NOT restate the shared blast radius or cross-unit decisions. Your unit's own Critical Files, phases, Verification Matrix, and new decisions still belong in the plan. If the artifact carries a `## Program acceptance` section:
  - **Non-final unit** — say in Approach: "This unit does NOT make seam `<X>` newly exercisable — the acceptance test is landed as `it.skip(...)` / `markTestSkipped(...)` in Phase N." Do **not** invent a Verification Matrix row claiming to exercise a skipped test.
  - **Final unit** — emit a `CLI-executable` Verification Matrix row that runs the acceptance test file **by its exact path**, and un-skip the test in the phase that wires the last seam.
  - **Read the seam's address from the serving code, never from the artifact.** Confirm URL prefix / file path / CLI flag against `ibl5/.htaccess` + `ibl5/router.php` (HTTP route), the plist template (launchd job), or the script's argument parsing (CLI flag). `_architect-contract-detail.md` § The seam-address story.
- **Never emit a frontmatter or plan-metadata *section*.** The line-1 YAML block (`impl_model`, `auto_merge`) is the **orchestrator's** to write at Step 5. Gate `[H]` forces `## Automouse Hold Justification` and **fails** it when it asks the human to run a check (write a `**Decision:**` line plus a `**Discharged by matrix rows:**` pointer, § Conditional sections); `bin/lib/plan-model-consistency` derives `impl_model` from the Truly-manual row count. A body section restating these values is **inert** and ships a satisfied build directive into every impl run's context. <!-- slop-ok -->
- **Never run `bin/check-plan` on your draft.** It is the **orchestrator's** Step-5 gate, and gates `[13]`, `[H]`, `[T]`, `[S]` all read line-1 frontmatter that a draft structurally cannot carry. Self-verify `[C]`, `[F]`, `[8]`, and `[T]` (tier lines) by reading what you wrote. `_architect-contract-detail.md` § Why bin/check-plan is off-limits to the architect.
- **The one self-check you DO run: the per-turn phase count.** It is the `awk`/`grep` block above, **not** `bin/check-plan`. `SCAF` is 0 without an outline comment (the guard is a no-op); `BODY < SCAF` mid-run is normal and only `BODY > SCAF` is drift. <!-- slop-ok -->
- **A pre-prod exercise path for every deploy-dependent behavior**: behavior that manifests only once *deployed* (a file CI ships on merge, an applied migration, a registered daemon/cron, a new `.github/workflows/` job, a live-service call). Each needs a matrix row runnable on the worktree Docker stack, CI, or the prod-clone `.github/workflows/deploy-rehearsal.yml` rehearsal (`_plan-verification-detail.md` § Pre-prod exercise paths — worked catalogue). Silence and stub/mock/fake-only coverage do not count. A surviving exception requires `<!-- pre-prod-exception: <slice + why> -->` plus `## Pre-prod Exception Justification` (gate `[P]`). `_architect-contract-detail.md` § Pre-prod exercise path — slice elaboration. <!-- slop-ok -->

## Conditional sections

Conditionally — include a section **only when it applies**; never emit an empty header:
- **Backlog issues** (only when Step 2 recorded an `a-jay85/IBL5-backlog` issue this PR resolves or advances): emit a `## Backlog issues` section with one bullet per issue. Write `- closes a-jay85/IBL5-backlog#N — <what this PR resolves>` for an issue the PR fully resolves and `- refs a-jay85/IBL5-backlog#N — <what it advances>` for partial work. Gate `[U]` rejects any other shape (lowercase tag, full repo path, description). Post-plan turns each `closes` bullet into a PR-body `Closes` line, so GitHub closes the issue on merge; emit no step or CLI close call for it. `_architect-contract-detail.md` § Backlog — GitHub Issue bookkeeping. <!-- slop-ok -->
- **Post-merge mechanization** (only when the follow-up genuinely *cannot* run until the PR merges): prefer folding into the PR. Add a **merge-triggered watcher** phase only when the trigger is the merge event *itself*: write the follow-up as a small script, arm a detached launchd job. Two correctness traps: (1) resolve the PR number once from the branch, then poll by number; (2) self-teardown on either terminal state (fire on `MERGED`, unload without running on `CLOSED`). Tier the setup **Haiku (inline — one-shot script + plist write from the provided recipe, well below the ~15K bar)**. `_architect-contract-detail.md` § Post-merge — watcher setup procedure. <!-- slop-ok -->
- **Manual UI/UX check** (only when the plan introduces new or redesigned user-visible UI/UX): add one **Truly-manual** matrix row for the look-and-feel judgment, do NOT emit the "All verification is automated" line, and set `auto_merge: false` in the line-1 frontmatter (Step 4 gate 14d). The row must be **pre-merge-performable**, and its location cell must carry a **`vr:` cell** so CI screenshots it onto the PR (`bin/check-plan` gate `[Q]`, ADR-0126): `vr: label=<kebab-slug>; role=anon|regular|admin; url=<app-relative path>; anchor=<selector>`, plus zero or more `setup=<GET|POST|DELETE> test-state.php?action=…` clauses, `;`-separated (a `|` would break the matrix table). When the row cannot be shot (print CSS, an email render) write **`no-vr: <reason ≥ 15 chars>`** instead. `_architect-contract-detail.md` § Why the UI/UX hold is NOT the pre-prod gate.
- **Verification-gap mechanization** (only when a correctness property is **silent**, **integration-only**, observable-only-in-prod, or needs a human to confirm it; autonomy lever 3): the plan MUST include a phase that **builds the mechanical self-check**, naming the **invariant** and **how it fails loudly**, plus matrix rows. A verification gap is *reducible*, unlike a `Truly-manual` UI/UX judgment or an `irreducible` hold. It justifies a hold only when the plan states concretely why no check can be built.
- **Pre-prod testability mechanization** (only when the plan introduces **deploy-dependent** behavior): the plan MUST include a phase that **builds the pre-prod exercise path**, plus the matrix rows. Organize the analysis **by slice, not by artifact**:
  - **Logic slice — reducible; build it, always.** Exercise on the worktree Docker stack through a one-shot seam (`--dry-run` / `--once` / `--sim=N`).
  - **Scheduling slice — intrinsic.** launchd/cron actually firing on the prod box.
  - **Network / credential boundary — intrinsic.** The real endpoint with the real token.
  - **Exception is claimable for the scheduling / reachability slice ONLY** — logic slice is still built in the same plan. A surviving slice is **recorded**: `<!-- pre-prod-exception: <slice + why> -->` plus `## Pre-prod Exception Justification`.
  - **Stub-only coverage is not a pre-prod exercise path** — a stub proves the caller's branch handling, not the real call shape. `_architect-contract-detail.md` § Pre-prod testability — distinct from Verification-gap.
  - Tier the seam-adding step **Sonnet (delegated — recipe-backed: copy the named existing `--dry-run` seam)**.
- **Schema-safety mechanization** (only for a *reversible* schema-tightening migration that would otherwise stay held under Step 4 gate 14(c)): the plan MUST include a migration phase with all of: (1) **apply-time fail-closed guard** that aborts if any live row violates the new constraint, raising a real SQL **error** via a mode-independent idiom (`SELECT IF(...)`, not `CAST` or division-by-zero which only warn under non-strict prod `sql_mode`); (2) **forward-bound assertion** that the writer's source column cannot produce a violating value; (3) **idempotency** via `information_schema`; (4) **documented lossless rollback**; (5) **DatabaseIntegration test** running under `SET SESSION sql_mode = ''` that inserts a violating row and asserts the migration aborts, plus a conforming row asserting it succeeds. `_architect-contract-detail.md` § Schema-safety — guard mechanics. <!-- slop-ok -->
- **Security-surface mechanization** (only when Step 2 flagged a dischargeable touched surface under Step 4 gate 14(b)): the plan MUST carry a `## Security-Surface Discharge` section plus the assertion phase, with all of: (a) **`**Invariant:**`** — a *falsifiable structural* claim about the code; (b) **`**Asserted by:**`** — one `*Test.php` path in a registered `<testsuite>` directory holding a reflection assertion; (c) **`**Not covered:**`** — surfaces this discharge does NOT cover; (d) **matrix rows** for the reflection assertion including a negative-path row proving it fails when a forbidden property is introduced. `bin/check-plan` gate `[B]` enforces (a)–(d). `_architect-contract-detail.md` § Security-surface — dischargeable taxonomy. <!-- slop-ok -->
  - **Dischargeable (non-exhaustive)** — structural property: state-free extraction (collaborator holds no DB handle and no actor identity), pure-function move (no new I/O, no authorization decision), validator that never sees actor identity.
  - **Irreducible — the hold stands, and `## Automouse Hold Justification` still applies**: carries actor identity (`$loggedInTeamID`, session, current user); new POST/form endpoint; new SQL construction site; new user-facing output rendering. Ambiguity resolves to held.
  - **The taxonomy guides; the gate enforces.** A named-but-unasserted invariant discharges nothing — no prose-only path. A discharge never replaces a defense.
- **Design decisions** (only when the design has a genuine fork): list each fork and classify it — **self-resolved** (conventional seam; state the choice + reason), **needs-user-input** (a single discrete choice the codebase can't reveal — phrase as one crisp question with 2–4 concrete options, for the orchestrator to surface in Step 3.5), or **irreducible** (distributed per-site/per-test judgment, or data-blocked).
- **Approach** (non-trivial changes only): one short paragraph naming the chosen design and the main alternative rejected, with the reason. Skip for trivial single-file edits.
- **Security** (only when Step 2 flagged a touched surface): for each surface, an implementation step encoding the defense AND a matching matrix row —
  - SQL → prepared statement / `bind_param` (mind native-type binding); row asserts the query is parameterized.
  - POST/form endpoint → `CsrfGuard` token validation (share one raw token across forms when a page has ≥10, per `MAX_TOKENS=10`); E2E or API-test row asserts a missing/invalid token is rejected.
  - Auth/authz-gated route → guard present on the state-changing endpoint; row asserts an unauthorized request is refused.
  - Output rendering → escaped output (enforced by `RequireEscapedOutputRule`); note it so the impl agent doesn't fight the PHPStan rule.
  XSS and input validation are deterministically enforced by PHPStan custom rules — note which apply, do not write redundant manual checks. Encoding the defense satisfies Step 4 gate 12 but does **not** by itself release the gate-14(b) merge hold — if the surface is dischargeable, see § Security-surface mechanization above; otherwise the plan holds and says why.
- **Automouse Hold Justification** (only when the plan keeps `auto_merge: false`): the section carries the **decision**, never a **check**. Every *observable* claim a reader might expect the human to re-run is a Verification Matrix row you have not written yet — write it, cite it, and leave only the judgment behind. Emit exactly this four-part skeleton:
  ```
  ## Automouse Hold Justification

  **Category:** intrinsic — <which Step 4.5 intrinsic bullet, or `reducible-confirmed` + which gate-14/15 trigger>.

  **Decision:** <ONE plain yes/no sentence, at most 40 words, for the person pressing merge.
  Example: "Merge if you're OK with about 7% more PRs being held.">

  **Discharged by matrix rows:** <row numbers asserting the observable claims>

  <One line of why the judgment is irreducible (intrinsic) or why no mechanical check is
  buildable (reducible-confirmed).>
  ```
  Gate `[H]` fails an ask-shaped sentence outside the `**Decision:**` block (exempt from that line to the next blank line). `**Category:**` and `**Discharged by matrix rows:**` are contract the gate does not check; emit them anyway. Passing its narrow pattern check over `bin/lib/hold-check-patterns.txt` is only the floor.
  **Decision wording.** Only the Decision reaches the PR body. Do not open it with "The human accepts that ...". Gate `[H]` runs it through `bin/check-prose`, caps it at 40 words, and fails that opener.

## Self-apply the Automouse Hold Challenge

**Self-apply the Automouse Hold Challenge.** Before you call a decision `irreducible`, recommend a hold, or let a verification gap / reversible schema tightening default to supervised, ask: *"What would I add to this plan to make it safe for automouse to merge unattended?"* If the honest answer is a buildable mechanical check (a lever-3 self-check, the Schema-safety guard, or the Security-surface assertion), **add that phase and its matrix rows** instead of leaning toward a hold.

Carry a hold forward **only** when it is *intrinsic*: subjective UI/UX taste; an **irreducible** security surface (not a dischargeable shape under Security-surface mechanization above, or one whose invariant cannot be machine-asserted); an irreversibly-destructive or design-data-blocked change; a self-gating change to the merge-gate machinery; or a stated reason *why no mechanical check is buildable*. Never pressure an intrinsic hold into arming (a safety regression); name its category and stop. Step 4.5 re-runs this challenge; any justification an agent could settle from the repo and diff is an unwritten matrix row. `_architect-contract-detail.md` § Why the hold challenge is self-applied.

## Mid-design exploration

Mid-design exploration is governed by your agent def; budget arithmetic, the advisory bound rationale, and enforcement details: `_architect-contract-detail.md` § Mid-design exploration.

## Agent-tiering guidance to inject

Apply this guidance verbatim when tiering phases:

> **In Plans.** Explicitly label which implementation phases go to Sonnet / Haiku / self. A **below-run-model** tier (below `impl_model`) must be declared on a single parseable **canonical `**Tier:**` line** — the same field the `### Delegate` packet uses — and resolved per the Binding rule above (either a `### Delegate` packet, or an `(inline — <reason>)` marker on that `**Tier:**` line). Same- or above-run-model phases may write `**Tier:** self` or omit the line entirely. This is the exact form `bin/check-plan` gate `[T]` keys on. The gate ranks the **first** model name after `**Tier:**` on the line, so lead with the tier token; a reason clause may then name other tiers freely (`**Tier:** self — too entangled for Sonnet` reads as self, not Sonnet).
>
> **Mechanical recipe agents.** When a plan phase writes out every action as literal commands (`git mv`, explicit find/replace mappings, `git rm`, config line swaps), the executing agent is Haiku. Sonnet is only needed when the prompt asks the agent to decide *what* to do, not just *how* to do it.
> - **Haiku:** `git mv` file renames with explicit source→target, namespace find/replace from a provided mapping, `git rm` + config updates, multi-step recipe execution
> - **Haiku context cap:** a packet reading >~100K tokens per request goes to Sonnet (Haiku's 5x price cliff, `agent-tiering-detail.md`).
> - **Sonnet:** call-site sweeps where the agent must judge whether a match is a column vs. table name, test-writing, code authoring, debugging failures
>
> **Bulk-sweep pattern.**
> - Migration authoring, PHPStan rules, ADRs → Opus (self).
> - Per-module PHP call-site sweeps that require judgment (e.g., distinguishing column refs from table refs in backtick-quoted SQL) → Sonnet.
> - Per-module sweeps with an explicit old→new mapping and no ambiguity → Haiku.
> - Running tests, migrations, schema verification → direct Bash (short output); Haiku only if multi-step or output is unpredictably large.
> - Interpreting failing tests, deciding when to update baselines → Opus (self).

## Delegation packets for verbose phases

For a phase that is **genuinely verbose or parallelizable** — a multi-step run→inspect→fix→regen loop, or a bulk sweep of roughly **three or more** file-edits — emit a self-contained **delegation packet** the impl agent hands to one sub-agent. Reserve packets for moved work clearly over a sub-agent's fixed startup (~15K tokens); keep small phases inline. `_architect-contract-detail.md` § Delegation packets — delegation economics. <!-- slop-ok -->

**Binding rule — every below-run-model phase declares packet-or-inline explicitly.** For any implementation phase assigned a tier **below the plan's whole-run model** (`impl_model`, resolved by `bin/lib/plan-impl-model`), the packet-vs-inline choice MUST be made **explicit**, one of exactly two ways:

- **(a) Delegate** — the moved work clears the ~15K bar → emit a full `### Delegate` packet (the packet's own `**Tier:**` line is its tier declaration). `bin/automouse/prompt-impl` binds it at runtime.
- **(b) Inline** — the work is genuinely tiny (a one- or two-edit phase, moved work below the ~15K bar) → the phase carries a `**Tier:** <Haiku|Sonnet> (inline — <one-clause reason>)` line. The `(inline — …)` marker is what makes the packetless-ness provably intentional.

A below-run-model phase with **neither** a packet nor an `(inline — …)` marker is **forbidden**; `bin/check-plan` rejects it mechanically (gate `[T]`). Phases **at or above** the whole-run model need no marker.

Format each packet as a fenced block within the plan:

````
### Delegate — <phase name>
- **Tier:** Haiku | Sonnet  (per the agent-tiering guidance above)
- **Scope:** which files, what change
- **Rules:** (optional) `.claude/rules/` files this packet's sub-agent must Read first — name any **path-scoped** rule the phase depends on; always-on rules load automatically and need no entry
- **Recipe:** the exact commands / edits to run
- **Assertions:** every property this phase's Verification Matrix rows assert, pasted verbatim — one backticked test-method name or matrix-row property per list item
- **Self-verify:** the command the sub-agent runs *before returning*, naming **every** token listed under `Assertions:` and **executing** each one (e.g. `vendor/bin/phpunit --filter 'testA|testB' path/to/Test.php`, expected test count, green-green) — the packet owns its own verification. A `grep` for the token names satisfies gate `[E]` mechanically and verifies nothing; see the paragraph below the block
- **Report back:** a one-line summary only
````

The `Assertions:` field copies every property the phase's matrix rows assert; a sub-agent sees only the packet, so a property left out never ships. Gate `[E]` requires the field, and requires every backticked token in it to appear literally in the packet's `**Self-verify:**`. Name the test methods and run them; a source grep for the property name stays green through the regression it pins (§ mutation statement above), so it does not count. A phase with no matrix rows of its own (e.g. a rename verified only by `composer run analyse`) carries a line opening with `no-assertions: <reason>` in the packet instead. Gate `[E]` ignores a mid-sentence mention or empty reason.

**When to fill the `Rules:` field.** Never list an always-on rule (no `paths:` frontmatter key). Those load verbatim into every sub-agent. List a **path-scoped** rule only when the delegate's work depends on it and the packet's own file edits would not match its globs. Omit the field entirely otherwise. `_architect-contract-detail.md` § Rules field: worked examples.
