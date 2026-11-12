---
description: Read-on-demand only (no auto-attach trigger). The pre-fire checks for bin/post-plan-now: the plan matrix-assertion check, the prefire-verify stages and run order, exit codes 9 and 10, the tree-hash pass cache, and the --skip-verify bypass. workflow-continuity.md links here from § Post-Plan.
last_verified: 2026-11-12
paths: "~/.claude/hooks/post-verification-workflow.sh"
---

# Workflow Continuity: Pre-Fire Checks

Moved verbatim from `.claude/rules/workflow-continuity.md` § Post-Plan. The core file keeps the decision to fire and points here.

## Matrix check

- **Matrix check first.** With a plan, `git add -A` and run `bin/lib/plan-matrix-assertions <plan> <(git diff --cached origin/master)`. Write the test for each `UNREALISED-ASSERTION` row and rerun; if you cannot, do not fire. No plan: skip.

## Prefire verification

`bin/post-plan-now` runs `bin/lib/prefire-verify` (PHPUnit on the tests mapped to the changed files, the plan's post-impl CLI matrix rows, the plan's Executable Mutations rows) and exits 9 until all three pass on the current tree. A matrix row whose whole command is one of six whole-suite runs (`vendor/bin/phpunit`, `composer run analyse` and the four other forms listed in `bin/lib/prefire-verify`), alone or after `cd ibl5 &&`, is skipped with a `skipped, CI covers full-suite runs` line, because CI runs those suites on every push. A row with a filter, a path or a chained command still runs. Exit 9 means the gate refused the tree: fix the check it names. Exit 10 means the gate could not run (Docker or the DB is down, or a prerequisite is missing): fix the environment and rerun, and do not hunt for a failing check. CI's required check `Infection PHP (per-PR diff)` gates mutation score. Run it first with `--plan <plan> --stage matrix`, then `phpunit`, then `exec-mutations`, one foreground call each. The fire point reruns exec-mutations with larger limits. A pass is cached by tree hash. A changed file with no mapped test runs nothing locally and defers to CI, which runs the full suite on every push. That covers a migration, a script in the ibl5 bin directory, a class that no test names, a deleted source or test file, a shared base class, a PHPStan rule fixture, a shared test helper, and the PHPUnit config, bootstrap and composer files. The log prints one `phpunit deferred to CI:` line per deferred file, and a diff with only deferred files reports `phpunit mode: skipped` and writes no pass record. A selector error, such as an unresolvable base or a failed diff, runs the full suite, and `PREFIRE_PHPUNIT_FULL=1` forces it. With no plan, the matrix stage skips. A human may bypass with `--skip-verify=<reason>`; it is logged and refused under `CLAUDE_HEADLESS`.
