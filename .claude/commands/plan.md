---
description: "Plan an implementation task: enforces a verification matrix, directs code reuse, flags security surfaces, and requires negative-path tests so plans drive clean, secure, well-tested implementations."
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
last_verified: 2026-06-19

---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request follows this skill's instructions as `$ARGUMENTS`.

**Do NOT write or edit any code files.** This skill produces a plan only. The output is one plan file per PR.

**One plan = one PR.** A single plan file must be implementable and mergeable as exactly one pull request. If the work naturally spans multiple PRs (independent concerns, separate review surfaces, a refactor that should land before the feature that uses it, or a change too large to review in one sitting), do NOT bundle them. Split the work into PR-sized units and produce a separate, fully self-contained plan — its own implementation steps and its own Verification Matrix — for each one. The user should never have to ask for this split.

**Maximize nightly autonomy.** A plan is most valuable when nightly can implement *and merge* it with no human in the loop. Two levers turn a would-be-supervised plan autonomous — apply them by default instead of reaching for `auto_merge: false`:

1. **Pin, don't supervise.** Uncovered code is not a reason to hold the merge — it's a reason to add a **Phase-1 characterization pin** (Step 4 gate 11). A behavior-preserving refactor with a pin is green-green; the pin *is* the coverage.
2. **Decide at plan-time, not merge-time.** A single discrete design fork doesn't have to wait for a human at merge — surface it to the user *now* (Step 3.5) and record the answer in the plan; nightly then executes a fully-specified decision.

Fall back to `auto_merge: false` only when the judgment is **irreducible**: *distributed* across the implementation (per-site security verdicts, per-test correctness on untested code) or *data-blocked* (needs production data unreachable from CI). "A decision exists" is not a hold reason; "the decision can't be made once, up-front" is. The flag holds only the **merge** — post-plan still runs and opens the PR for review either way.

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

