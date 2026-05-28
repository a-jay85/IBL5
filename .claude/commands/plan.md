---
description: "Plan an implementation task with mandatory test classification. Wraps the built-in plan mode with the verification matrix rule injected so subagents can't skip it."
disallowed-tools:
  - EnterPlanMode
  - ExitPlanMode
last_verified: 2026-05-28

---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request follows this skill's instructions as `$ARGUMENTS`.

**Do NOT write or edit any code files.** This skill produces a plan only. The output is one plan file per PR.

**One plan = one PR.** A single plan file must be implementable and mergeable as exactly one pull request. If the work naturally spans multiple PRs (independent concerns, separate review surfaces, a refactor that should land before the feature that uses it, or a change too large to review in one sitting), do NOT bundle them. Split the work into PR-sized units and produce a separate, fully self-contained plan — its own implementation steps and its own Verification Matrix — for each one. The user should never have to ask for this split.

## Step 1: Verification rule

`plan-verification.md` is already in your context (always-loaded rule — no `paths:` field). Use its full content as `$VERIFICATION_RULE` for injection into the Plan agent prompt in Step 3. Do not re-read the file. Do not summarize or paraphrase the rule.

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

Collect: file paths, existing patterns, dependencies, blast radius, existing test coverage for affected code.

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

The Plan agent auto-loads CLAUDE.md, all always-loaded rules (agent-tiering, core-coding, plan-verification, etc.), and user memory. Do NOT re-inject any of these into the prompt — only supply what the agent cannot get on its own.

Launch a **single Plan agent** (`model: "opus"`) with a prompt containing ALL of these:

1. **Task description** from `$ARGUMENTS` — when the work was split in Step 2.5, scope this to the single PR being planned and state which PR it is and what it depends on
2. **Exploration results** from Step 2 — file paths, code traces, existing patterns, test coverage findings
3. **The full `$VERIFICATION_RULE`** from Step 1, prefixed with: `MANDATORY — you must follow this rule exactly:`

The Plan agent MUST produce:
- Implementation steps with tests woven inline (pre-impl before their step, post-impl after)
- A full Verification Matrix in the exact format specified by `$VERIFICATION_RULE`
- File paths for every test to be written or modified

## Step 4: Validate the matrix

After receiving the Plan agent's output, check these gates yourself — do NOT delegate validation:

1. **Matrix exists** — the plan contains a table with columns: #, What to verify, Test type, Timing, Test file / location
2. **No unclassified items** — every row has a test type from the allowed set (PHPUnit, API-test, E2E, Visual-regression, CLI-executable, Truly-manual)
3. **No false manuals** — scan for "verify", "check that", "confirm", "ensure" in any row classified as Truly-manual. These verbs indicate an automatable assertion — reclassify the row
4. **Tests woven inline** — pre-impl tests appear before their implementation step, not collected in a bottom appendix
5. **Production comparison classified correctly** — any "compare against production" or "match iblhoops.net" row must be Visual-regression, not Truly-manual
6. **Test file paths present** — every PHPUnit/API-test/E2E/Visual-regression row names a concrete test file path, not just a category
7. **No unresolved decisions** — scan all table rows (lines matching `^\s*\|`) for these tokens (case-insensitive):
   - `DECIDE` (whole word)
   - `TBD` (whole word)
   - `(or ` (literal — indicates unresolved alternative, e.g., "STAY (or move)")
   - `subject to validation`
   - `subject to review`
   If any match is found, resolve the decision in-place before saving the plan. The nightly agent cannot make judgment calls — every table cell must contain a concrete action, not a deferred question.
8. **Decision-trigger pre-classified** — scan implementation phases for file additions matching `bin/adr-check` trigger patterns (listed in `plan-verification.md` § Decision-trigger pre-classification). If any trigger fires, verify the plan includes a resolution step (ADR or bypass marker). If missing, add the appropriate resolution step and update the verification matrix.

If validation fails on any gate, fix the matrix yourself rather than re-running the Plan agent.

## Step 5: Write the plan file

Derive a kebab-case slug from the task description (max 50 chars, lowercase, alphanumeric and hyphens only).

```bash
PLAN_PATH="$HOME/.claude/plans/<slug>.md"
```

If a plan file already exists at that path, create a new one with a numeric suffix rather than overwriting.

Write the validated plan (with corrected matrix if Step 4 required fixes) to the plan file. When the work was split into multiple PRs, give each plan a distinct slug (e.g. `<base-slug>-1-<unit>`, `<base-slug>-2-<unit>`) so they sort in dependency order, and write one file per unit.

## Step 6: Report

Tell the user:
- The plan file path — **all of them** when the work was split into multiple PRs
- A one-line matrix summary per plan (e.g., "12 items: 7 PHPUnit, 3 E2E, 2 CLI-executable, 0 truly-manual")
- For a multi-PR split: the PR sequence and dependency order (which lands first, what each stacks on)
- Whether each plan is ready for implementation or has open questions
