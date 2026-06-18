---
description: "Plan an implementation task: enforces a verification matrix, directs code reuse, flags security surfaces, and requires negative-path tests so plans drive clean, secure, well-tested implementations."
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
last_verified: 2026-06-18

---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request follows this skill's instructions as `$ARGUMENTS`.

**Do NOT write or edit any code files.** This skill produces a plan only. The output is one plan file per PR.

**One plan = one PR.** A single plan file must be implementable and mergeable as exactly one pull request. If the work naturally spans multiple PRs (independent concerns, separate review surfaces, a refactor that should land before the feature that uses it, or a change too large to review in one sitting), do NOT bundle them. Split the work into PR-sized units and produce a separate, fully self-contained plan — its own implementation steps and its own Verification Matrix — for each one. The user should never have to ask for this split.

## Step 1: Verification rule

Read `.claude/commands/_plan-verification.md` and use its full content as `$VERIFICATION_RULE` for injection into the Plan agent prompt in Step 3. Do not summarize or paraphrase the rule.

## Step 2: Orient on the codebase

**Prefer direct tool calls over Explore agents.** Most orientation can be done without spawning an agent:

1. Read `.claude/rules/codebase-map.md` to identify affected modules and their file locations
2. Run targeted `grep`/`find` via Bash for specific symbols, callers, or file paths
3. Read key files directly (migrations, interfaces, existing tests)

**Only spawn an Explore agent when** direct lookups leave unanswered questions. Tier per `.claude/rules/agent-tiering.md`:

- Single-module change → 0 agents (direct tools suffice) or 1 Haiku for enumeration
- Spans 2+ modules → up to 2 agents (Sonnet for cross-module traces, Haiku for file/grep lookups)
- Never spawn 3 agents

Provide each agent a single concrete question, pre-resolved paths, and a response cap (under 150 lines).

Collect: file paths, existing patterns, dependencies, blast radius, existing test coverage for affected code, **the specific existing helpers/services/repositories the implementation should reuse** (name the exact methods — e.g. `SalaryCapRepository::getTeamTotalSalary()` — so the plan directs reuse instead of leaving the impl agent to rediscover them), and **which security surfaces the change touches** (SQL queries, POST/form endpoints, auth/authz-gated routes, user-facing output rendering). If none of these surfaces are touched, record that explicitly.

## Step 2.5: Scope into PRs

Using the blast radius from Step 2, decide how many PRs the work requires. Default to **one** — most tasks are a single PR. Split into multiple only when a boundary is real:

- Independent concerns that can land and be reviewed separately
- A refactor/extraction that should merge before the feature that depends on it (stacked PRs)
- A change large enough that one reviewer cannot reasonably review it in a single sitting
- Distinct migrations or schema changes that each warrant their own rollback boundary

If the work is **one PR**, proceed to Step 3 once.

If the work is **multiple PRs**, list the PR-sized units in dependency order (what must merge first), then run Steps 3–5 **once per unit** — each producing its own plan file. Plans for stacked PRs should note their base branch in the implementation steps (`bin/wt-new --base <branch>`). Do not collapse the units back into one plan to "save effort" — the split is the deliverable.

## Step 3: Design the plan

Run this step once per PR-sized unit identified in Step 2.5. Each run plans exactly one PR.

The Plan agent auto-loads CLAUDE.md, all always-loaded rules (agent-tiering, core-coding, etc.), and user memory. Do NOT re-inject any of these into the prompt — only supply what the agent cannot get on its own.

Launch a **single Plan agent** (`subagent_type: "plan-architect"` — its definition carries `model: opus` and `effort: xhigh`; do NOT pass an inline `model` override) with a prompt containing ALL of these:

