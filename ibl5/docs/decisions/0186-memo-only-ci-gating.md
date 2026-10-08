---
description: On pull requests the ci-memo is the only skip gate for every test and analysis job in Tests and Analysis; path filters gate only audit-php, audit-js and iblbot.
last_verified: 2026-10-08
---

# ADR-0186: Memo-only skip gating for Tests and Analysis on pull requests

**Status:** Accepted
**Date:** 2026-10-08

## Context

Tests and Analysis gated its heavy PR jobs twice: a dorny/paths-filter output (`src`, `shell`, `ibl5ts`, `workflows`, `both`) and the tree-hash memo of ADR-0131. The filter lists were hand-kept, so a PR that edited a file a test reads but the list omitted skipped that test and merged green. Between 2026-09-29 and 2026-10-07, 8 of 15 master failures of the gate came from such skips: five PHPUnit (PRs 2907, 2891, 2888, 2882, 2811) and three shell harness (PRs 2814, 2682, 2678). Each fix added paths to `src:`, and the next omission broke master again. The memo manifest `.github/ci-memo/tests.paths` already covers whole directories and is a superset of every filter, so it cannot skip a run the filter would have caught.

## Decision

On `pull_request`, a job in `.github/workflows/tests.yml` skips only on a memo hit. `test`, `harness-tests`, `db-integration`, `phpstan`, `shellcheck` and `ibl5-ts-unit` run on every memo miss. `phpunit-hygiene`, `host-mariadb-guard`, `automouse-impl-model-test` and `actionlint` run unconditionally. Path filters remain only where the filter equals the job's whole input or the result depends on an advisory database: `iblbot` (`ibl5/IBLbot/**`), `audit-php` and `audit-js`. The `src` output shrinks to what the two audits read. The tests memo key carries `--extra gating=memo-only`, which retires memos written while a filter-skipped job counted as clean. `bin/test-ci-memo` enforces this: gate-topology assertion 7 pins the allowlist of jobs that read a `changes` output to exactly those three, and case `memo-only-salt` pins the salt.

## Alternatives Considered

- **Keep adding paths to `src:` and `shell:`.** Rejected because each omission is found only after master breaks, and `bin/test-path-filters` (example) covered one load pattern of many.
- **Bump the shared memo `FORMAT_VERSION`.** Rejected because it evicts the e2e and lighthouse memos too, and only the tests scope stored unsound entries.
- **Drop every filter, including the audits.** Rejected because the audits redden on new advisories regardless of PR content, and running them on docs-only PRs spreads unrelated reds.

## Consequences

- Positive: a PR cannot merge with a test job skipped by a stale path list.
- Positive: a written tests memo now implies `test` and `harness-tests` ran green on that tree.
- Negative: a docs-only or `.claude/`-only PR runs full PHPUnit (about 4.5 minutes) and the harness suite on its first push. Re-pushes of the same tree still hit the memo.
- Negative: every existing tests memo misses once after merge.

## References

- `.github/workflows/tests.yml`
- `.github/ci-memo/tests.paths`
- `bin/ci-memo`
- `bin/test-ci-memo`
- ADR-0131 (tree-hash memoization)
- ADR-0017 (dependabot forces surviving filter outputs true)
