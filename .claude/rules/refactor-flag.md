---
description: Refactor PRs that touch ibl5/classes/** without an accompanying test change are blocked by bin/refactor-flag (workflow refactor-flag.yml).
paths: "ibl5/classes/**"
last_verified: 2026-05-28
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
- Whitespace-only diffs above the 30-line threshold (rare; should be handled
  by reformatting in a separate commit).

The bypass reason must be at least 20 characters. "trivial" or "no behavior"
will be rejected.
