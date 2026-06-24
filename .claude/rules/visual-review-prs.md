---
description: On a visual-change PR, the visual-regression run publishes the Playwright HTML report to per-SHA GitHub Pages and posts a sticky visual-review PR comment grouped by module, with a "changed but NOT covered" coverage-gap section.
paths:
  - ".github/workflows/e2e-tests.yml"
  - "ibl5/tests/e2e/vr-manifest.ts"
  - "ibl5/tests/e2e/vr-coverage-map.ts"
  - "ibl5/tests/e2e/vr-review-comment.ts"
  - "bin/vr-changed-coverage"
  - "bin/vr-review-comment"
last_verified: 2026-06-24
---

# Visual-review PRs

See ADR-0068 and ADR-0069 for the decisions and rationale.

## What runs

When the visual-regression (VR) step in `.github/workflows/e2e-tests.yml` (the `e2e`
"Visual Regression" job) detects pixel diffs on a PR — and the `update-baselines` label
is **absent** — four steps fire, in order, AFTER the VR run and BEFORE baseline regen:

1. **Compute coverage** — `git diff --name-only <base>...HEAD | bin/vr-changed-coverage`
   maps the changed files onto `vr-manifest.ts` rows (covered rows, uncovered website
   paths, and a global-change flag for shared CSS/theme/class edits).
2. **Deploy report to per-SHA Pages** — pushes the generated Playwright HTML report (the
   `playwright-report` output dir the VR run writes under ibl5/) to the `gh-pages` branch
   under `<sha>/visual-review/` (multiple open PRs/SHAs coexist).
3. **Build comment** — `bin/vr-review-comment` renders the sticky markdown.
4. **Post sticky comment** — `marocchino/sticky-pull-request-comment@v3`, header `visual-review`.

A `vr-pages-cleanup` job (push-to-master only, not part of the required gate) prunes
per-SHA report dirs whose newest commit is older than 14 days.

## Reading the comment

- The PR is **red until approved** — by design. The VR check fails whenever pixels change.
- Diffing views are grouped per module in `<details>` blocks; each link deep-links into the
  per-SHA Playwright HTML report (filtered by test title) where the before/after/diff slider
  shows desktop + mobile.
- A **"🆕 New views (no prior baseline — review the render)"** section lists failing cells
  whose baseline was never committed (a brand-new VR row). These have no before/after — the
  link shows the first render; sanity-check it, then `update-baselines` commits it as the
  baseline. NEW cells are excluded from the "changed view(s)" count so a first-render never
  reads as a regression. Classification uses `git ls-files` on the tracked index (not disk),
  per ADR-0069.
- The per-SHA Pages URL shape is `https://a-jay85.github.io/IBL5/<sha>/visual-review/`.
- A **"⚠️ Changed but NOT covered by the VR manifest"** section lists changed website paths
  that match no manifest row — review those by hand or add a `vr-manifest.ts` row. A
  **global-change banner** appears when a shared CSS/theme/class file changed.

## Approving (sign-off)

Apply the **`update-baselines`** label. That regenerates the baselines, auto-commits them,
and re-runs VR green — the auto-commit is the durable approval record. There is no separate
approval mechanism.

## When the comment is missing

- Fork PR: the read-only token has no secrets, so the publish/comment steps no-op (expected).
- No pixel diffs: VR passes, nothing to review, no comment.

## Modifying the selection logic

Changed-files → coverage selection lives in `ibl5/tests/e2e/vr-coverage-map.ts`
(`classifyChangedFiles`, `deriveModuleGlob`, `rowGlobs`) and the comment markup in
`ibl5/tests/e2e/vr-review-comment.ts` (`buildComment`). Both are pure and unit-tested
(`ibl5/tests/ts-unit/vr-coverage-map.test.ts`, `ibl5/tests/ts-unit/vr-review-comment.test.ts`,
run via `bun run test:unit` from `ibl5/`). Per-row source overrides use the optional
`sourceGlobs` field on `VrRow`. **Changing this selection logic is a mechanical-enforcement
surface and requires an ADR.**

## One-time deployment prerequisite

GitHub Pages must be enabled with source = the `gh-pages` branch, once, after the branch
first exists (owner action — Pages is otherwise 404):

```bash
gh api -X POST repos/a-jay85/IBL5/pages -f 'source[branch]=gh-pages' -f 'source[path]=/'
```

(or repo Settings → Pages).
