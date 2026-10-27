---
description: Lazy detail companion to visual-review-prs.md. Covers self-stability (flake) triage and the strict review-only pass, cropped before/after changed-screen pairs in the PR body, and publishing agent shots.
last_verified: 2026-10-27
paths:
  - ".github/workflows/e2e-tests.yml"
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

## Self-stability (flake) vs real change

Each cell is captured twice: render A (`.a.png`) and a reload render B (`.b.png`). A cell is a real
**changed** cell only when A ≈ B but both differ from master's committed baseline. If A ≠ B
(differing dimensions or pixels) the render is self-unstable; the cell is demoted to an
**infra/flake** cell surfaced in a separate `⚠️ … failed to render` section whose remedy is
**re-run the VR job**, NOT `update-baselines` (which cannot fix a flaky render). A `changed` cell
whose reload `.b.png` is missing is likewise demoted to infra. See ADR-0073 for the
infra-vs-pixel-diff labeling this reuses.

A second, review-only strict pass (ADR-0180) runs after this triage. It uses per-pixel threshold `STRICT_PIXEL_THRESHOLD` (0.065) and an absolute floor `STRICT_MIN_CHANGED_PIXELS` (25), both in `ibl5/tests/e2e/vr-gallery.ts`. It can only upgrade an `unchanged` cell to `changed`, and only when the reload render exists and A and B agree under the strict threshold. It never creates a flake cell and never touches the VR check, whose 0.2 threshold and 0.005 ratio are unchanged. Rows that set `extraMaxDiffPixelRatio` skip it.

## Changed screens in the PR body

Changed cells get cropped before/after pairs in the same block (ADR-0181). `--crop-changed` runs
pixelmatch at the gate threshold (0.22) with `diffMask`, clusters changed pixels into at most three
padded spots, and crops both sides to one box. A size change or a sub-threshold change publishes
uncropped, labelled. Output: `changed/<title>.<i>.{before,after,side,stack}.png` plus
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
