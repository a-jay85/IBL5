---
description: Before adding a new hook, CI gate, workflow, or bin/ script, first ask whether an existing one can be extended; quarterly cull retires dead meta-tooling.
last_verified: 2026-07-09
---

# Meta-Tooling Bar

As of 2026-07-09 (UTC), the repo carries: 113 files in `bin/` (30 `test-*`, 18 `check-*`), 27 CI workflows in `.github/workflows/`, 20 hooks in `~/.claude/hooks/`, and 28 always-loaded rules in `.claude/rules/`. Roughly a quarter of `bin/` (`test-*`) exists to maintain the other three-quarters — every gate is itself code that needs upkeep and can carry its own bugs. Evidence that gates need upkeep (not that they are broken now): the persist-gate append blindspot (fixed 2026-07-04) and the check-docs rebase author-date bug (fixed PR #1262). This rule caps growth two ways: an **extend-before-add bar** checked at creation time, and a **quarterly cull** that retires dead tooling.

Recount at any time:

```bash
ls bin/ | wc -l                         # bin total
ls bin/ | grep -c '^test-'              # bin test-*
ls bin/ | grep -c '^check-'             # bin check-*
ls .github/workflows/*.yml | wc -l      # CI workflows
ls ~/.claude/hooks/*.sh | wc -l         # hooks
ls .claude/rules/*.md | wc -l           # always-loaded rules
```

## The extend-before-add bar

Before adding a new hook, CI gate, workflow, or `bin/` script, first ask whether an existing one can be extended; extend by default.

**Add-new only when ALL hold:**
- **No host to extend** — no existing hook/gate/workflow/script owns this surface, and bolting a branch onto one would strain its single responsibility.
- **Distinct trigger** — it fires on a genuinely different event/surface than any existing gate, not a variant foldable in as a flag.
- **Earns its upkeep** — the surface it guards is live and recurring; the gate pays back the maintenance (and its own future bugs) it will cost.
- **No cheaper alternative** — the same protection can't come from an always-loaded rule doc (zero `test-*` overhead), a doc note, or extending a config.

An always-loaded rule doc (like this one) carries ZERO `test-*`/gate overhead and is the cheap enforcement lever — prefer a rule over a hook when a documented norm plus review suffices.

## Quarterly cull

A recurring review that retires meta-tooling no longer earning its keep. **First due 2026-10-06; RE-ARMS +1 quarter after each run** — after each cull, bump the dated memory reminder's `⏰` +1 quarter.

The teeth are documented hand-run greps, not a maintained script (a `bin/cull-audit` (example) would itself violate the bar). Run these at each cull:

- **Orphaned `test-*`** — a `test-*` in `bin/` whose target no longer exists: list `bin/`'s `test-*`, derive each target, confirm it still exists.
- **Unreferenced gate** — a `check-*` referenced by no `.github/workflows/*.yml`: grep the workflows dir for each `check-*` name; zero hits ⇒ candidate.
- **Buggy gate/hook** — a gate/hook with bugs recorded in memory: is it still earning its keep, or has its cost outgrown its value?
- **Dead rule / silent hook** — a rule superseded by another, or a hook that never fires: confirm against recent logs/PRs.

Each hit is a **CANDIDATE**, judgment-gated — the cull PROPOSES retirements; a human confirms.

**Trap to avoid:** the bar caps gate/hook/workflow proliferation; `test-*` is the SYMPTOM (a quarter of `bin/` tests the rest), not the enemy — do NOT mandate "every new gate needs a matching `test-*`", because that ADDS the very thing being capped. Test coverage for a gate is a separate per-gate call, never a blanket requirement this rule imposes.

After each cull, bump the reminder `⏰` +1 quarter.

## Calibration

Surface the extend-before-add question only when the change ADDS a governance mechanism (hook, gate, workflow, `bin/` script); skip the ritual for editing an existing one or for a plain rule-doc/prose change (zero-overhead).

**Headless:** no-op under headless/automouse — no human to weigh the extend-vs-add call, and automouse runs only pre-vetted plans; governs interactive tooling-authoring only.