**Run this step inline — never delegate `/plan` itself.** The orchestrating session owns Steps 1–5 directly and spawns exactly **one** `plan-architect` per PR-sized unit. Do NOT hand the whole `/plan` invocation to a `general-purpose`/`claude` sub-agent (or fan it out across several), and do NOT have any such agent fire `/plan` on your behalf. Those agent types carry `Tools: *` — they *can* spawn further agents, so delegating `/plan` to them produces a `general-purpose → plan-architect` nest, exactly the multi-level `plan-architect` tree the flat-fan-out rule forbids (`agent-tiering.md` § Nested Sub-Agents). `plan-architect`/`Plan`/`Explore` cannot cause this themselves — they lack the `Agent` tool — so the only way the nest appears is an orchestrator delegating `/plan` outward. Keep planning one level deep: this session → one `plan-architect`.

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
- **Manual UI/UX check** (only when the plan introduces new or redesigned user-visible UI/UX — see `_plan-verification.md` § Forced manual-verification trigger): add one **Truly-manual** matrix row for the subjective look-and-feel + flow judgment (phrase it as a question of taste — no `verify`/`check that`/`confirm`/`ensure`, which `bin/check-plan` gate 3 rejects), do NOT emit the "All verification is automated" line, and set `auto_merge: false` in the line-1 frontmatter (Step 4 gate 14d).
- **Design decisions** (only when the design has a genuine fork): list each fork and classify it — **self-resolved** (conventional seam; state the choice + reason), **needs-user-input** (a single discrete choice the codebase can't reveal — preserve-vs-fix a known latent bug, ban-rule scope, module/admin-split boundary), or **irreducible** (distributed per-site/per-test judgment, or data-blocked). Phrase each `needs-user-input` fork as one crisp question with 2–4 concrete options, for the orchestrator to surface in Step 3.5.
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

## Step 3.5: Front-load design decisions

The Plan agent runs in a sub-context and **cannot ask the user**. For each `needs-user-input` fork it flagged in its **Design decisions** section, you (the orchestrator) surface it now with `AskUserQuestion` — one question, 2–4 concrete options, recommendation first; use the `preview` field to show a proposed module layout or code shape for structural choices. Record each answer + a one-line rationale into the plan's **Approach** section as a fixed constraint, then patch the affected implementation steps so the decision is fully specified.

A recorded decision is **no longer a fork**: it does not trip Step 4 gate 7 (unresolved decision) and does not, by itself, force `auto_merge: false` (gate 14) — the human judgment already happened at plan-time.

Do **not** ask when: the fork is conventional (let the Plan agent's self-resolution stand), the judgment is `irreducible` (distributed per-site/per-test, or data-blocked — that legitimately keeps the plan supervised), or asking is ceremony. `AskUserQuestion` is for forks where the answer actually changes the implementation.

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
11. **Refactor characterization** — if any step under `ibl5/classes/**` carries a refactor signal (file rename, method signature change, visibility narrowing, class removal, or > 30-line deletion per `refactor-flag.md`), the matrix must include a pre-impl characterization row for the affected code. If missing, add it. A correct **Phase-1** characterization pin makes a behavior-preserving refactor green-green — the pin *is* the coverage, so "this code was untested" is not on its own grounds for `auto_merge: false` (gate 14).
12. **Security surface resolved** — if Step 2 flagged a touched security surface, the plan contains a Security section with a defense step and matching matrix row for each. If a flagged surface has no resolution, add it.
13. **impl_model criterion** — if the plan declares `impl_model: sonnet` frontmatter (see Step 5), scan the Verification Matrix; if ANY row is classified `Truly-manual`, strip the marker so the plan runs at the Opus default. Sonnet may drive a plan only when every behavior-changing step has an objectively machine-checkable row that fails on a wrong edit.
14. **auto-merge hold criterion** — post-plan **always** runs and opens the PR (with code review, security audit, and CI) the moment an implementation session verifies complete; what you decide here is only whether **auto-merge arms** or the PR waits for a human to merge. Decide yourself — do NOT delegate — whether this plan wants a human at merge, and if so declare `auto_merge: false` (see Step 5; `/post-plan` Phase 6.5 condition (7) reads it and refuses to arm). Hold the merge when **any** hold: (a) the Verification Matrix carries a `Truly-manual` (or otherwise subjective) row — post-plan's machine gates can't validate it; (b) Step 2 flagged a touched security surface; (c) the plan is a high-blast-radius data/schema change — a destructive migration (DROP/backfill/data mutation), a column-rename sweep, or an FK-ordering migration; (d) the plan introduces **new or redesigned user-visible UI/UX** — the forced manual-verification trigger in `_plan-verification.md` (new/restyled CSS component, new rendered page/module, new nav entry/indicator/badge, or a new multi-step user flow). Set `auto_merge: false` directly when any of these hold — it is the single authoritative hold, so you do not need to keep it in sync with the matrix: Phase 6.5 condition (1) *independently* holds a UI/UX plan (its Truly-manual row lands in the PR's Manual-Testing section, which blocks arming), so neither lever re-arms if the other is dropped. This composes with gate 13: a `Truly-manual` row both strips `impl_model: sonnet` **and** sets `auto_merge: false`. A genuine design fork is **not** a hold trigger once it has been front-loaded and recorded via Step 3.5 — the judgment already happened at plan-time; hold only when the judgment is irreducible (distributed per-site/per-test, or data-blocked). Omitting the flag leaves the PR eligible to auto-merge, still subject to every Phase-6.5 condition — including the PR-time safety verdict (9) on the realized diff and the `feat:` floor (8).

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

### Holding auto-merge (optional)

An interactive implementation session, once it verifies complete, always auto-fires a detached `/post-plan` via `bin/post-plan-now --auto` — opening the PR and running code review, security audit, and CI. Post-plan is **never held**. What you control is whether **auto-merge arms**: for a plan judged by Step 4 gate 14 to want a human at merge, add `auto_merge: false` to the **same** line-1 frontmatter block. Post-plan still opens the PR and reviews it; `/post-plan` Phase 6.5 condition (7) reads the flag and refuses to arm auto-merge, leaving the PR open for a human to merge:

```
---
impl_model: sonnet
auto_merge: false
---
```

Either field may appear alone; the block above shows both (a mechanical column-rename sweep, e.g., can be Sonnet-eligible **and** want a human at merge). Absence of `auto_merge` leaves the PR eligible to auto-merge (still subject to every other Phase-6.5 condition). Only the line-1 block is parsed (Phase 6.5), so a body that documents the syntax can't opt a plan out. Failure modes are bounded: absent/garbled → eligible to arm (post-plan's own gates — code review, security audit, CI-green-required, the `feat:` floor, the PR-time safety verdict, and the headless golden-snapshot block — still apply); a wrongly-applied `false` → the PR just waits for a manual merge.

## Step 6: Report

Tell the user:
- The plan file path — **all of them** when the work was split into multiple PRs
- A one-line matrix summary per plan (e.g., "12 items: 7 PHPUnit, 3 E2E, 2 CLI-executable, 0 truly-manual")
- Whether any security surface was flagged and how each is defended (or "no security surface touched")
- Whether the PR is eligible to auto-merge on green CI (default) or is held for a human merge (`auto_merge: false`, per Step 4 gate 14) — and why, if held
- For a multi-PR split: the PR sequence and dependency order (which lands first, what each stacks on)
- Whether each plan is ready for implementation or has open questions
