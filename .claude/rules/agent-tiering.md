---
description: Agent model tiering rules — match sub-agent model to task difficulty
last_verified: 2026-04-22
---

# Agent Tiering

When spawning sub-agents or writing plans that will spawn them, always tier by the reasoning the task actually needs — never default everything to Opus.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | 100% mechanical, single-command tasks: run `bin/test`, run `composer run analyse`, verify a file exists, count grep matches, apply a known find-and-replace in known files |
| **Sonnet** | `model: "sonnet"` | Mechanical sweeps needing some judgment but following a clear pattern: rename a column across call sites, update test fixtures, port a pattern between modules. All `Explore` agents default to Sonnet |
| **Opus** | self (no delegation) | Planning, novel reasoning, FK ordering, rule authoring, ADR writing, interpreting ambiguous test failures, final code review, diff-triage. Never delegate understanding |

## In Plans

Explicitly label which implementation phases go to Sonnet / Haiku / self. The tiering decision belongs in the plan, not deferred to execution time.

## Bulk-Sweep Pattern

- Migration authoring, PHPStan rules, ADRs → Opus (self).
- Per-module PHP call-site sweeps → one Sonnet agent per 5-10 related modules, run in parallel.
- Running tests, migrations, schema verification → Haiku.
- Interpreting failing tests, deciding when to update baselines → Opus (self).
