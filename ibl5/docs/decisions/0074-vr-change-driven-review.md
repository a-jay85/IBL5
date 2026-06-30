---
description: The visual-review gallery is built from rows whose PR render differs from master's committed baseline (read via git show <base.sha>:<snapshot>), not from Playwright cells that failed pixel comparison; this makes the gallery immune to a baseline regen into the PR branch and lets it publish on pass OR fail.
last_verified: 2026-06-30
---

# ADR-0074: Change-driven visual-review gallery (render-diff, not comparison-failure)

**Status:** Accepted
**Date:** 2026-06-29

## Lineage

Extends **ADR-0068** (per-SHA Pages publishing), **ADR-0069** (NEW-vs-CHANGED comment
classification), and **ADR-0073** (infra/render-failure vs pixel-diff labeling) — does NOT
supersede them. Those decisions assumed the gallery is fanned out from the set of VR cells that
**failed** Playwright's pixel comparison. This ADR replaces that selection seam: cells are now
built from rows whose PR render **differs from master's committed baseline**, decoupled from
whether the VR check passed or failed.

## Context

The old `visual-review` gallery was **failure-driven**: it rendered exactly the cells whose
Playwright `toHaveScreenshot` comparison failed in this run. That coupling has a fatal blind spot
once baselines can be regenerated into the PR branch (the `update-baselines` flow): the moment the
in-branch baselines are refreshed, the comparison passes, the failure set empties, and the gallery
goes blank — even though the PR's render genuinely differs from what `master` ships. PR #1188
regressed exactly this way: a reviewer applying `update-baselines` made the visual evidence
disappear instead of recording it.

The fix is to stop asking "did the comparison fail?" and start asking "does this PR's render differ
from the baseline `master` committed?" The "before" image must come from master's **committed**
snapshot, read via `git show <base.sha>:<snapshot-path>`, so it is immune to any regen written into
the PR branch's working tree. The "after" image is captured explicitly per cell, twice (render A,
then a reload render B), so the comparison can also tell a real change apart from a self-unstable
(flaky) render.

This rewrites the gallery selection/markup pipeline — a mechanical-enforcement surface per
`.claude/rules/visual-review-prs.md` — and adds a new bin entrypoint (`bin/vr-build-gallery`) plus a
workflow rewire and a rule edit, so an ADR is required. This ADR is that decision record: it
resolves the `bin/adr-check` decision-trigger for `bin/vr-build-gallery`, the
`.github/workflows/e2e-tests.yml` change, and the `.claude/rules/visual-review-prs.md` edit — no
`no-adr` bypass is needed.

## Decision

Build the gallery from **render diffs against master's committed baseline**, not from
comparison-failure cells:

- **Before** = `git show <base.sha>:<snapshot-path>` — master's committed baseline PNG. Reading the
  tracked blob at the PR's base SHA makes the "before" immune to a baseline regen into the PR branch.
- **After** = an explicit `page.screenshot()` per cell, captured **twice**: render A (`.a.png`) and a
  reload render B (`.b.png`). The spec writes raw actuals to `ibl5/vr-actuals`; it no longer relies
  on Playwright's pass/fail to decide what to capture.
- **Changed** ⇔ render A differs from master's baseline. `bin/vr-build-gallery` triages each row,
  pre-classifies it into `changedCells` / `newCells` / `flakeCells`, and writes `gallery.json` plus
  the static side-by-side `index.html` (with `<title>` anchors) and the per-SHA artifacts. The
  comment wrapper (`bin/vr-review-comment`) consumes the pre-classified cells; it no longer parses
  the Playwright report to classify (the ADR-0069 `isNew` split now lives in the gallery builder).
- **Self-stability flake demotion:** if A ≈ B but both ≠ master, it is a real `changed` cell; if
  A ≠ B (differing dimensions or pixels), the render is self-unstable and the cell is demoted to a
  `flake` / infra cell surfaced in the separate "failed to render" section (ADR-0073 semantics),
  never as a pixel change demanding `update-baselines`. A `changed` cell with no `.b.png` is also
  demoted to infra (the reload capture never completed). `unchanged` cells are dropped.
- **Publish on pass OR fail.** Because selection no longer keys on the VR check outcome, the four
  publish steps (compute coverage → build gallery → deploy gallery → build comment → post comment)
  fire on **every** PR run, gated only by `!update-baselines` (skipped during baseline regen). The
  three regen steps keep the `outcome == 'failure'` gate.

