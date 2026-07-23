---
description: Ship the compiled post-plan harness in-repo under tools/postplan-harness/ with a dedicated Python CI workflow, a main-checkout path pin, and real-data dirs gitignored.
last_verified: 2026-07-23
---
# 92. Post-plan harness in-repo with dedicated Python CI

Date: 2026-07-23

## Status
Accepted

## Context
The compiled post-plan harness (`bin/post-plan-now`'s primary engine, PR #1503) was
developed as an external expedient at `~/GitHub/postplan-harness` — un-versioned, no CI,
no review trail. That isolation was never deliberate ship-isolation; it was a two-week
prototype convenience. The code is ~2K LOC, stdlib-only (zero external Python deps), and
now load-bearing for every auto-fired `/post-plan`. It needs version control, review, and
a regression gate.

## Decision
1. Move the harness into the repo at `tools/postplan-harness/`. Real-data and regenerable
   artifacts (`out/`, `fixtures/scenarios/`, `report/`, `activation/`, pyc/caches) are NOT
   committed — `out/` and `fixtures/scenarios/` hold real IBL5 diffs/PR numbers and are
   gitignored; the rest are dropped as regenerable or stale.
2. Gate the pytest suite via a NEW dedicated workflow `.github/workflows/python-tests.yml`,
   NOT a job folded into `tests.yml`. Rationale: `tests.yml` feeds the required "Tests and
   Analysis" status gate; coupling a single-developer dev-tool's Python tests into that gate
   would let harness-test flake block app PRs. A separate, path-scoped (`tools/postplan-harness/**`)
   workflow with its own concurrency group keeps the dev tool's CI decoupled from the app's
   required gate and its distinct toolchain (setup-python + pytest) out of the PHP matrix.
3. `bin/post-plan-now` references the MAIN-CHECKOUT absolute path
   (`/Users/ajaynicolas/GitHub/IBL5/tools/postplan-harness`), not a worktree-relative path.
   This preserves today's "fixed external version relative to any worktree" behavior and
   avoids a self-gating hazard: a harness PR's own worktree copy is never used to ship itself
   (the main-checkout lacks the new files pre-merge → `[ -x "$HARNESS/run" ]` false → skill
   fallback), so the change takes effect only post-merge, verified by this workflow.
4. CodeQL Python is deliberately NOT added (single-developer macOS dev tool, zero external
   deps, meta-tooling upkeep bar). Revisit only if the harness grows to touch user-facing
   data paths.

## Consequences
- Harness changes are now reviewable, versioned, and regression-gated per PR.
- The main-checkout pin is machine-specific by design; other machines fall through to the
  skill session (same as today) — the harness is a local dev optimization, not a universal
  requirement.
- Rollback stays trivial: `POST_PLAN_SKILL=1` forces the skill path; a broken in-repo harness
  self-heals via the `[ -x "$HARNESS/run" ]` fallthrough.
- The two suites named `*_live.py` are in fact hermetic (a shimmed `gh` on `PATH`, a temp
  `git init` repo) and are `--ignore`d in CI only as a conservative scope decision, not
  because they need live credentials. Broadening CI to run them is a cheap follow-up.
