---
description: Classify each failing visual-review cell as NEW (no committed baseline) vs CHANGED (a real pixel regression) using git ls-files on the tracked index, and render NEW views in a distinct section of the sticky PR comment.
last_verified: 2026-06-24
---

# ADR-0069: NEW vs CHANGED classification in the visual-review PR comment

**Status:** Accepted
**Date:** 2026-06-24

## Lineage

Extends **ADR-0068** (`ibl5/docs/decisions/0068-visual-review-pr-publishing.md`) — does NOT supersede it. ADR-0068 established the sticky PR comment and its module-grouped diff-cell format; this ADR adds a classification layer on top of that format.

## Context

The sticky `visual-review` PR comment (ADR-0068) frames every failing VR cell identically
as a "changed view" — implying a pixel regression that must be scrutinized against a
committed before/after. But a row whose baseline was **never committed** is not a
regression: Playwright's default `missing` mode writes the new render as a baseline AND
fails the test. This means `extractDiffCells` includes both genuine regressions and
first-renders in the same list, framed the same way.

Reviewers cannot tell "this is the first render of a brand-new view — sanity-check it"
from "this is a real diff against a committed before/after." The result is either
over-scrutiny of first-renders (treating them as regressions) or under-scrutiny (mentally
dismissing all red cells as "probably new baselines").

This touches the `buildComment` markup — a mechanical-enforcement surface per
`.claude/rules/visual-review-prs.md` § "Modifying the selection logic" — so an ADR is
required.

## Decision

Classify each failing cell as **NEW** (its baseline snapshot is NOT committed to the git
index) or **CHANGED** (a committed baseline exists → a real pixel regression). NEW cells
render in a distinct, clearly-labeled **"🆕 New views (no prior baseline — review the
render)"** section; CHANGED cells render in the existing "changed view(s)" section.

The **"changed view(s)" count excludes NEW cells** so a first-render never reads as a
regression count. An all-NEW PR omits the changed-views header entirely and does not
print "No visual diffs detected" — the NEW section IS the signal.

The classification is **presentation-only, not an approval fork**: applying
`update-baselines` commits the new baseline for NEW rows and updates it for CHANGED rows
identically. No new sign-off mechanism is introduced.

### The classification signal: `git ls-files` on the tracked index

The signal is `git ls-files <snapshot path>` read against the **tracked index**, not the
working tree. A brand-new row's snapshot path returns **empty stdout** — Playwright's
`missing` mode writes the `.png` UNTRACKED into the working directory, so it is not in
the index. A CHANGED row's committed baseline prints its path.

`existsSync` / disk-existence is **invalid** here: `missing` mode writes the untracked
baseline into the working dir during the run, making the file present on disk for both
NEW and CHANGED rows.

The discriminator is the **printed path line, NOT the exit code**: both a tracked and an
untracked path exit 0 from `git ls-files`. A naive `if git ls-files …; then` would mark
everything CHANGED. The correct check is whether the path appears as a non-empty stdout
line.

A single batched `git ls-files -z -- <paths>` call is used over all failing cells'
snapshot paths (never over the whole manifest — that could hit ARG_MAX and is wasteful).
The `-z` NUL-delimiter output is unambiguous about empty results and robust against
hypothetical path oddities.

### Architecture: pure builder, I/O in the wrapper

The builder `ibl5/tests/e2e/vr-review-comment.ts` remains **pure — no I/O**. The new
`classifyCells(cells, trackedTitles: Set<string>)` function is keyed on title and
receives the tracked/untracked result as **data** from the wrapper. The `git ls-files`
call lives entirely in `bin/vr-review-comment`.

This preserves the builder's existing "No I/O" contract and keeps the classifier
independently unit-testable from a plain Set with zero git/fs dependency.

### Fail-safe on git error

If `git ls-files` throws (git unavailable, not a git repo), the wrapper falls back to
treating all cells as CHANGED — the pre-existing behavior — and never crashes the comment
build. The NEW label is lost in that failure mode; the comment is otherwise identical to
today's output.

## Alternatives Considered

- **Secondary signal: `-diff.png` attachment presence/absence** — At the attachment
  level, `missing` mode emits `{expected, actual, NO -diff.png}` while a real regression
  emits `{expected, actual, -diff.png}`. The discriminator is presence/absence of the
  `-diff.png` attachment. Rejected: the primary git signal is simpler, requires no
  attachment-set parsing the builder does not do today, and is sufficient for this repo's
  workflow. Primary-git-only is chosen.

- **Pure title-keyed `classifyCells` that itself calls git** — would push I/O into the
  pure builder layer, violating its "No I/O" contract. Rejected in favor of the
  wrapper-computes-Set / builder-receives-data seam described above.

- **`existsSync` on the working-tree snapshot path** — Invalid because `missing` mode
  writes the untracked baseline into the working dir during the run. Disk presence cannot
  distinguish NEW from CHANGED in that environment.

## Consequences

- **Positive:** Reviewers no longer over-scrutinize first-renders as regressions. The
  labeled NEW section says "review the render," not "investigate a diff," giving the
  correct mental model.
- **Positive:** The "changed view(s)" count is accurate — it excludes first-renders,
  which are not regressions.
- **Positive:** The builder remains pure and the new `classifyCells` is independently
  unit-testable.
- **Negative:** Classification is git-index-coupled — a `git ls-files` failure fails safe
  to "all CHANGED" (pre-existing behavior), losing only the NEW label, never crashing the
  comment build.
- **Negative:** The signal depends on `standings.png` (and other committed baselines)
  remaining in the tracked index. If a baseline is deleted from the index without a
  replacement, the cell would be misclassified as NEW rather than CHANGED. This is an
  edge case unlikely in normal workflow.

## References

- `bin/vr-review-comment` — CLI wrapper; contains the `git ls-files` call and Set
  construction.
- `ibl5/tests/e2e/vr-review-comment.ts` — pure builder; `classifyCells`, `buildComment`,
  `DiffCell.isNew`.
- `ibl5/tests/ts-unit/vr-review-comment.test.ts` — unit tests for the classifier and
  `buildComment` NEW/CHANGED rendering.
- `.claude/rules/visual-review-prs.md` — operator-facing rule; "Reading the comment"
  section documents the NEW section.
- `ibl5/playwright.visual.config.ts` — snapshot path template
  (`snapshotPathTemplate: '{testDir}/{testFileDir}/{testFileName}-snapshots/{arg}{ext}'`).
- `ibl5/docs/decisions/0068-visual-review-pr-publishing.md` — the publishing pipeline
  this classification layer extends.