1. **Task description** from `$ARGUMENTS` — when the work was split in Step 2.5, scope this to the single PR being planned and state which PR it is and what it depends on
2. **Exploration results** from Step 2 — file paths, code traces, existing patterns, test coverage findings
3. **The full `$VERIFICATION_RULE`** from Step 1, prefixed with: `MANDATORY — you must follow this rule exactly:`
4. **Agent-tiering guidance for plan phases** — the "In Plans / Mechanical recipe agents / Bulk-sweep pattern" block (in *Agent-tiering guidance to inject* below), so the Plan agent labels each implementation phase's tier (Sonnet / Haiku / self).

The Plan agent MUST produce:
- Implementation steps with tests woven inline (pre-impl before their step, post-impl after)
- A full Verification Matrix in the exact format specified by `$VERIFICATION_RULE`
- File paths for every test to be written or modified
- A **Reuse** note in each implementation step that should call existing code: name the exact helper/service/repository method to use (from Step 2 findings) so the impl agent reuses rather than reinvents. Omit only when the step genuinely introduces new infrastructure.
- An **exact edit anchor** for every step that modifies an existing file: quote the unique surrounding snippet (the exact line(s) the edit lands on or next to) so the impl agent's first `Edit` matches unambiguously. This is a **correctness / disambiguation** aid — it secures a first-try Edit match and avoids a failed-edit→re-read retry. It is **not** a token optimization and must not be presented as one: the impl agent already greps-then-slices and never reads a whole file to locate an edit, so anchors reduce ambiguity, not tokens.
- For every behavior-changing step, at least one **negative-path, boundary, or failure-case** matrix row — not only the happy path (e.g. "rejects over-cap trade", "returns null for unknown player", "empty roster"). Happy-path-only coverage is insufficient.
- When the plan emits a `## Critical Files` section, **mark every entry that will NOT be changed** (references, templates, files read for context) with an explicit reference marker — use `` `path` (reference) `` or `` `path` (read-only reference) ``. post-plan's Phase 5.0 file-conformance check treats every Critical File as a **must-appear** change target *by default* and blocks auto-merge if it never lands in the diff — it exempts an entry **only** when the annotation carries a reference marker (`reference`/`read-only`/`verify`/`template`/etc.). A bare path OR a path you annotate with a change-*description* (e.g. `` `path` — add the foo helper ``) is still checked, so describing your change-targets is safe; only the reference marker exempts. Mark the non-changed entries and the gate stays false-positive-free.

Conditionally — include a section **only when it applies**; never emit an empty header:
- **Manual UI/UX check** (only when the plan introduces new or redesigned user-visible UI/UX — see `_plan-verification.md` § Forced manual-verification trigger): add one **Truly-manual** matrix row for the subjective look-and-feel + flow judgment (phrase it as a question of taste — no `verify`/`check that`/`confirm`/`ensure`, which `bin/check-plan` gate 3 rejects), do NOT emit the "All verification is automated" line, and set `auto_postplan: false` in the line-1 frontmatter (Step 4 gate 14d).
- **Approach** (non-trivial changes only): one short paragraph naming the chosen design and the main alternative rejected, with the reason. Skip for trivial single-file edits.
- **Security** (only when Step 2 flagged a touched surface): for each surface, an implementation step encoding the defense AND a matching matrix row —
  - SQL → prepared statement / `bind_param` (mind native-type binding); row asserts the query is parameterized.
  - POST/form endpoint → `CsrfGuard` token validation (share one raw token across forms when a page has ≥10, per `MAX_TOKENS=10`); E2E or API-test row asserts a missing/invalid token is rejected.
  - Auth/authz-gated route → guard present on the state-changing endpoint; row asserts an unauthorized request is refused.
  - Output rendering → escaped output (enforced by `RequireEscapedOutputRule`); note it so the impl agent doesn't fight the PHPStan rule.
  XSS and input validation are deterministically enforced by PHPStan custom rules — note which apply, do not write redundant manual checks.

#### Agent-tiering guidance to inject (item 4 above)

This guidance was relocated from `agent-tiering.md` (it is plan-authoring-only). Inject it verbatim into the Plan-agent prompt:

