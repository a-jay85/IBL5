---
description: Changed VR cells are published at the top of the PR body as cropped before/after pairs, the publish steps also run under update-baselines, manual-row shots gain a base-SHA before, and agents publish their own shots through bin/vr-review-comment.
last_verified: 2026-10-08
---

# ADR-0181: Publish cropped before/after pairs of changed VR screens in the PR body

**Status:** Accepted
**Date:** 2026-10-07

## Lineage

Extends ADR-0076. It does NOT supersede it. The managed block, its outer markers, the offset-0 splice and the readiness poll stay as ADR-0076 defined them.

## Context

ADR-0076 put first renders of brand-new VR views at the top of the PR body. Changed views stayed behind a link to the per-SHA gallery, which shows full-page shots side by side. On a phone a small colour change in a full-page shot cannot be seen. On PR #2599 the reviewer could judge the change only after an agent cropped ten before/after pairs by hand and saved them to a local folder, which GitHub cannot render. The only CI image was one full-page manual-row shot with no before. The final redesign landed after `update-baselines` was applied, and the publish steps skip while that label is present, so no CI image of the final look existed.

## Decision

1. For each changed cell, CI computes the changed pixels with pixelmatch at the gate's threshold and `diffMask`, clusters them into at most three padded spots, and crops before and after to the same box. A cell whose dimensions changed, or whose change sits below the threshold, is published uncropped and labelled so.
2. The ADR-0076 managed block gains a changed section: one `###` heading per spot (module, cell, region), the before image above the after image, desktop first, mobile spots inside one `<details>` per module. A stamp with the short head SHA and the UTC run time opens the block. Spots stop at 40 entries or 40,000 characters, and a link to the full gallery follows.
3. The gallery build, crop, deploy, assert, re-serve and splice steps also run while `update-baselines` is present. Each carries `continue-on-error` that is true on a labelled run, so a publish failure can never skip the baseline regen. The before image is master's committed baseline, so the evidence survives the regen. A unit test over `.github/workflows/e2e-tests.yml` fails if any of these steps regains the label guard or loses `continue-on-error`.
4. Manual-row `vr:` shots gain a before taken from a second PHP container serving a worktree of the base SHA. Any failure falls back to the after-only shot.
5. `bin/vr-review-comment --publish-agent-shots` lets an agent push its own PNGs to `<head-sha>/visual-review/agent-shots/` on `gh-pages` and upsert them into an agent-shot sub-block that the CI refresh carries forward. Labels are reduced to `[a-z0-9-]`. Local file paths never enter a PR body.

The whole surface stays a review aid. No new step can fail a PR.

The new-screen image alt text now joins title and viewport with a middle dot (`draft-board · desktop`). The old separator was an em-dash, which the prose gate flags in committed Markdown fixtures.

## Alternatives Considered

- **Side-by-side tables.** Two images in one table row shrink to unreadable widths on a phone.
- **Bounding box from the alpha of `<title>.diff.png`.** pixelmatch paints every unchanged pixel grey, so the alpha box is the whole image.
- **A before built from base CSS only.** It misses markup changes, which are half of what a redesign touches.
- **A new `bin/` publisher for agent shots.** The meta-tooling bar asks to extend first, and `bin/vr-review-comment` already owns the managed block.

## Consequences

- PR bodies can grow by up to 40,000 characters of image markup.
- Runs with the `update-baselines` label now push to `gh-pages` too.
- Agent-shot directories sit under a real commit sha, so `bin/prune-vr-galleries` ages them out like any per-SHA directory.

## Addendum: managed block at any position (2026-10-08)

The Lineage line "the offset-0 splice ... stay as ADR-0076 defined them" no longer holds for block
position. ADR-0185 supersedes ADR-0076 on that point: `spliceBody` finds the managed block at any
position, replaces the first in place and strips later stale blocks.
