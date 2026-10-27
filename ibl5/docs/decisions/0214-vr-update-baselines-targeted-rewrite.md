---
description: The update-baselines label rewrites only the VR snapshots the in-job strict pass flags, minus flake cells, and commits only those files.
last_verified: 2026-10-23
owner: ajaynicolas
---

# ADR-0214: Targeted update-baselines rewrite from the in-job strict pass

**Status:** Accepted
**Date:** 2026-10-23
**Deciders:** a-jay85

## Context

The `update-baselines` label is the owner's sign-off on a visual change. Until this ADR the label run executed `npx playwright test --update-snapshots`, and only when the VR step failed. That left two gaps. Playwright's default `changed` mode rewrites only cells over the comparator's 0.005 ratio, so a sub-gate drift that the review-only strict pass (ADR-0180) shows in the gallery was never committed. The VR step stays green on such a drift, so the regen step never ran at all.

PR [#3468](https://github.com/a-jay85/IBL5/pull/3468) tried `--update-snapshots=all`. Its noise test on its own no-UI branch rewrote 74 of 137 PNGs ([run 40038410311](https://github.com/a-jay85/IBL5/actions/runs/40038410311), auto-commit 1d75578c62, reverted). CI renders are not byte-stable, so rewriting every snapshot turns a sign-off into a commit of mostly unrelated byte noise. The PR was closed.

## Decision

The label run computes its own target list in the same job that rendered the cells. It reruns `bin/vr-build-gallery` against the PR branch HEAD's committed baselines into a scratch gallery (`ibl5/vr-gallery-targets/` (example), gitignored). `bin/vr-apply-baselines` then copies `vr-actuals/<title>.a.png` over `<title>.png` for the titles in `changedCells` and `newCells`, minus any title in `flakeCells`. Every title must match `^[a-z0-9][a-z0-9-]*$` and every render must exist, or the script fails before writing anything. The commit step stages only the listed paths, fails if anything else is staged (`--check-staged`), skips the commit when the list is empty, and prints the committed count to the step summary. The regen step now runs whenever the VR step finished, pass or fail.

ADR-0074 (selection decoupled from the VR check outcome) and ADR-0180 (the review-only strict pass) stand unedited. This ADR extends them: the strict pass now also chooses what the sign-off commits. The VR check's thresholds stay as they were.

## Consequences

- A no-UI PR's sign-off is expected to commit 0 to 5 PNGs. The PR body of the PR that ships this ADR records the measured count and run URL.
- A flake cell is never committed and keeps the VR check red. The remedy stays a VR re-run.
- Triage against HEAD means a re-apply after an earlier in-branch approval commits only views that drifted since.
- The old full `--update-snapshots` regen is gone.

## References

- `bin/vr-apply-baselines` and its harness `bin/test-vr-apply-baselines`
- `bin/vr-build-gallery`
- `.github/workflows/e2e-tests.yml` (`Regenerate visual regression baselines`, `Commit and push updated baselines`)
- `.claude/rules/visual-review-prs.md` § Approving (sign-off)
