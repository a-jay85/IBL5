---
description: Agent model tiering rules — match sub-agent model to task difficulty
last_verified: 2026-04-23
---

# Agent Tiering

When spawning sub-agents or writing plans that will spawn them, always tier by the reasoning the task actually needs — never default everything to Opus.

## Core Principle

The dividing line between Haiku and Sonnet is **synthesis**. Haiku reliably runs commands, matches patterns against checklists, and formats structured output. Sonnet is needed when the agent must judge whether two things are semantically related — connecting a past commit to the current change's collision zone, deciding whether a docstring's intent matches renamed code, or tracing how data flows across multiple files.

Haiku's specific failure mode: it produces confident, well-structured "all clean" results while missing issues that require connecting dots across files. A scoring filter catches Haiku's false positives but cannot recover its misses.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, pattern-matching against named checklists, grep-and-format, mechanical lookups. The task can be answered by running commands and reporting results without judging relevance. |
| **Sonnet** | `model: "sonnet"` | Tasks requiring synthesis: "is this finding relevant to the current change?", cross-file traces, semantic compliance checks, rename sweeps needing judgment about call sites. |
| **Opus** | self (no delegation) | Planning, novel reasoning, FK ordering, rule authoring, ADR writing, interpreting ambiguous test failures, final code review, diff-triage. Never delegate understanding. |

## Prompt Style by Tier

Prompts targeting Haiku must compensate for its tendency to stop exploring after finding "enough":

**Haiku prompts — be explicit:**
- Lead with a concrete grep/find command or search strategy
- Say "list EVERY match" or "do NOT skip files" when exhaustiveness matters
- Pre-resolve directory paths (absolute, not relative)
- Request structured output (table, numbered list) — not narrative
- For checklist tasks: "check EACH pattern and cite file:line if present, or state not found"
- Never ask Haiku to judge relevance, trace multi-hop flows, or decide whether a past event relates to the current context

**Sonnet prompts — current style is fine:**
- Open-ended exploration and "figure it out" delegation
- Multi-file synthesis and connection-drawing
- Ambiguous queries where the first grep might miss

## Explore Agents

Choose the tier per prompt — do not default all Explore agents to one tier:

| Tier | Use for Explore | Examples |
|------|-----------------|---------|
| **Haiku** | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "which files import WaiverService", "list all test files for DepthChart", "does column X exist in migration Y" |
| **Sonnet** | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page display", "how does module A interact with B", "what patterns does this module use" |

**Decision heuristic:** if the prompt asks the agent to notice connections, judge relevance, or trace data flow — use Sonnet. If the prompt can be answered by running a grep and formatting the output — use Haiku.

## Post-Plan Agents

See `/post-plan` SKILL.md for authoritative model assignments. Summary:

**Haiku** — pattern-match or command-report tasks:
- Phase 5B Agent 4 (Previous PRs) — mechanical `gh search/view` lookup
- Phase 5C Security agents (SQLi, CSRF, Auth) — explicit vulnerable/secure pattern tables
- Phase 5D Scoring agent — numeric rubric application
- Phase 6 Agent 1 (PHPUnit+PHPStan) — runs commands, reports output
- Phase 6 Agent 2 (E2E Playwright) — runs commands, reports output

**Sonnet** — synthesis-dependent tasks:
- Phase 5B Agent 1 (Architectural fitness) — judges R/S/V fit, dependency direction
- Phase 5B Agent 2 (Bug detection) — connects schema types to PHP comparisons
- Phase 5B Agent 3 (Git history) — must judge whether a past commit's context overlaps the current change
- Phase 5B Agent 5 (Code comments) — semantic compliance with docstring intent
- Phase 5B Agent 6 (Database performance) — interprets query behavior in context
- Phase 7 (Manual testing review) — category judgment

## In Plans

Explicitly label which implementation phases go to Sonnet / Haiku / self. The tiering decision belongs in the plan, not deferred to execution time.

### Mechanical recipe agents

When a plan phase writes out every action as literal commands (`git mv`, explicit find/replace mappings, `git rm`, config line swaps), the executing agent is Haiku. The prompt already contains the recipe — the agent executes it. Sonnet is only needed when the prompt asks the agent to decide *what* to do, not just *how* to do it.

**Haiku:** `git mv` file renames with explicit source→target, namespace find/replace from a provided mapping, `git rm` + config updates, running test/lint commands
**Sonnet:** call-site sweeps where the agent must judge whether a match is a column vs. table name, test-writing, code authoring, debugging failures

### Bulk-sweep pattern

- Migration authoring, PHPStan rules, ADRs → Opus (self).
- Per-module PHP call-site sweeps that require judgment (e.g., distinguishing column refs from table refs in backtick-quoted SQL) → Sonnet.
- Per-module sweeps with an explicit old→new mapping and no ambiguity → Haiku.
- Running tests, migrations, schema verification → Haiku.
- Interpreting failing tests, deciding when to update baselines → Opus (self).
