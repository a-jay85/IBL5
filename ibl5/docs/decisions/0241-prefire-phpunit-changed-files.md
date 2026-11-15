---
description: bin/lib/prefire-verify runs only the PHPUnit tests mapped to the branch's changed files, falls back to the full suite on any shared input or mapping error, and skips when nothing PHPUnit-relevant changed. Partially supersedes ADR-0227. A 2026-11-14 addendum leaves database-group tests to CI and fails a subset run that executes no tests.
last_verified: 2026-11-14
---

# ADR-0241: Run only the PHPUnit tests tied to the branch's changed files in prefire-verify

**Status:** Accepted
**Date:** 2026-11-11
**Deciders:** ajaynicolas
**Supersedes:** ADR-0227, the phpunit stage's full-suite scope only. ADR-0237 stands.

## Context

ADR-0227 made the prefire phpunit stage run the full default suite before every fire. On 2026-11-11 one full local run took 3m10s and 323 MB for 11,811 tests. Prefire wrote 26 phpunit logs in 24 hours, about 80 minutes of Mac CPU a day. The owner chose to narrow the stage to the branch's own tests over auto-stopping idle Docker stacks.

## Decision

`stage_phpunit` in `bin/lib/prefire-verify` picks one of three modes and logs it as `phpunit mode: subset (N test files)`, `phpunit mode: full (<reason>)` or `phpunit mode: skipped (<reason>)`.

- The diff runs from the merge-base of the base ref to the working tree. It covers committed, staged, unstaged and untracked files. Renames split into a delete and an add.
- The base ref is `--base` when given, else `git config branch.<name>.iblBase`, else `origin/master`.
- `ibl5/classes/<Rel>.php` maps to `tests/<Rel>Test.php` and `tests/DatabaseIntegration/<Rel>Test.php` when they exist. `ibl5/phpstan-rules/<Rel>.php` maps the same way under `tests/PHPStanRules/`.
- A word-match `git grep` for the class's short name over `ibl5/tests/**/*Test.php` adds every test that names the class. It excludes `tests/PHPStanRules/Fixtures`.
- A changed `ibl5/tests/**/*Test.php` runs itself when it still exists. A deleted test is never passed to PHPUnit.
- A changed path outside `ibl5/` that is not markdown adds every test that names it by its repo-relative path. Cli tests such as `ibl5/tests/Cli/AdrCheckCliTest.php` run `bin/adr-check` that way.
- These shared inputs run the full suite: `ibl5/phpunit.xml`, `ibl5/phpunit.xml.dist` (example), `ibl5/tests/bootstrap.php`, `ibl5/composer.json`, `ibl5/composer.lock`, any PHPStan rule fixture, and any other file under `ibl5/tests/` that is not a `*Test.php` file (base classes such as `WideUnitTestCase.php`, helpers, fixtures, golden snapshots).
- A deleted source class, a PHP change that maps to no test, any other path under `ibl5/` (modules, migrations, CSS), and every error building the diff run the full suite.
- The stage skips with exit 0 only when every changed path is outside `ibl5/`, is markdown, or is an e2e spec under `ibl5/tests/e2e/`, and no test names a changed path outside `ibl5/`.
- `PREFIRE_PHPUNIT_FULL=1` forces the full suite. `0`, empty and unset select automatically. Any other value exits 2.
- A full-suite pass record keeps its old filename and satisfies a later subset run on the same tree. A subset record is keyed by a hash of its test list and never satisfies a full run. A skip writes no record.

`bin/test-prefire-verify` pins each rule with a `test_phpunit_*` case, and the plan's Executable Mutations break each fallback to prove the cases catch it.

## Alternatives Considered

- **Auto-stop idle Docker stacks instead.** Rejected because the owner chose subset-by-changed-files on 2026-11-11.
- **Path mirroring only, with no class-name grep.** Rejected because tests that exercise a class from another directory would be missed.
- **Skip on a mapping error.** Rejected because every error must fail toward the full suite.

## Consequences

- Positive: a typical fire runs a few test files in seconds instead of the 3-minute full suite.
- Positive: CI's `PHPUnit Tests` job still runs the full suite on every PR, and CI's `Infection PHP (per-PR diff)` check still gates mutation score.
- Negative: a test broken outside the mapped set no longer blocks the fire. It shows up as a red CI check on the PR instead.
- Negative: transitive dependents that never name a changed class are missed locally.

## Lineage

ADR-0227 ran the full default PHPUnit suite in the prefire phpunit stage. This ADR narrows that stage to the mapped tests, with the full-suite fallbacks above. ADR-0237's removal of the local Infection stage is unchanged.

## References

- `bin/lib/prefire-verify` (`prefire_phpunit_select`, `prefire_phpunit_map_source`, `stage_phpunit`)
- `bin/test-prefire-verify`
- `bin/mutation-diff` (the merge-base and untracked-file pattern)
- `ibl5/docs/decisions/0227-prefire-verify-gate.md`
- `ibl5/docs/decisions/0237-prefire-drop-local-infection.md`
- `.claude/rules/workflow-continuity.md`

## Addendum (2026-11-14): database-group tests are left to CI

PHPUnit's default run excludes the `database` group (`ibl5/phpunit.xml`, `<groups><exclude>`). Every test file under `ibl5/tests/DatabaseIntegration/` carries that group at class level, so a subset run that targets one executes nothing. On 2026-11-14, a PHPUnit run on the single file `tests/DatabaseIntegration/ActivityTrackerRepositoryTest.php` printed `No tests executed!` and exited 0. A change whose only mapped test sat in that folder passed the stage with zero tests run.

The stage changes in two ways.

1. `prefire_phpunit_select` drops every target under `tests/DatabaseIntegration/` after it collects and deduplicates the list. Each dropped file is recorded as deferred, and the stage prints `phpunit deferred to CI: database-group test left to the CI db-integration job: <path>`. When the drop empties the list, the stage takes its existing skip path and runs no PHPUnit. CI's `db-integration` job in `.github/workflows/tests.yml` runs these tests with the database group enabled.
2. A subset run whose log holds `No tests executed!` fails with exit 1 and writes no pass record. This catches any other filter that empties a run, such as a file whose methods all carry a method-level database group. Full-suite runs keep their old behavior.

Detection keys on the path prefix and reads no file. Files outside that folder that tag single methods with `@group database` stay in the list, because their other methods still run.

The owner decided on 2026-11-14 that prefire never runs database tests locally. The stage adds no local database run or seed load. `ibl5/phpunit.xml`, the full-suite run, the exit codes, and the tree-hash pass records keep their behavior. The DB probe (`PREFIRE_DB_PROBE_CMD`) stays, because the matrix-evidence stage uses it; the phpunit stage never called it.

The skip line still reads `deferred to CI, which runs the full suite`. For a database test the per-file line names the job that runs it.
