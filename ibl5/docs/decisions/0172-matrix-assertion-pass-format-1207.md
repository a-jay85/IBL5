---
description: The matrix-assertion regression harness prints every case result as `PASS <name>` or `FAIL <name>` with no colon, so one grep pattern counts all cases. Also records the stray `-E` copy of the harness that the same diff added.
last_verified: 2026-10-05
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0172: One PASS and FAIL line format in the matrix-assertion harness

**Status:** Accepted
**Date:** 2026-10-05
**Deciders:** post-plan harness (auto-draft)

## Context

`bin/test-matrix-assertion-check` is the regression harness for `bin/lib/plan-matrix-assertions`, and the `.github/workflows/tests.yml` CI job runs it. Its case-statement cases report through the `ok` and `fail` helpers, which print `PASS <name>` and `FAIL <name>`. The function-based cases added later for the Step 6.7 gate in `bin/automouse/prompt-impl` and the pre-fire command in `.claude/rules/workflow-continuity.md` (`step67-*`, `prefire-*`) wrote their own lines as `PASS: <name>` and `FAIL: <name>`. One harness therefore printed two formats. A reader or script counting results with `^PASS ` missed the function cases, and one counting `^PASS:` missed the rest.

`bin/adr-check` flagged this PR for a new `bin/` script of 50 lines or more: `bin/test-matrix-assertion-check-E` (example). That file is a byte-for-byte copy of the harness before this change. Its blob hash matches the old `bin/test-matrix-assertion-check`. The name fits the backup file that BSD `sed -i -E` writes on macOS, where `-i` takes `-E` as its backup suffix. It adds no new tool.

## Decision

Every result line in `bin/test-matrix-assertion-check` uses the `ok` and `fail` format: `PASS <name>` or `FAIL <name>`, a single space, no colon. The function-based cases keep their own `echo` calls and `return 1` control flow, and their text now matches the helpers. Detail after the case name (expected and actual output) stays on the same line. The `-E` copy is an editing artifact. It should be deleted before merge, and this ADR then records only the format decision.

## Alternatives Considered

- **Colon form everywhere.** Switch the helpers to print `PASS:` and `FAIL:`. Rejected because the case-statement cases outnumber the function cases about four to one. Changing the helpers would touch more lines for the same result.
- **Helpers everywhere.** Route the function cases through `ok` and `fail`. Rejected for this change because `fail` sets the global `FAILED` flag while the function cases signal failure by return code to the `step67` and `prefire` group runners. Merging the two paths changes control flow, and the goal here is only the printed text.
- **Keep the copy.** Leave the `-E` file as a frozen copy of the old harness. Rejected because git history already holds the old version, and a second copy would drift and could be run by mistake.

## Consequences

- Positive: `grep -c '^PASS '` and `grep -c '^FAIL '` now count every case in one run.
- Positive: the change is text only. No case logic, fixture, or exit code moved.
- Negative: any external script that matched `PASS: step67` or `FAIL: prefire` breaks. The only CI caller in `.github/workflows/tests.yml` reads the exit code, so none is known.
- Negative: if the `-E` copy merges, the repo carries a 942-line dead harness that still prints the old format. The reviewer should remove it.

## References

- `bin/test-matrix-assertion-check`: the harness whose output format this ADR fixes.
- `bin/lib/plan-matrix-assertions`: the checker the harness tests.
- `bin/automouse/prompt-impl`: source of the Step 6.7 block the `step67-*` cases extract.
- `.claude/rules/workflow-continuity.md`: source of the pre-fire command the `prefire-*` cases extract.
- `.github/workflows/tests.yml`: the CI job that runs the harness.
- `ibl5/docs/decisions/README.md`: ADR policy that `bin/adr-check` enforces.
