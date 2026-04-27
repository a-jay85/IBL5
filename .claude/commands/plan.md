---
description: "Plan an implementation task with mandatory test classification. Wraps the built-in plan mode with the verification matrix rule injected so subagents can't skip it."
last_verified: 2026-04-26
---

# /plan — Implementation Planning with Verification Matrix

You are planning an implementation task. The user's request follows this skill's instructions as `$ARGUMENTS`.

**Do NOT write or edit any code files.** This skill produces a plan only. The single output is the plan file.

## Step 1: Verification rule

`plan-verification.md` is already in your context (always-loaded rule — no `paths:` field). Use its full content as `$VERIFICATION_RULE` for injection into the Plan agent prompt in Step 3. Do not re-read the file. Do not summarize or paraphrase the rule.

## Step 2: Explore the codebase

Launch up to 3 **Explore agents** (Sonnet for cross-module traces, Haiku for file/grep lookups) to understand the task. Follow the agent-tiering rules in `.claude/rules/agent-tiering.md`.

Provide each agent with:
- The user's task description (`$ARGUMENTS`)
- Specific areas to investigate (files, modules, patterns)
- A response cap (under 200 lines)

Collect: file paths, existing patterns, dependencies, blast radius, existing test coverage for affected code.

## Step 3: Design the plan

The Plan agent auto-loads CLAUDE.md, all always-loaded rules (agent-tiering, core-coding, plan-verification, etc.), and user memory. Do NOT re-inject any of these into the prompt — only supply what the agent cannot get on its own.

Launch a **single Plan agent** (`model: "opus"`) with a prompt containing ALL of these:

1. **Task description** from `$ARGUMENTS`
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

If validation fails on any gate, fix the matrix yourself rather than re-running the Plan agent.

## Step 5: Write the plan file

Derive a kebab-case slug from the task description (max 50 chars, lowercase, alphanumeric and hyphens only).

```bash
PLAN_PATH="$HOME/.claude/plans/<slug>.md"
```

If a plan file already exists at that path, create a new one with a numeric suffix rather than overwriting.

Write the validated plan (with corrected matrix if Step 4 required fixes) to the plan file.

## Step 6: Report

Tell the user:
- The plan file path
- A one-line matrix summary (e.g., "12 items: 7 PHPUnit, 3 E2E, 2 CLI-executable, 0 truly-manual")
- Whether the plan is ready for implementation or has open questions

Do NOT call `EnterPlanMode` or `ExitPlanMode`. This skill replaces the built-in plan mode workflow for implementation tasks.