> **In Plans.** Explicitly label which implementation phases go to Sonnet / Haiku / self. The tiering decision belongs in the plan, not deferred to execution time.
>
> **Mechanical recipe agents.** When a plan phase writes out every action as literal commands (`git mv`, explicit find/replace mappings, `git rm`, config line swaps), the executing agent is Haiku. The prompt already contains the recipe — the agent executes it. Sonnet is only needed when the prompt asks the agent to decide *what* to do, not just *how* to do it.
> - **Haiku:** `git mv` file renames with explicit source→target, namespace find/replace from a provided mapping, `git rm` + config updates, multi-step recipe execution
> - **Sonnet:** call-site sweeps where the agent must judge whether a match is a column vs. table name, test-writing, code authoring, debugging failures
>
> **Bulk-sweep pattern.**
> - Migration authoring, PHPStan rules, ADRs → Opus (self).
> - Per-module PHP call-site sweeps that require judgment (e.g., distinguishing column refs from table refs in backtick-quoted SQL) → Sonnet.
> - Per-module sweeps with an explicit old→new mapping and no ambiguity → Haiku.
> - Running tests, migrations, schema verification → direct Bash (short output); Haiku only if multi-step or output is unpredictably large.
> - Interpreting failing tests, deciding when to update baselines → Opus (self).

#### Delegation packets for verbose phases

For a phase that is **genuinely verbose or parallelizable** — a multi-step run→inspect→fix→regen loop, or a bulk sweep of roughly **three or more** file-edits — emit a self-contained **delegation packet** the impl agent hands to a single sub-agent. Delegate the **whole phase loop including its own verify/regen/fixup** (not just the edits): the sub-agent's tool output then accumulates in *its* context and returns as one summary, keeping the orchestrator's per-turn context flat. The win is **context localization** (the orchestrator stops re-reading a growing transcript every turn) — not a flat cost-percentage. Reserve packets for phases whose moved work clearly exceeds a sub-agent's fixed startup (~15K tokens); a packet for one tiny edit costs more than it saves, so keep small phases inline.

Format each packet as a fenced block within the plan:

````
### Delegate — <phase name>
- **Tier:** Haiku | Sonnet  (per the agent-tiering guidance above)
- **Scope:** which files, what change
- **Recipe:** the exact commands / edits to run
- **Self-verify:** the command the sub-agent runs *before returning* (e.g. `composer run analyse`, expected test count, green-green) — the packet owns its own verification
- **Report back:** a one-line summary only
````

## Step 4: Validate the matrix

After receiving the Plan agent's output, check these gates yourself — do NOT delegate validation.

**The deterministic gates are scripted, not hand-run.** `bin/check-plan` (invoked in Step 5, once the plan is on disk) mechanically enforces the false-positive-free subset: gate 1 (matrix exists), gate 3 (no false manuals), the `DECIDE`/`TBD`/`subject to validation`/`subject to review` tokens of gate 7, gate 8 (decision-trigger ADR — flags a declared new trigger-surface file lacking an ADR step or `no-adr:` marker), **and** reuse-target existence (a PHP `Class::method` named in a **Reuse** note whose class exists in `ibl5/` but whose method is absent — a likely typo). Do **not** hand-scan for those; fix whatever the script reports. The gates below are the ones that need judgment a script cannot do:

