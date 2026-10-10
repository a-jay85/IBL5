---
description: Lazy detail companion to visual-review-prs.md. Covers self-stability (flake) triage and the strict review-only pass, cropped before/after changed-screen pairs in the PR body, and publishing agent shots.
last_verified: 2026-10-07
paths:
  - "ibl5/tests/e2e/vr-gallery.ts"
  - "ibl5/tests/e2e/vr-crop.ts"
  - "ibl5/tests/e2e/vr-crop-png.ts"
  - "bin/vr-build-gallery"
  - "bin/vr-review-comment"
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

A second, review-only strict pass (ADR-0180) runs after this triage. It uses per-pixel threshold `STRICT_PIXEL_THRESHOLD` (0.05) and an absolute floor `STRICT_MIN_CHANGED_PIXELS` (25), both in `ibl5/tests/e2e/vr-gallery.ts`. It can only upgrade an `unchanged` cell to `changed`, and only when the reload render exists and A and B agree under the strict threshold. It never creates a flake cell and never touches the VR check, whose 0.2 threshold and 0.005 ratio are unchanged. Rows that set `extraMaxDiffPixelRatio` skip it.

## Changed screens in the PR body

Changed cells get cropped before/after pairs in the same block (ADR-0181). `--crop-changed` runs
pixelmatch at the gate threshold (0.2) with `diffMask`, clusters changed pixels into at most three
padded spots, and crops both sides to one box. A size change or a sub-threshold change publishes
uncropped, labelled. Output: `changed/<title>.<i>.{before,after}.png` plus `changed/spots.json`.
One `###` per spot (module, cell, region), before above after, desktop first, mobile in one
`<details>` per module. A short-SHA and UTC stamp opens the block. Cap: 40 spots or 40,000 chars,
then a gallery link. Code: `ibl5/tests/e2e/vr-crop.ts`, `ibl5/tests/e2e/vr-crop-png.ts`,
`ibl5/tests/e2e/vr-pr-body.ts`.

## Publishing agent shots

`bin/vr-review-comment --publish-agent-shots=<PR#> --agent-shots=<shots.json> [--dry-run]`.
JSON: 1 to 20 of `{"label": "nav-bar", "before": "a.png", "after": "b.png"}` (`before` optional,
PNG under 5 MB). Files go to `<head-sha>/visual-review/agent-shots/` on `gh-pages`, then an
agent-shot sub-block is upserted, which the CI refresh keeps. Never put a local file path in a PR
body. Labels are sanitized to `[a-z0-9-]`. `bin/prune-vr-galleries` prunes the per-SHA tree.