**Key invariant:** in the regen-into-branch steady state the VR gate can be **green** while the
gallery still shows **changed** cells (the in-branch baseline was refreshed, but the render still
differs from `master`'s committed baseline). The comment prose must therefore be true in **both**
red and green: it must not assert a check color, must not claim the check "stays red until then,"
and must not say "failed to render" on the changed path.

**Gallery tolerance matches the gate's effective per-row tolerance — on both axes.** A row is
`changed` only when render A's diff against master's baseline **exceeds** the same tolerance the VR
gate applies. The gate's tolerance has **two** knobs, and the gallery must mirror **both** or it
re-introduces the very noise this fixes:

- **Per-pixel threshold (what counts as a different pixel).** The gate's `toHaveScreenshot`
  (`ibl5/playwright.visual.config.ts`) sets only `maxDiffPixelRatio` and leaves `threshold` at
  Playwright's default of **0.2** (Playwright runs the same `pixelmatch` underneath). The gallery's
  `diffRatio` (`ibl5/tests/e2e/vr-gallery.ts`) therefore passes `threshold: 0.2`
  (`GATE_PIXEL_THRESHOLD`), not pixelmatch's own stricter 0.1 default — otherwise the gallery would
  count **more** anti-aliasing / font pixels per cell than the gate, inflating the ratio above the
  gate's and surfacing sub-gate noise the ratio floor alone cannot suppress.
- **Diff-pixel ratio (how many such pixels are allowed).** The manifest row's
  `extraMaxDiffPixelRatio` when set, else the gate's global floor (`maxDiffPixelRatio: 0.005`).
  `bin/vr-build-gallery`'s `toleranceFor` returns `row.extraMaxDiffPixelRatio ?? 0.005` (never zero),
  mirroring the gate's `row.extraMaxDiffPixelRatio ?? this default`.

Without these two corrections the gallery triaged every cell at pixelmatch's stricter per-pixel
threshold **and** at **zero** ratio tolerance, so sub-floor anti-aliasing / font-rendering noise that
the gate correctly ignores (the gate was **green**) still surfaced ~70 "changed" cells — a confusing
green-check / noisy-gallery split. Parity only suppresses **sub-gate** noise: because the "before" is
master's **committed** baseline (`git show`), a real >0.005 delta still shows even when the gate is
green from an in-branch regen, so parity does not defeat the gallery's purpose. The ratio floor also
applies to the self-stability (A-vs-B) check, so a reload differing from its own first render by
<0.5% is no longer demoted as a flake — intended, since sub-0.5% reload jitter is not a real flake.

**Global-change banner:** the coverage map (`ibl5/tests/e2e/vr-coverage-map.ts`) no longer fans the
gallery. A shared CSS/theme/class change (a `GLOBAL_GLOBS` hit, including `ibl5/classes/**`) trips a
**standalone** "global change detected" coverage heads-up banner — shown whenever such a file
changed, independent of whether any row diffed. Coverage drives **only** the banner, never gallery
cell selection.

## Alternatives Considered

- **Keep failure-driven, but snapshot the report before regen.** Rejected: it races the regen step
  and still loses the evidence whenever the comparison passes for any other reason (e.g. a
  pre-refreshed baseline). Decoupling from the check outcome is the only robust fix.
- **Read "before" from the working-tree baseline instead of `git show`.** Rejected: that is exactly
  the blob a regen overwrites — the regression this ADR exists to fix.
- **Single after-capture (no reload B).** Rejected: without a second render there is no way to tell a
  genuine change from a self-unstable render, reintroducing the false "changed" flakes ADR-0073
  fought.

## Consequences

- **Positive:** The gallery records the PR's actual visual delta against shipped `master` even after
  baselines are regenerated into the branch — the evidence no longer vanishes on `update-baselines`.
- **Positive:** Publishing on pass OR fail means the before/after gallery exists for every visual PR,
  not only failing ones; a reviewer always has the side-by-side.
- **Positive:** The triage and comment builders are pure and unit-tested; classification is
  mechanized, not trusted.
- **Negative:** The "before" read depends on the base SHA's snapshot path layout
  (`…-snapshots/<title>.png`); a future snapshot-naming change needs a matching
  `bin/vr-build-gallery` update (covered by the unit tests, which would go red).
- **Negative:** Each cell now renders twice (A + reload B), modestly increasing VR run time.
- **Negative (two sync points):** Gate parity is mirrored in **two** places, neither
  single-sourced from the Playwright config:
  - `GATE_MAX_DIFF_PIXEL_RATIO = 0.005` in `bin/vr-build-gallery` mirrors the gate's
    `maxDiffPixelRatio`.
  - `GATE_PIXEL_THRESHOLD = 0.2` in `ibl5/tests/e2e/vr-gallery.ts` mirrors the per-pixel `threshold`
    the gate leaves at Playwright's default.

  Both literals are stable, so a mirror with a cross-reference comment was chosen over a new import
  direction into the Playwright config; if the gate's `maxDiffPixelRatio` or `threshold` ever
  changes, the matching mirror must move with it. Single-sourcing them is a fine follow-up but was
  out of scope for this go-green fix.

## References

- `bin/vr-build-gallery` — reads actuals + `git show <base.sha>:<snapshot>`, triages, writes
  `gallery.json` + `index.html`.
- `ibl5/tests/e2e/vr-gallery.ts` — pure `triageCell` / `buildGalleryHtml`.
- `ibl5/tests/e2e/vr-review-comment.ts` — `buildComment` consuming pre-classified cells.
- `ibl5/tests/e2e/vr-coverage-map.ts` — `classifyChangedFiles`; `globalChange` now feeds only the banner.
- `ibl5/tests/e2e/smoke/visual-regression.spec.ts` — writes `.a.png` + reload `.b.png` to `vr-actuals`.
- `ibl5/docs/decisions/0073-vr-infra-vs-pixel-failure-labeling.md` — the infra/flake labeling this reuses.
- `ibl5/docs/decisions/0069-vr-comment-new-vs-changed.md` — the NEW-vs-CHANGED split, now in the gallery builder.