1. *(scripted — see above)*
2. **No unclassified items** — every row's test type is a real classification. *Not scripted on purpose:* the type column is open-ended in practice (`Go-archive-diagnostic`, `Documented (domain rule)`, `read-before-cut` are legitimate), so a closed-set check would false-positive — judge membership yourself.
3. *(scripted — see above)*
4. **Tests woven inline** — pre-impl tests appear before their implementation step, not collected in a bottom appendix
5. **Production comparison classified correctly** — any "compare against production" or "match iblhoops.net" row must be Visual-regression, not Truly-manual
6. **Test file paths present** — every PHPUnit/API-test/E2E/Visual-regression row names a concrete test file path, not just a category
7. **No unresolved decisions** — the literal tokens are scripted (see above). You still hand-resolve an unresolved **`(or `** fork (e.g. "STAY (or move)") — `bin/check-plan` skips that token because the corpus showed it is overwhelmingly a benign aside (`≤5 (or 0 ideally)`, `(or extend existing)`), and telling a real fork from an aside needs reading the alternative. Resolve any genuine fork in-place; the nightly agent cannot make judgment calls.
8. *(scripted — `bin/check-plan` gate `[8]`)* **Decision-trigger pre-classified** — gate `[8]` flags any declared NEW file matching a `bin/adr-check` trigger surface (the pattern table lives in `_plan-verification.md` § Decision-trigger pre-classification — the single source of truth; do not duplicate it) that lacks a resolution. When it fires, do **not** merely "add an ADR step": pre-name the ADR slug and pre-fill the ADR's Context and Decision text directly into the plan body, so the spec carries the ADR draft. The conservative flags (any new `bin/` script; a new migration only when the plan text mentions `DROP`; a `composer.json` `require`/`require-dev` add) cannot read LOC/content at plan time, so they over-include slightly — clear a false flag with a `no-adr:` marker when no real decision is introduced.
9. **Negative-path coverage** — every behavior-changing step has at least one matrix row asserting a failure, boundary, or rejection case, not only happy-path. If a step has only happy-path rows, add the missing negative-path row.
10. **Hot-file extraction** — if any step adds > 100 LOC to a file `bin/check-hot-files` lists as hot (> 500 LOC under `classes/`), the plan must either propose an extraction step or carry an inline justification (per `_plan-verification.md` § Hot-file thresholds). If neither is present, add one.
11. **Refactor characterization** — if any step under `ibl5/classes/**` carries a refactor signal (file rename, method signature change, visibility narrowing, class removal, or > 30-line deletion per `refactor-flag.md`), the matrix must include a pre-impl characterization row for the affected code. If missing, add it.
12. **Security surface resolved** — if Step 2 flagged a touched security surface, the plan contains a Security section with a defense step and matching matrix row for each. If a flagged surface has no resolution, add it.
13. **impl_model criterion** — if the plan declares `impl_model: sonnet` frontmatter (see Step 5), scan the Verification Matrix; if ANY row is classified `Truly-manual`, strip the marker so the plan runs at the Opus default. Sonnet may drive a plan only when every behavior-changing step has an objectively machine-checkable row that fails on a wrong edit.
14. **auto-fire risk criterion** — by default an implementation session auto-fires `/post-plan` the moment it verifies complete (no human eyeball before the PR opens and auto-merge arms). Decide yourself — do NOT delegate — whether this plan is risky enough to want that eyeball, and if so declare `auto_postplan: false` (see Step 5). Disable auto-fire when **any** hold: (a) the Verification Matrix carries a `Truly-manual` (or otherwise subjective) row — post-plan's machine gates can't validate it; (b) Step 2 flagged a touched security surface; (c) the plan is a high-blast-radius data/schema change — a destructive migration (DROP/backfill/data mutation), a column-rename sweep, or an FK-ordering migration; (d) the plan introduces **new or redesigned user-visible UI/UX** — the forced manual-verification trigger in `_plan-verification.md` (new/restyled CSS component, new rendered page/module, new nav entry/indicator/badge, or a new multi-step user flow). A plan that trips (d) must BOTH carry the forced Truly-manual look-and-feel/flow row AND set `auto_postplan: false` — set the marker **directly here**, do not route it through (a)'s row-presence: the user asked for *both* (not auto-merged AND manual testing), so coupling them would let a dropped row silently re-arm auto-merge. Otherwise omit the marker (default = auto-fire). This composes with gate 13: a `Truly-manual` row both strips `impl_model: sonnet` **and** sets `auto_postplan: false`.

