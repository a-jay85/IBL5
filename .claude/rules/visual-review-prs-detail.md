---
description: Lazy detail companion to visual-review-prs.md. Covers reading the VR comment, self-stability (flake) triage and the strict review-only pass, cropped before/after changed-screen pairs in the PR body, publishing agent shots, how an `update-baselines` label run treats an in-flight E2E run, and the one-time Pages source setting.
last_verified: 2026-11-11
paths:
  - ".github/workflows/e2e-tests.yml"
  - ".github/workflows/update-baselines.yml"
  - ".github/workflows/pages-deploy.yml"
  - "ibl5/tests/e2e/vr-manifest.ts"
  - "ibl5/tests/e2e/vr-coverage-map.ts"
  - "ibl5/tests/e2e/vr-gallery.ts"
  - "ibl5/tests/e2e/vr-review-comment.ts"
  - "ibl5/tests/e2e/vr-pr-body.ts"
  - "ibl5/tests/e2e/vr-crop.ts"
  - "ibl5/tests/e2e/vr-crop-png.ts"
  - "bin/vr-changed-coverage"
  - "bin/vr-build-gallery"
  - "bin/vr-apply-baselines"
  - "bin/vr-review-comment"
  - "ibl5/tests/e2e/vr-manual-rows.ts"
  - "ibl5/tests/e2e/manual-rows.spec.ts"
  - "ibl5/playwright.manual-rows.config.ts"
---

# Visual Review PRs Detail

Companion to `.claude/rules/visual-review-prs.md`, which points here.

## Reading the comment

- The comment makes **no claim about the check's red/green color.** In the regen-into-branch steady
  state the VR gate can be **green** while the gallery still shows **changed** cells (the in-branch
  baseline was refreshed, but the render still differs from master's committed baseline). The prose
  is true in both states and never says the check "stays red until then."
- Diffing views are grouped per module in `<details>` blocks; each link points into the static
  side-by-side gallery (the `<title>` anchor) where the master-vs-PR before/after shows desktop +
  mobile. The Playwright report (traces) is preserved under `…/playwright-report/`.
- A **"🆕 New views (no prior baseline — review the render)"** section lists cells whose baseline was
  never committed (a brand-new VR row). These have no before — the link shows the first render; <!-- slop-ok -->
  sanity-check it, then `update-baselines` commits it as the baseline. NEW cells are excluded from the
  "changed view(s)" count so a first-render never reads as a regression. The NEW-vs-CHANGED split
  (ADR-0069) is computed in `bin/vr-build-gallery` from master's committed baseline set, not from <!-- slop-ok -->
  disk.
- The per-SHA Pages URL shape is `https://a-jay85.github.io/IBL5/<sha>/visual-review/`.
- A **"⚠️ Changed but NOT covered by the VR manifest"** section lists changed website paths
  that match no manifest row — review those by hand or add a `vr-manifest.ts` row. A <!-- slop-ok -->
  **global-change banner** appears as a standalone coverage heads-up whenever a shared
  CSS/theme/class file changed, independent of whether any row diffed.

## Self-stability (flake) vs real change

Each cell is captured twice: render A (`.a.png`) and a reload render B (`.b.png`). A cell is a real
**changed** cell only when A ≈ B but both differ from master's committed baseline. If A ≠ B
(differing dimensions or pixels) the render is self-unstable; the cell is demoted to an
**infra/flake** cell surfaced in a separate `⚠️ … failed to render` section whose remedy is
**re-run the VR job**, NOT `update-baselines` (which cannot fix a flaky render). A `changed` cell
whose reload `.b.png` is missing is likewise demoted to infra. See ADR-0073 for the
infra-vs-pixel-diff labeling this reuses.

A second, review-only strict pass (ADR-0180) runs after this triage. It uses per-pixel threshold `STRICT_PIXEL_THRESHOLD` (0.065) and an absolute floor `STRICT_MIN_CHANGED_PIXELS` (25), both in `ibl5/tests/e2e/vr-gallery.ts`. It can only upgrade an `unchanged` cell to `changed`, and only when the reload render exists and A and B agree under the strict threshold. It never creates a flake cell and never touches the VR check, whose 0.2 threshold and 0.005 ratio are unchanged. Rows that set `extraMaxDiffPixelRatio` skip it.

## The update-baselines label and in-flight runs

A label applied while an `E2E Tests` run is in flight no longer cancels it. The label run starts from
`.github/workflows/update-baselines.yml` in its own concurrency group, so the PR's required `E2E Tests`
run keeps going and keeps its result (ADR-0225).

## Changed screens in the PR body

Changed cells get cropped before/after pairs in the same block (ADR-0181). `--crop-changed` runs
pixelmatch at the gate threshold (0.22) with `diffMask`. A height change is aligned as one inserted
or removed band, so content that only shifted is not marked. Changed pixels become red boxes (#e11d48,
3 px) drawn at the same spot on both halves. Each box is padded by 56 px into a crop window, at most
three windows per cell. A width change or a sub-threshold change publishes uncropped, labelled.
Manual-row and agent-shot composites get one window holding every box. Output: `changed/<title>.<i>.{before,after,side,stack}.png` plus
`changed/spots.json`. One `###` per spot (module, cell, region), desktop first, mobile in one
`<details>` per module. Each spot is one `<picture>`: on screens 768 px and wider it shows the
`side` composite (before left, after right), and narrower screens get `stack` (before above
after). A red band tops the before and a green band tops the after. GitHub's sanitizer keeps
`<source media>` (probed on PR #2950). A spot whose composite failed shows separate before and
after images. Agent shots with a before and manual rows with an ok before use the same
`<picture>`. A short-SHA and UTC stamp opens the block. Cap: 40 spots or 40,000 chars,
then a gallery link. Code: `ibl5/tests/e2e/vr-crop.ts`, `ibl5/tests/e2e/vr-crop-png.ts`,
`ibl5/tests/e2e/vr-pr-body.ts`.

## Publishing agent shots

`bin/vr-review-comment --publish-agent-shots=<PR#> --agent-shots=<shots.json> [--dry-run]`.
JSON: 1 to 20 of `{"label": "nav-bar", "before": "a.png", "after": "b.png"}` (`before` optional,
PNG under 5 MB). Files go to `<head-sha>/visual-review/agent-shots/` on `gh-pages`, then an
agent-shot sub-block is upserted, which the CI refresh keeps. Never put a local file path in a PR
body. Labels are sanitized to `[a-z0-9-]`. `bin/prune-vr-galleries` prunes the per-SHA tree.

## One-time deployment prerequisite

GitHub Pages must use source = **GitHub Actions** (`build_type: workflow`). The owner sets it once
in Repo Settings → Pages → Build and deployment → Source, or:

```bash
gh api --method PUT repos/a-jay85/IBL5/pages -f build_type=workflow
```

Until then, `Deploy VR gallery to Pages` runs fail at the deploy step (expected, self-clears once flipped). After the flip, re-serve
once via the workflow's `workflow_dispatch`.
