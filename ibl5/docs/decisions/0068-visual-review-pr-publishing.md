---
description: Publish the Playwright visual-regression HTML report to per-SHA GitHub Pages and post a sticky PR comment (grouped by module, with a coverage-gap section) so visual-change PRs are reviewed from the PR itself.
last_verified: 2026-07-03
---

# ADR-0068: Visual-review publishing for visual-change PRs

**Status:** Accepted
**Date:** 2026-06-22

## Context

Reviewers of visual-change PRs manually load each changed page in the Docker preview
at mobile and desktop sizes to eyeball before/after — slow, easy to skip a page, and
unrecorded. The visual-regression (VR) engine already computes before/after/diff per
module × viewport (the committed baseline is the "before" / `-expected.png`, the PR
render is the "after" / `-actual.png`, and Playwright emits a `-diff.png`), and the
native Playwright HTML report renders all three with an interactive slider — but the
result is buried in an uploaded artifact zip. A standard fix is a hosted visual-review
SaaS (Percy/Argos/Chromatic), rejected to preserve the self-hosted, no-external-egress
posture (ADR-0046/0062 lineage of in-repo tooling).

This change also touches a **mechanical-enforcement surface**: the changed-files →
manifest-coverage selection logic. `.claude/rules/lighthouse-pr-comments.md` establishes
that changing PR-comment selection logic requires an ADR.

## Decision

Publish the existing Playwright HTML report to per-SHA GitHub Pages (a `gh-pages`
branch, one `<sha>/visual-review/` subdirectory per commit, with a retention/cleanup
job) on any PR where VR detects diffs, and post a sticky `visual-review` PR comment
linking into it, grouped by module, covering desktop + mobile. The comment **must**
explicitly enumerate any changed page NOT covered by `vr-manifest.ts` so a coverage
gap can never masquerade as "nothing to review."

Sign-off reuses the existing `update-baselines` label (no new approval mechanism):
applying it regenerates baselines and the auto-commit is the durable approval record.
The publish/comment steps run **before** baseline regeneration to preserve the
before/after (ordering invariant). Changed-files → manifest-coverage selection
(`tests/e2e/vr-coverage-map.ts`) and the comment markup (`tests/e2e/vr-review-comment.ts`)
are pure, unit-tested functions; changing that selection logic is itself a
mechanical-enforcement surface and requires a future ADR.

Per-SHA accumulation uses `peaceiris/actions-gh-pages@v4` with `keep_files: true` and
`destination_dir: <sha>/visual-review` to push each commit's gallery onto the `gh-pages`
branch, which is the durable accumulator of every open PR's gallery. Serving is via GitHub
Actions (Pages source = GitHub Actions, `build_type: workflow`) — see ADR-0078, which amends
the serving layer of this decision.

## Alternatives Considered

- **Hosted visual-review SaaS (Percy/Argos/Chromatic)** — Rejected: lower build effort
  but adds an external dependency and egresses screenshots, against the repo's
  self-hosted/Docker posture (ADR-0046/0062 lineage).
- **`actions/deploy-pages` uploading only the current run's gallery** — Rejected: a single
  fresh-artifact deploy of just this SHA's output replaces the whole site and cannot host
  concurrent SHAs. ADR-0078 instead uploads the ENTIRE `gh-pages` tree as the artifact, so
  `actions/deploy-pages` serves every accumulated SHA at once — coexistence is preserved.
- **A new opt-in `visual-review` label to trigger publishing** — Rejected: the comment is
  only built when VR detects diffing cells, so it is self-scoping; a label would add a
  step the reviewer must remember.

## Consequences

- Positive: visual-change PRs carry an actionable inline review surface; a reviewer signs
  off from the PR instead of loading each page in the Docker preview.
- Positive: the coverage-gap section makes a changed-but-unreviewed page impossible to miss.
- Negative: visual-change PRs sit red until a human applies `update-baselines` (by design).
- Negative: the Pages source must be set to **GitHub Actions** (`build_type: workflow`) — a
  one-time owner action (repo Settings → Pages → Build and deployment → Source → GitHub Actions,
  or `gh api --method PUT repos/a-jay85/IBL5/pages -f build_type=workflow`). See ADR-0078. Until
  it is flipped, the `Deploy VR gallery to Pages` `workflow_run` runs go red at the deploy step
  (expected; self-clears once flipped).
- Negative: fork PRs receive a read-only token with no secrets and cannot post the comment
  or push Pages — acceptable for this single-owner repo (consistent with the existing
  secrets-in-`pull_request` E2E model). The steps no-op there.

## References

- `.github/workflows/e2e-tests.yml` — the `e2e` job's publish/comment steps (gated to the
  review run, before regen) and the `vr-pages-cleanup` retention job.
- `.github/workflows/pages-deploy.yml`, `ibl5/docs/decisions/0078-vr-pages-actions-serving.md` —
  the `workflow_run`-triggered Actions deploy that serves the whole `gh-pages` tree, and the ADR
  that amends this decision's serving layer.
- `ibl5/tests/e2e/vr-coverage-map.ts` — pure changed-files → manifest-coverage mapper.
- `ibl5/tests/e2e/vr-review-comment.ts` — pure sticky-comment markdown builder.
- `bin/vr-changed-coverage`, `bin/vr-review-comment` — CLI wrappers consumed by the workflow.
- `ibl5/tests/e2e/vr-manifest.ts` — `sourceGlobs` field powering coverage selection.
- `.claude/rules/visual-review-prs.md` — the operator-facing rule for this surface.
- `.claude/rules/lighthouse-pr-comments.md` — the precedent that PR-comment selection logic is a mechanical-enforcement surface.
- `bin/lighthouse-comment`, `.github/workflows/lighthouse.yml` — the sticky-comment pattern modeled here.
