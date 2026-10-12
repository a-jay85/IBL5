---
description: Adds a review-only strict pixel pass to the visual-review gallery triage so sub-gate recolors surface as changed cells, amending ADR-0074.
last_verified: 2026-10-07
---

# ADR-0180: Review-only strict triage for the visual-review gallery

**Status:** Accepted
**Date:** 2026-10-07
**Amends:** ADR-0074 (change-driven gallery selection)

## Context

PR #2599 recoloured six selectors by one palette step and the gallery came back empty: `.ibl-search__btn` background, `.leaders-tabbed__leader-value` and `.news-article__link` text (accent-500 to accent-700, hover accent-600 to accent-800), and `.leaders-tabbed__runner-rank`, `.h2h-unplayed`, and `.updater-section__label` (gray-400 to gray-500). The commit body also mentions navy-900, an underline, a 1.75rem to 2.25rem size change, and `last-sim-recap.css`. Those belong to an earlier draft and are absent from the merged diff.

ADR-0074 triages each cell with the gate's own pixelmatch threshold (0.2) and ratio floor (0.005), so the gallery shows what the gate sees. A pixel counts only when its YIQ delta exceeds 35215 times the threshold squared. For the #2599 pairs on white, the threshold above which a pixel stops counting is:

| Pair | full | 0.75 | 0.5 | 0.25 coverage |
|---|---|---|---|---|
| gray-400 to gray-500 | 0.1848 | 0.1393 | 0.0939 | 0.0455 |
| accent-500 to accent-700 | 0.1856 | 0.1398 | 0.0934 | 0.0458 |
| accent-600 to accent-800 (hover) | 0.2042 | 0.1531 | 0.1019 | 0.0512 |

Root-cause replay per element:

- Every resting-state recolor is below 0.2 at full coverage, so the gate threshold counts zero pixels. The threshold is the cause for all six selectors.
- Masks are not the cause. On `index`, `div.news-article__body` closes before the footer that holds `.news-article__link`, and the `news` and `news-article` rows mask only `article time`.
- Seed data is not the cause. The CI seed already renders the League Leaders block (`nuke_blocks` plus `ibl_plr` rows with `stats_gm > 0`) and the last-sim recap Box score link (`ibl_sim_dates` sim 689, `ibl_box_scores_teams` with `game_of_that_day = 1`).
- Only the hover pair clears 0.2, and hover states are never captured. Even when pixels count, the 0.005 ratio absorbs a short text run on a full-page shot.
- `.updater-section__label` renders only on LeagueControlPanel, which has no manifest row. It is a coverage gap outside this decision.

## Decision

Add a second, review-only comparison to `triageCell` in `ibl5/tests/e2e/vr-gallery.ts`, enabled per cell by `bin/vr-build-gallery`.

- Constants: `STRICT_PIXEL_THRESHOLD = 0.05` and `STRICT_MIN_CHANGED_PIXELS = 25`. Both are exported. 0.05 counts solid and half-covered glyph pixels of a one-step recolor (0.0917 and above) and ignores quarter-coverage anti-alias fringes (0.0455). The absolute floor replaces the ratio, because 0.005 of a full-page shot is thousands of pixels.
- Monotonic: the strict pass can only upgrade a gate-parity `unchanged` to `changed`, flagged `strict: true` inside the script. It never alters `changed`, `flake`, `new`, or `infra`, and it never produces `flake`.
- Self-stability still applies. The upgrade requires the reload render B, and A and B must agree under the strict threshold (fewer than 25 differing pixels). A cell that is unstable under the strict threshold keeps its gate-parity verdict.
- Rows that declare `extraMaxDiffPixelRatio` are known-noisy and keep gate-parity triage only.
- The VR gate is untouched: `threshold` 0.2, `maxDiffPixelRatio` 0.005 in `ibl5/playwright.visual.config.ts`, and every `toHaveScreenshot` call. The strict pass feeds only `gallery.json`, whose shape and the `vr-build-gallery:` summary line stay byte-compatible.

## Alternatives Considered

- **Lower the gate.** Cut the gate threshold or `maxDiffPixelRatio`. It would detect the recolors. Rejected because it turns cross-run anti-alias noise into red CI.
- **Non-monotonic strict triage.** Re-run the whole verdict ladder at 0.05. Rejected because it would mint strict-only flake cells and could reclassify a loose `changed`, which breaks consumers of the verdict sets.
- **New seed rows.** Add rows so the touched panels render. Rejected because the seed audit found every touched panel already rendered. E2E guards pin that dependency instead.

## Consequences

- Positive: small recolors now reach the sticky comment as ordinary changed cells. The CI check colour cannot change, because the strict pass never runs in the gate.
- Positive: ADR-0074 named two sync points with the gate (threshold and ratio floor). The strict constants deliberately do not mirror the gate and need no sync.
- Negative: cross-run rendering drift between the baseline run and the PR run (font hinting, runner image updates) can now surface as changed cells. The A-versus-B guard cannot catch that drift, since both renders come from the same run. The 25-pixel floor and the 0.05 threshold bound it. If it becomes noisy, raise the floor or opt the row out through `extraMaxDiffPixelRatio`.

Calibration is pinned by synthetic-PNG unit tests built from the real palette hex values in `ibl5/tests/ts-unit/vr-gallery.test.ts`, and end to end by `ibl5/tests/ts-unit/vr-build-gallery.test.ts`.

## References

- `ibl5/docs/decisions/0074-vr-change-driven-review.md`
- `ibl5/tests/e2e/vr-gallery.ts`
- `bin/vr-build-gallery`
- `ibl5/tests/e2e/smoke/vr-seed-coverage.spec.ts`
- `.claude/rules/visual-review-prs.md`
