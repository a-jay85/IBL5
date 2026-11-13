---
description: bin/lib/prefire-verify gains a pytest stage that runs only the post-plan harness test files mapped to the branch's changed files, serially, and defers every error, shared input and oversized selection to CI instead of running the whole suite.
last_verified: 2026-11-13
---

# ADR-0249: Run only the harness tests tied to the branch's changed files in prefire-verify, and defer the rest to CI

**Status:** Accepted
**Date:** 2026-11-13
**Deciders:** ajaynicolas
Related: ADR-0241 (the phpunit stage's changed-files subset; unchanged)

## Context

On 2026-11-12 an agent ran `pytest -q tests/ -x` over the post-plan harness: 8 workers for over 9 minutes, each starting real `bin/post-plan-now` scripts, load average 96. `.github/workflows/python-tests.yml` already runs the whole suite on every PR that touches its trigger paths. Before this change, the fire point ran no harness test at all.

## Decision

`bin/lib/prefire-verify` gains a `pytest` stage, third in `all`. Selection maps by name: a test file maps to itself, a harness module to the test files that import it (dotted path or `from <package> import <name>`), and a trigger script to the test files that name its basename. It runs serially from `tools/postplan-harness/` with explicit file arguments, `-p no:xdist` and `-p no:cacheprovider`. Every error path, shared input, unmapped file, and selection over 25 test files defers to CI. The stage has no whole-suite path.

## Why it differs from ADR-0241

ADR-0241's phpunit stage falls back to the full suite on a selector error. The pytest stage falls back to CI instead, because the whole harness suite is the cost this stage exists to avoid and CI runs it on the same PR.

## Alternatives Considered

- Fold the harness tests into the phpunit stage. Rejected because the two suites have different runners, prerequisites and pass records.
- A static table from source file to test file. Rejected because it goes stale each time a test gains an import.
- Fall back to the full harness suite on an error, as ADR-0241 does. Rejected because that run is the 9-minute load spike this stage exists to avoid.

## Consequences

- Positive: a typical fire runs a few harness test files serially instead of nothing, and `bin/post-plan-now` exits 9 when a mapped harness test fails.
- Positive: CI's Python tests workflow still runs the whole suite on every PR that touches its trigger paths.
- Negative: a harness break that no name match finds (for example a multi-line parenthesized import) reaches CI and never the fire point.
- Negative: the cap of 25 bounds fire-point cost. Raising it is a one-constant change.

## References

- `bin/lib/prefire-verify` (`prefire_pytest_select`, `stage_pytest`)
- `bin/test-prefire-verify`
- `ibl5/docs/decisions/0241-prefire-phpunit-changed-files.md`
- `.github/workflows/python-tests.yml`
- `.claude/rules/workflow-continuity-prefire.md`
- `.claude/rules/postplan-harness-tests.md`