If validation fails on any gate, fix the matrix yourself rather than re-running the Plan agent.

## Step 5: Write the plan file

Derive a kebab-case slug from the task description (max 50 chars, lowercase, alphanumeric and hyphens only).

```bash
PLAN_PATH="$HOME/.claude/plans/<slug>.md"
```

If a plan file already exists at that path, create a new one with a numeric suffix rather than overwriting.

Write the validated plan (with corrected matrix if Step 4 required fixes) to the plan file. When the work was split into multiple PRs, give each plan a distinct slug (e.g. `<base-slug>-1-<unit>`, `<base-slug>-2-<unit>`) so they sort in dependency order, and write one file per unit.

Then run the mechanical linter on each plan file you wrote and fix anything it reports, in-place, until it exits clean:

```bash
bin/check-plan "$PLAN_PATH"
```

It enforces the deterministic gates from Step 4 (matrix present, no false manuals, no `DECIDE`/`TBD`/`subject to …` tokens, no unresolved decision-trigger surface, reuse targets resolve). A non-zero exit prints each violation prefixed by its gate (`[1]`/`[3]`/`[7]`/`[8]`/`[R]`); resolve each and re-run. Do not leave a plan written until `bin/check-plan` passes.

### Declaring the implementation model (optional)

The nightly implementation agent's model is selectable per-plan via a line-1 YAML frontmatter field. When **every** behavior-changing step has at least one Verification-Matrix row that is objectively machine-checkable and fails on a wrong edit (PHPStan green, PHPUnit/CLI assertion, baseline regen, identical test count, green-green characterization), prepend this block as the **very first lines** of the plan file:

```
---
impl_model: sonnet
---
```

The implementation then runs at Sonnet (cheaper, verified-equivalent quality on uniformly-mechanical plans — parsed by `bin/lib/plan-impl-model`). Omit the marker for any plan carrying a `Truly-manual` or subjective row — absence defaults to Opus. Only the first frontmatter block is parsed, so documenting this syntax inside a plan body never mis-selects a model. Failure modes are bounded: an absent or garbled marker → Opus (safe); a wrongly-applied `sonnet` marker → the plan's objective matrix goes red under Sonnet → caught by CI / post-plan.

### Disabling auto-fired post-plan (optional)

By default an interactive implementation session, once it verifies complete, auto-fires a detached `/post-plan` via `bin/post-plan-now --auto` — opening the PR and arming auto-merge with no human eyeball at the trigger. For a plan judged risky by Step 4 gate 14, add `auto_postplan: false` to the **same** line-1 frontmatter block so the auto-fire skips (the work waits for a reviewed, manual `bin/post-plan-now`):

```
---
impl_model: sonnet
auto_postplan: false
---
```

Either field may appear alone; the block above shows both (a mechanical column-rename sweep, e.g., can be Sonnet-eligible **and** high-blast-radius). Absence of `auto_postplan` defaults to auto-fire. Only the line-1 block is parsed (`bin/post-plan-now`), so a body that documents the syntax can't opt a plan out. Failure modes are bounded: absent/garbled → auto-fire (post-plan's own gates — code review, security audit, CI-green-required, headless golden-snapshot block — still apply); a wrongly-applied `false` → the plan just waits for a manual ship.

## Step 6: Report

Tell the user:
- The plan file path — **all of them** when the work was split into multiple PRs
- A one-line matrix summary per plan (e.g., "12 items: 7 PHPUnit, 3 E2E, 2 CLI-executable, 0 truly-manual")
- Whether any security surface was flagged and how each is defended (or "no security surface touched")
- Whether post-plan auto-fires on completion (default) or is held for review (`auto_postplan: false`, per Step 4 gate 14) — and why, if held
- For a multi-PR split: the PR sequence and dependency order (which lands first, what each stacks on)
- Whether each plan is ready for implementation or has open questions
