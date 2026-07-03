---
description: Serve the accumulated per-SHA visual-review gh-pages tree via a workflow_run-triggered GitHub Actions Pages deploy (build_type: workflow), replacing the stuck legacy Jekyll branch-build that 404'd.
last_verified: 2026-07-03
---

# ADR-0078: Serve the VR gallery via Actions (workflow_run → deploy-pages), not legacy Jekyll

**Status:** Accepted
**Date:** 2026-07-02

## Context

ADR-0068 publishes each PR's visual-review gallery to a `gh-pages` branch under
`<sha>/visual-review/` (via `peaceiris/actions-gh-pages@v4`, `keep_files: true`) and set the
Pages **source to the `gh-pages` branch** (legacy `build_type`). That legacy Jekyll branch-build
got stuck (`status: building`) and returned **404** for the site root and every
`<sha>/visual-review/` URL — the accumulated per-SHA content was intact, but the serving layer
was dead. ADR-0068 also recorded "`actions/deploy-pages` rejected because it replaces the whole
site with one latest deploy" — true only when the artifact is one run's output.

## Decision

Set the Pages source to **GitHub Actions** (`build_type: workflow`) and serve the **whole
`gh-pages` tree — no Jekyll** — via a new `.github/workflows/pages-deploy.yml`. It is triggered by
`workflow_run` on the `E2E Tests` workflow completing (plus `workflow_dispatch`), checks out
`gh-pages`, uploads the **entire** tree as the Pages artifact (`actions/upload-pages-artifact`,
`path: .`), and deploys it (`actions/deploy-pages`) — so **every** accumulated
`<sha>/visual-review/` is served at once. The `gh-pages` branch stays the durable per-SHA
accumulator; the `peaceiris` push and the `vr-pages-cleanup` prune job are unchanged; the per-SHA
URL scheme (`https://a-jay85.github.io/IBL5/<sha>/visual-review/`) is unchanged.

## Alternatives Considered

- **Keep the legacy `gh-pages` branch build** — Rejected: the Jekyll build stalls on a large
  branch and serves 404; it is the reported bug.
- **`actions/deploy-pages` uploading only the current run's gallery** — Rejected: a single
  fresh-artifact deploy of one SHA replaces the whole site and cannot host concurrent SHAs (this
  is ADR-0068's original rejection). Uploading the ENTIRE `gh-pages` tree instead preserves
  per-SHA coexistence.
- **A `push: [gh-pages]`-triggered deploy workflow** — Rejected: `gh-pages` is a `peaceiris`
  orphan branch with no `.github/workflows/` directory, so a push there never runs a workflow (a
  push-triggered workflow is read from the pushed ref); a push-context run would also violate the
  `github-pages` environment's default-branch protection. `workflow_run` runs from the default
  branch, sidesteps both, and needs no `CI_PAT`.
- **In-job serve inside the required `e2e` job** — Rejected: it puts `environment: github-pages`
  (which GitHub serializes) on the required gate, coupling gate latency to Pages deploys and
  turning every pre-flip PR gate red. `workflow_run` keeps serving out of the required gate.

## Consequences

- Positive: the VR gallery serves again, with every open PR's `<sha>/visual-review/` coexisting.
- Positive: no Jekyll build to stall; each deploy re-publishes the current accumulated tree, so
  `vr-pages-cleanup` prunes reach the served site on the next master-run deploy.
- Negative: the Pages source must be flipped to **GitHub Actions** (`build_type: workflow`) once,
  by the owner (`gh api --method PUT repos/a-jay85/IBL5/pages -f build_type=workflow`, or Settings
  → Pages). `workflow_run` reads the workflow from the default branch, so `pages-deploy.yml` only
  runs after it is on `master`; pre-flip runs go red at the deploy step (expected; self-clears).

## Lineage

Amends the **serving-layer** decision of ADR-0068 (Visual-review publishing). ADR-0068 otherwise
stands in full — change-driven gallery selection, per-SHA `peaceiris` accumulation onto `gh-pages`,
the sticky comment, and the `update-baselines` sign-off are all unchanged. This ADR replaces only
"Pages source = the `gh-pages` branch (legacy build)" with "Pages source = GitHub Actions serving
the whole `gh-pages` tree," and retires ADR-0068's "`actions/deploy-pages` rejected" reasoning
(which assumed a single-run artifact). Deliberately NOT modeled as `## Supersedes`: ADR-0068 is not
retired, only its serving mechanism is amended, so no reciprocal `Status: Superseded by` is claimed.

## References

- `.github/workflows/pages-deploy.yml` — the `workflow_run`-triggered Actions deploy that serves
  the whole `gh-pages` tree (this ADR).
- `ibl5/docs/decisions/0068-visual-review-pr-publishing.md` — the amended decision.
- `.github/workflows/e2e-tests.yml` — the `peaceiris` push and `vr-pages-cleanup` job that
  accumulate/prune the `gh-pages` tree, both unchanged.
- `.claude/rules/visual-review-prs.md` — operator-facing rule (Pages-source prerequisite, serve path).
