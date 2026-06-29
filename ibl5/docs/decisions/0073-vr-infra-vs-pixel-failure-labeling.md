---
description: The visual-review comment classifies a failed VR cell as a genuine pixel diff only when it carries a *-diff.png screenshot attachment; failed cells with no triplet are labeled navigation/render failures with a re-run remedy, never "changed pixels / apply update-baselines".
last_verified: 2026-06-29
---

# ADR-0073: Distinguish infra/render failures from pixel diffs in the visual-review comment

**Status:** Accepted
**Date:** 2026-06-28

## Lineage

Extends **ADR-0068** (per-SHA Pages publishing) and **ADR-0069** (NEW-vs-CHANGED comment
classification) — does NOT supersede them. Those decisions assumed every failing VR cell is a
pixel change. This ADR adds a prior split: pixel diff vs infra/render failure.

## Context

The `visual-review` comment keyed solely on `spec.ok === false`, so ANY failed VR cell —
including a `page.goto` navigation timeout (the PHP built-in server intermittently returns blank
HTML under load) — was rendered as a "changed pixels" cell under a headline instructing the
reviewer to apply the `update-baselines` label. For a navigation timeout this is wrong twice: the
run captured no before/after/diff images (the screenshot step never ran), and regenerating
baselines cannot fix a timeout. PR #1107's stale report showed all 12 "changed views" were in fact
identical `page.goto` timeouts with zero pixel-diff attachments.

The Playwright JSON report already distinguishes the two: a genuine screenshot mismatch attaches
`<title>-expected.png` / `<title>-actual.png` / `<title>-diff.png`, while a navigation/error
failure attaches only `error-context`. The bot was not reading attachments.

This changes the comment selection/markup logic, a mechanical-enforcement surface per
`.claude/rules/visual-review-prs.md`, so an ADR is required.

## Decision

Classify each failed VR cell by its attachments:

- **Pixel diff** ⇔ the cell carries a `*-diff.png` attachment. These render in the existing
  per-module "changed view(s)" / "new view(s)" sections under the "This PR changed pixels … apply
  `update-baselines`" headline.
- **Infra/render failure** ⇔ failed but with attachments and no `*-diff.png`. These render in a
  separate `⚠️ N view(s) failed to render (navigation/error — not a pixel change)` section whose
  remedy is **re-run the VR job**, explicitly NOT regenerating baselines.
- **Legacy fallback:** a failed spec with no attachment data at all is treated as a pixel diff,
  preserving the prior `{title, ok}`-only behavior. Real reports always attach ≥1 artifact on
  failure, so this affects only synthetic test fixtures.

The "This PR changed pixels" headline and the "Global change detected" banner render only when at
least one genuine pixel-diff cell exists, so an infra-only failure never points the reviewer at the
wrong remedy.

This is paired with a navigation-robustness change: the VR spec swaps raw `page.goto` for the
existing non-masking `gotoWithRetry` helper, so transient navigation timeouts self-heal to green
rather than producing infra-failure cells at all. The classifier handles the residue. VR still goes
red on any genuine failure (a non-transient broken page exhausts the retries; a real pixel diff
fails the comparison) — neither the retry nor the relabeling auto-greens a real failure.

## Alternatives Considered

- **Add Playwright `retries` instead of `gotoWithRetry`.** Rejected: a screenshot-comparison retry
  re-runs `toHaveScreenshot` and can mask a flaky-render pixel diff; the codebase already
  standardized on the non-masking `gotoWithRetry` for navigation robustness.
- **Auto-pass VR on an infra failure.** Rejected: it would hide a genuinely broken page. VR staying
  red on a real failure is correct; the fix is fewer false reds plus the right remedy message.
- **A new standalone bin script.** Rejected: `bin/vr-review-comment` already owns the report parse;
  the classifier extends the existing pure `vr-review-comment.ts` seam.

## Consequences

- **Positive:** A navigation timeout is labeled as flake with a re-run remedy, never as a pixel
  change demanding baseline regeneration.
- **Positive:** The classifier is pure and unit-tested; the gating is mechanized, not trusted.
- **Negative:** The classifier depends on Playwright's attachment naming (`*-diff.png`); a future
  Playwright change to attachment names would need a corresponding update (covered by the unit
  tests, which would go red).

## References

- `ibl5/tests/e2e/vr-review-comment.ts` — `extractCells`, `specHasPixelDiff`, `buildComment`.
- `ibl5/tests/ts-unit/vr-review-comment.test.ts` — classification + gating tests.
- `ibl5/tests/e2e/smoke/visual-regression.spec.ts` — `gotoWithRetry` navigation.
- `ibl5/playwright.visual.config.ts` — `trace: 'retain-on-failure'`.
- `ibl5/docs/decisions/0069-vr-comment-new-vs-changed.md` — the classification this extends.
