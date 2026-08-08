---
description: Before adding a new hook, CI gate, workflow, or bin/ script, first ask whether an existing one can be extended; quarterly cull retires dead meta-tooling.
last_verified: 2026-08-08
paths:
  - "bin/**"
  - ".github/workflows/**"
  - ".claude/**"
---

# Meta-Tooling Bar

The meta-tooling surface — `bin/` scripts, CI workflows, hooks, always-loaded rules — keeps growing, and every gate is itself code that needs upkeep and can carry its own bugs. This rule caps growth two ways: an **extend-before-add bar** checked at creation time, and a **quarterly cull** that retires dead tooling. Recount the surface any time:

```bash
ls bin/ | wc -l                    # bin total
ls bin/ | grep -c '^test-'         # test-*
ls bin/ | grep -c '^check-'        # check-*
ls .github/workflows/*.yml | wc -l # workflows
ls ~/.claude/hooks/*.sh | wc -l    # hooks
ls .claude/rules/*.md | wc -l      # rules
```

## The extend-before-add bar

Before adding a new hook, CI gate, workflow, or `bin/` script, first ask whether an existing one can be extended; extend by default.

**Add-new only when ALL hold:**
- **No host to extend** — no existing hook/gate/workflow/script owns this surface, and bolting a branch onto one would strain its single responsibility.
- **Distinct trigger** — a genuinely different event/surface, not a variant foldable in as a flag.
- **Earns its upkeep** — the surface it guards is live and recurring; the gate pays back the maintenance (and its own future bugs) it will cost.
- **No cheaper alternative** — the same protection can't come from a doc note, an extended config, or an always-loaded rule doc, which carries ZERO `test-*`/gate overhead and is the cheap enforcement lever: prefer a rule over a hook when a documented norm plus review suffices.

## Quarterly cull

A recurring review that retires meta-tooling no longer earning its keep. **First due 2026-10-06; RE-ARMS +1 quarter after each run** — after each cull, bump the dated memory reminder's `⏰` +1 quarter.

The teeth are documented hand-run greps, not a maintained script (a `bin/cull-audit` (example) would itself violate the bar). Run these at each cull:

- **Orphaned `test-*`** — a `test-*` whose target no longer exists: list `bin/`'s `test-*`, derive each target, confirm it still exists.
- **Unreferenced gate** — a `check-*` referenced by no `.github/workflows/*.yml`: grep the workflows dir for each `check-*` name; zero hits ⇒ candidate.
- **Unwired `test-*`** — target still exists, but **no workflow ever runs it**: `for t in bin/test-*; do n=$(basename "$t"); grep -rqF "$n" .github/workflows/ || echo "UNWIRED $n"; done`, then check each hit for an invoking wrapper (a wrapper counts as wired). Such a test passes locally forever and protects nothing — how `bin/test-plan-now` went unrun until 2026-07-28. Disposition: **wire it or retire it**; a test that self-skips off-platform (`bin/test-automouse-single`, macOS-only) is a legitimate stay-unwired, since wiring it to a Linux runner buys a SKIP line. This is the *inverse* of the trap below — it never mandates a new `test-*`, only asks whether an existing one runs.
- **Buggy gate/hook** — bugs recorded in memory: still earning its keep, or has its cost outgrown its value?
- **Dead rule / silent hook** — a rule superseded by another, or a hook that never fires: confirm against recent logs/PRs.

Each hit is a **CANDIDATE**, judgment-gated — the cull PROPOSES retirements; a human confirms.

**Trap to avoid:** the bar caps gate/hook/workflow proliferation; `test-*` is the SYMPTOM (a quarter of `bin/` tests the rest), not the enemy — do NOT mandate "every new gate needs a matching `test-*`", because that ADDS the very thing being capped. Test coverage for a gate is a separate per-gate call, never a blanket requirement this rule imposes.

## Calibration

Surface the extend-before-add question only when the change ADDS a governance mechanism (hook, gate, workflow, `bin/` script); skip it for editing an existing one or for a plain rule-doc/prose change (zero-overhead).

**Headless:** no-op under headless/automouse — no human to weigh the extend-vs-add call; governs interactive tooling-authoring only.
