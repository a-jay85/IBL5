---
description: On a visual-change PR, the visual-regression run builds a change-driven before/after gallery (rows whose render differs from master's committed baseline) and posts a sticky visual-review PR comment grouped by module, with a "changed but NOT covered" coverage-gap section.
paths:
  - ".github/workflows/e2e-tests.yml"
  - "ibl5/tests/e2e/vr-manifest.ts"
  - "ibl5/tests/e2e/vr-coverage-map.ts"
  - "ibl5/tests/e2e/vr-gallery.ts"
  - "ibl5/tests/e2e/vr-review-comment.ts"
  - "ibl5/tests/e2e/vr-pr-body.ts"
  - "bin/vr-changed-coverage"
  - "bin/vr-build-gallery"
  - "bin/vr-review-comment"
last_verified: 2026-07-02
---

# Visual-review PRs

See ADR-0068, ADR-0069, ADR-0073, ADR-0074, and ADR-0076 for the decisions and rationale. ADR-0074
is the current gallery-selection model: the gallery is **change-driven** (built from rows whose PR
render differs from master's committed baseline), not failure-driven. ADR-0076 extends it: brand-new
views (`gallery.newCells`) are additionally published inline at the top of the PR body, not only in
the sticky comment.

## What runs

On a PR run of the visual-regression (VR) step in `.github/workflows/e2e-tests.yml` (the `e2e`
"Visual Regression" job) — whenever the `update-baselines` label is **absent** — five steps fire,
in order, AFTER the VR run and BEFORE baseline regen. They fire on **pass OR fail**: selection is
decoupled from the VR check outcome (ADR-0074), so the gallery publishes even when VR is green.
They are skipped only during baseline regen (the `update-baselines` label).

1. **Compute coverage** — `git diff --name-only <base>...HEAD | bin/vr-changed-coverage`
   maps the changed files onto `vr-manifest.ts` rows (uncovered website paths and a global-change
   flag for shared CSS/theme/class edits). Coverage drives **only** the banner now — never the
   gallery cell set.
2. **Build gallery** — `bin/vr-build-gallery` reads the raw actuals the VR spec wrote to
   `ibl5/vr-actuals` (each cell captured twice: render `.a.png` + reload `.b.png`), reads master's
   committed baseline for each row via `git show <base.sha>:<snapshot-path>` (regen-immune), triages
   each row, and writes the static side-by-side `index.html` (with `<title>` anchors) + per-SHA
   artifacts into `ibl5/vr-gallery`, plus the pre-classified `gallery.json`
   (`changedCells`/`newCells`/`flakeCells`).
3. **Deploy gallery to per-SHA Pages** — pushes `ibl5/vr-gallery` to the `gh-pages` branch under
   `<sha>/visual-review/` (multiple open PRs/SHAs coexist). The Playwright HTML report (traces) is
   preserved under `<sha>/visual-review/playwright-report/`.
4. **Build comment** — `bin/vr-review-comment` consumes the pre-classified `gallery.json` and renders
   the sticky markdown.
5. **Post sticky comment** — `marocchino/sticky-pull-request-comment@v3`, header `visual-review`.

A `vr-pages-cleanup` job (push-to-master only, not part of the required gate) prunes
per-SHA gallery dirs whose newest commit is older than 14 days.

## Reading the comment

- The comment makes **no claim about the check's red/green color.** In the regen-into-branch steady
  state the VR gate can be **green** while the gallery still shows **changed** cells (the in-branch
  baseline was refreshed, but the render still differs from master's committed baseline). The prose
  is true in both states — it never says the check "stays red until then."
- Diffing views are grouped per module in `<details>` blocks; each link points into the static
  side-by-side gallery (the `<title>` anchor) where the master-vs-PR before/after shows desktop +
  mobile. The Playwright report (traces) is preserved under `…/playwright-report/`.
- A **"🆕 New views (no prior baseline — review the render)"** section lists cells whose baseline was
  never committed (a brand-new VR row). These have no before — the link shows the first render;
  sanity-check it, then `update-baselines` commits it as the baseline. NEW cells are excluded from the
  "changed view(s)" count so a first-render never reads as a regression. The NEW-vs-CHANGED split
  (ADR-0069) is computed in `bin/vr-build-gallery` from master's committed baseline set, not from
  disk.
- The per-SHA Pages URL shape is `https://a-jay85.github.io/IBL5/<sha>/visual-review/`.
- A **"⚠️ Changed but NOT covered by the VR manifest"** section lists changed website paths
  that match no manifest row — review those by hand or add a `vr-manifest.ts` row. A
  **global-change banner** appears as a standalone coverage heads-up whenever a shared
  CSS/theme/class file changed, independent of whether any row diffed.

## Approving (sign-off)

Apply the **`update-baselines`** label. That regenerates the baselines, auto-commits them,
and re-runs VR green — the auto-commit is the durable approval record. There is no separate
approval mechanism. Because the gallery reads master's committed baseline via
`git show <base.sha>:…`, the before/after evidence survives this regen instead of vanishing.

## When the comment is missing

- Fork PR: the read-only token has no secrets, so the publish/comment steps no-op (expected).
- No render diffs and no global change: nothing to review, no comment.

## Self-stability (flake) vs real change

Each cell is captured twice — render A (`.a.png`) and a reload render B (`.b.png`). A cell is a real
**changed** cell only when A ≈ B but both differ from master's committed baseline. If A ≠ B
(differing dimensions or pixels) the render is self-unstable; the cell is demoted to an
**infra/flake** cell surfaced in a separate `⚠️ … failed to render` section whose remedy is
**re-run the VR job**, NOT `update-baselines` (which cannot fix a flaky render). A `changed` cell
whose reload `.b.png` is missing is likewise demoted to infra. See ADR-0073 for the
infra-vs-pixel-diff labeling this reuses.

## New screens in the PR body

In addition to the sticky comment, brand-new views (`gallery.newCells`) are published inline at the
top of the PR body itself (ADR-0076) — no click required to see a first render. Two extra workflow
steps run after "Deploy gallery to per-SHA Pages" and "Post sticky comment":

- **Copy new-screen renders** — `bin/vr-review-comment --copy-new-screens=DEST` copies each new
  cell's first-render PNG into a `new-screens/` subdirectory of the Pages deploy tree.
- **Splice into the PR body** — `bin/vr-review-comment --update-pr-body=<PR#>` polls the first
  new-screen image URL for readiness (bounded, ~30s), then splices a marker-delimited
  (`<!-- vr-new-screens:begin/end -->`), idempotent block at offset 0 of the PR body via
  `gh pr edit --body-file`. The block self-removes once `newCells` is empty (baseline committed via
  `update-baselines`), so it never goes stale. `--dry-run` exercises the splice without mutating a
  real PR.

Both steps are `continue-on-error: true` — a failure here degrades to "no inline image," it never
fails the VR job or blocks the sticky comment. The pure splice/copy-plan logic lives in
`ibl5/tests/e2e/vr-pr-body.ts` (unit-tested in `ibl5/tests/ts-unit/vr-pr-body.test.ts`); only `gh`/
`fetch`/`fs` I/O lives in the `bin/vr-review-comment` glue layer.

## Modifying the selection logic

Gallery cell selection lives in `bin/vr-build-gallery` and `ibl5/tests/e2e/vr-gallery.ts`
(`triageCell`, `buildGalleryHtml`); changed-files → coverage (banner only) lives in
`ibl5/tests/e2e/vr-coverage-map.ts` (`classifyChangedFiles`, `deriveModuleGlob`, `rowGlobs`); the
comment markup lives in `ibl5/tests/e2e/vr-review-comment.ts` (`buildComment`). The pure modules are
unit-tested (`ibl5/tests/ts-unit/vr-gallery.test.ts`, `ibl5/tests/ts-unit/vr-coverage-map.test.ts`,
`ibl5/tests/ts-unit/vr-review-comment.test.ts`, run via `bun run test:unit` from `ibl5/`). Per-row
source overrides use the optional `sourceGlobs` field on `VrRow`. **Changing this selection logic is
a mechanical-enforcement surface and requires an ADR** (current: ADR-0074). The PR-body new-screens
publishing surface (`--copy-new-screens`/`--update-pr-body` on `bin/vr-review-comment`,
`ibl5/tests/e2e/vr-pr-body.ts`, `ibl5/tests/ts-unit/vr-pr-body.test.ts`) is likewise a
mechanical-enforcement surface, covered by **ADR-0076**.

## One-time deployment prerequisite

GitHub Pages must be enabled with source = the `gh-pages` branch, once, after the branch
first exists (owner action — Pages is otherwise 404):

```bash
gh api -X POST repos/a-jay85/IBL5/pages -f 'source[branch]=gh-pages' -f 'source[path]=/'
```

(or repo Settings → Pages).
