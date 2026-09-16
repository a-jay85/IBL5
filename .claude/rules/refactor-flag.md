---
description: Refactor PRs that touch ibl5/classes/** without an accompanying test change are blocked by bin/refactor-flag (the refactor-flag check in workflow pr-meta-checks.yml).
paths: "ibl5/classes/**"
last_verified: 2026-09-16
---

# Refactor PR Flag

## Triggers

`bin/refactor-flag` runs on every PR. It blocks merge when the diff contains
any of these refactor signals under `ibl5/classes/**` (excluding `Contracts/`):

- File rename (`git diff --diff-filter=R`)
- Method signature change (parameters or return type differ)
- Visibility narrowing (public → protected, public → private, protected → private)
- Class declaration removal
- Large deletion (> 30 lines in a single file)

## Resolution

The gate passes if the same PR also:

- Adds at least one new test file under `ibl5/tests/`, OR
- Modifies an existing test whose filename references the affected class
  or module (case-insensitive substring), OR
- Carries a bypass marker in the PR body:
  `<!-- no-refactor-tests: reason at least 20 characters explaining why -->`

## When to bypass

Bypass is appropriate for:

- Pure file moves with no behavior change AND existing tests still cover the
  moved code by namespace path (the test's `use` statement updates count as
  test modification, but the heuristic matches by filename — bypass when the
  test filename doesn't match).
- Renames where the test file is also renamed in the same commit (test rename
  shows as A + D, but the gate counts the A side as "test added").
- **E2E-only spec changes.** The name-match credits a modified test only when its
  filename is a case-insensitive substring of the changed PHP class
  (`TradingViewTest.php` → `TradingView`). E2E specs are named after features or pages
  (`league-control-panel.spec.ts`), so they never match a class name — a PR that changes
  a class signature and touches only E2E specs trips the gate. Add the marker proactively
  and confirm with `bin/refactor-flag --pr` before pushing.
- Whitespace-only diffs above the 30-line threshold (rare; should be handled
  by reformatting in a separate commit).

The bypass reason must be at least 20 characters. "trivial" or "no behavior"
will be rejected.

## Green-green, not red-green

**Red-green is for greenfield; a refactor is green-green.** Writing a failing test for
behavior that already works proves nothing. Classify first: new Service/Validator with a
non-obvious spec → failing test first. Refactor → write a **characterization** test that
passes *before* the change, then refactor and re-confirm it still passes. That green→green
pair is the safety proof this gate exists to demand.

For legacy code that writes to the DB, the characterization test must assert the exact
stored column values for a known input, *before* any extract-method. Skipping that is how a
free-agency extraction shipped a reverse-engineered modifier, a hardcoded random, and wrong
bind types (commit 188bd3f4c).

## Verify `bind_param` type chars position by position

After any `bind_param` type-string edit, count each position explicitly:

```bash
echo -n "iisdisidsiiidsii" | fold -w1 | cat -n
```

Swapping two chars in a 16-char string is easy and local tests will not catch it —
`MockDatabase` does not validate bind types, so the failure surfaces only in CI.

## Relocating a namespace: sweep what `*.php` greps miss

**`ibl5/phpunit-mutation.xml`.** Its testsuite `<directory>tests/<OldModule></directory>` entries are XML, so neither
PHPStan, `phpunit.xml`, nor `bin/check-docs` sweeps them. Rename or remove the entry in the
same commit that moves or deletes `ibl5/tests/<Module>/` — a stale entry failed the PHPUnit
coverage-threshold step on PR #1283 while local `composer run analyse` stayed green.

**And sweep the extension-less PHP scripts.** `ibl5/bin/check-baseline-drift`,
`check-coverage`, and `check-coverage-regression` are `#!/usr/bin/env php` with no `.php`
suffix, so every `*.php`-scoped grep misses stale FQCNs inside them — PHPStan by extension, a
plan's own Verification Matrix, a review agent's caller sweep. Same PR #1283, second CI
failure. Search extension-agnostically instead:

```bash
grep -rlE '\\OldNamespace\\' bin/ ibl5/bin/
```
