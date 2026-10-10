---
description: On a visual-change PR, the visual-regression run builds a change-driven before/after gallery (rows whose render differs from master's committed baseline) and posts a sticky visual-review PR comment grouped by module, with a "changed but NOT covered" coverage-gap section. A daily and on-demand refresh workflow republishes stale galleries, and retention cleanup keeps every gallery dir an open PR links to. Changed cells and agent shots also get cropped before/after pairs in the PR body.
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
  - "bin/vr-review-comment"
  - "ibl5/tests/e2e/vr-manual-rows.ts"
  - "ibl5/tests/e2e/manual-rows.spec.ts"
  - "ibl5/playwright.manual-rows.config.ts"
last_verified: 2026-10-08
---

# Visual-review PRs

See ADR-0068, ADR-0069, ADR-0073, ADR-0074, and ADR-0076 for the decisions and rationale. ADR-0074
is the current gallery-selection model: the gallery is **change-driven** (built from rows whose PR
render differs from master's committed baseline), not failure-driven. ADR-0076 extends it: brand-new
views (`gallery.newCells`) are additionally published inline at the top of the PR body, not only in
the sticky comment. ADR-0181 extends ADR-0076 to changed cells, agent shots and manual-row befores.

## What runs

On a PR run of the visual-regression (VR) step in `.github/workflows/e2e-tests.yml` (the `e2e`
"Visual Regression" job) — whenever the `update-baselines` label is **absent** — five steps fire,
in order, AFTER the VR run and BEFORE baseline regen. They fire on **pass OR fail**: selection is
decoupled from the VR check outcome (ADR-0074), so the gallery publishes even when VR is green.
They are skipped only during baseline regen (the `update-baselines` label).

Under that label the publish steps still run (ADR-0181): gallery build, crop, deploy and retries, assert, re-serve and splice. Each has label-true `continue-on-error`, so regen always runs. Guard: `ibl5/tests/ts-unit/vr-workflow-guard.test.ts`.

1. **Compute coverage** — `git diff --name-only <base>...HEAD | bin/vr-changed-coverage`
   maps the changed files onto `vr-manifest.ts` rows (uncovered website paths and a global-change
   flag for shared CSS/theme/class edits). Coverage drives **only** the banner now — never the
   gallery cell set.
2. **Build gallery** — `bin/vr-build-gallery` reads the raw actuals the VR spec wrote to
   `ibl5/vr-actuals` (each cell captured twice: render `.a.png` + reload `.b.png`), reads master's
   committed baseline for each row via `git show <base.sha>:<snapshot-path>` (regen-immune), triages
   each row, and writes the static side-by-side `index.html` (with `<title>` anchors) + per-SHA
   artifacts into `ibl5/vr-gallery`, plus the pre-classified `gallery.json`
   (`changedCells`/`newCells`/`flakeCells`).
3. **Deploy gallery to per-SHA Pages** — pushes `ibl5/vr-gallery` to the `gh-pages` branch under
   `<sha>/visual-review/` (multiple open PRs/SHAs coexist). The Playwright HTML report (traces) is
   preserved under `<sha>/visual-review/playwright-report/`. The `gh-pages` branch is only the
   durable accumulator; the site is **served** by `.github/workflows/pages-deploy.yml` (Pages
   source = GitHub Actions, no Jekyll), which is dispatched by the deploy step below once the
   gh-pages push lands (one deploy per real content change, not one per E2E run) and re-publishes the
   whole tree. That dispatch is **debounced**: it is skipped when a `pages-deploy` run exists that has
   not started yet, since that run checks out `gh-pages` *after* this push and already serves it. The
   status filter is a **denylist** (neither `completed` nor `in_progress`), so a missed spelling
   over-suppresses one push instead of silently no-opping. The debounce never fires on an
   `in_progress` run (it may have checked out `gh-pages` first) and fails **open**: an API error
   dispatches. A not-started run older than an hour counts as stuck. The step cancels it and leaves it
   out of the count, so one stuck run cannot block every later deploy. This stops a push fan-out (one
   master push → multiple open-PR updates → N gh-pages pushes) from producing N deploys that the `pages`
   concurrency group would mostly cancel. It collapses to roughly **two** deploys (one in-flight
   plus one pending). Every open PR's VR job pushes to the same `gh-pages` ref, so concurrent runs
   collide on the ref lock. The deploy runs **one attempt plus two retries** (each re-clones
   `gh-pages`, so a retry sees the ref that beat it). An **assert step** fails the job if all three
   fail. The retries absorb contention and never soften the gate.
4. **Build comment** — `bin/vr-review-comment` consumes the pre-classified `gallery.json` and renders
   the sticky markdown.
5. **Post sticky comment** — `marocchino/sticky-pull-request-comment@v3`, header `visual-review`.

A `vr-pages-cleanup` job (push-to-master only, not part of the required gate) prunes
per-SHA gallery dirs whose newest commit is older than 7 days. Its `gh-pages` push carries the same
rebase-and-retry loop for the same ref contention. A prune reaches the served site when
`pages-deploy.yml` next re-publishes the tree (the cleanup job dispatches `pages-deploy.yml` itself once its prune push lands).
It keeps every dir an open PR still links to. See [Refreshing stale galleries](#refreshing-stale-galleries).

## Refreshing stale galleries

`.github/workflows/vr-refresh.yml` republishes the gallery, both sticky comments, and the PR-body block for open visual PRs whose screenshots went stale. It calls the `Visual Regression` job of `e2e-tests.yml` through its `workflow_call` inputs.

- **Daily schedule.** Refreshes each open visual PR whose linked gallery dir is older than 7 days. At most 10 PRs per sweep, 2 at a time.
- **Manual dispatch.** `gh workflow run vr-refresh.yml -f pr=<N>` refreshes one PR. A blank `pr` refreshes every open visual PR, with the same cap. Add `-f dry_run=true` to list what would refresh and what cleanup would keep.
- **Visual PR.** A PR whose `visual-review` sticky comment links a gallery, as `<sha>/visual-review` or `pr/<N>/visual-review`. The first link wins. The banner-only comment links none and is never refreshed.
- **Age.** The `gh-pages` commit time of the dir that comment links to (`<sha>` or `pr/<N>/visual-review`). Every publish writes `refreshed-at.txt` into the dir, so a refresh always moves the age forward.
- **Skipped PRs.** Forks, PRs labeled `update-baselines`, drafts (unless named by `pr`), PRs whose base is not `master`, and PRs behind `master`. A push republishes a behind PR anyway (ADR-0182 gives the reason).
- **Publish key.** A refresh publishes under the PR head SHA and rewrites the links to it. A push to the PR still publishes under the test-merge SHA.
- **Never a gate.** Every write runs on a master-ref event, so a refresh adds no check run to a PR head and a failed refresh leaves required checks alone. One sweep dispatches `pages-deploy.yml` once.
- **Retention.** `vr-pages-cleanup` keeps every per-SHA dir that any open PR's comments or body link to, plus each open PR's head SHA, through both the 7-day age pass and the 300-dir cap. When the keep-list cannot be computed, that run prunes nothing. The keep-list holds 40-hex SHAs only, since `bin/prune-vr-galleries` never prunes `pr/<N>/` dirs.
- **Pull-request dry-run.** A PR that edits the refresh workflow, `bin/vr-refresh-targets`, `ibl5/tests/e2e/vr-refresh.ts`, or `bin/prune-vr-galleries` runs the read-only `VR refresh select` job, which prints both lists.

## Reading the comment

- The comment makes **no claim about the check's red/green color.** In the regen-into-branch steady
  state the VR gate can be **green** while the gallery still shows **changed** cells (the in-branch
  baseline was refreshed, but the render still differs from master's committed baseline). The prose
  is true in both states — it never says the check "stays red until then."
- Diffing views are grouped per module in `<details>` blocks; each link points into the static
  side-by-side gallery (the `<title>` anchor) where the master-vs-PR before/after shows desktop +
  mobile. The Playwright report (traces) is preserved under `…/playwright-report/`.
- A **"🆕 New views (no prior baseline — review the render)"** section lists cells whose baseline was
  never committed (a brand-new VR row). These have no before — the link shows the first render;
  sanity-check it, then `update-baselines` commits it as the baseline. NEW cells are excluded from the
  "changed view(s)" count so a first-render never reads as a regression. The NEW-vs-CHANGED split
  (ADR-0069) is computed in `bin/vr-build-gallery` from master's committed baseline set, not from
  disk.
- The per-SHA Pages URL shape is `https://a-jay85.github.io/IBL5/<sha>/visual-review/`.
- A **"⚠️ Changed but NOT covered by the VR manifest"** section lists changed website paths
  that match no manifest row — review those by hand or add a `vr-manifest.ts` row. A
  **global-change banner** appears as a standalone coverage heads-up whenever a shared
  CSS/theme/class file changed, independent of whether any row diffed.

## Approving (sign-off)

Apply the **`update-baselines`** label. That regenerates the baselines, auto-commits them,
and re-runs VR green — the auto-commit is the durable approval record. There is no separate
approval mechanism. Because the gallery reads master's committed baseline via
`git show <base.sha>:…`, the before/after evidence survives this regen instead of vanishing.

## When the comment is missing

- Fork PR: the read-only token has no secrets, so the publish/comment steps no-op (expected).
- No render diffs and no global change: nothing to review, no comment.

## Self-stability (flake) vs real change

See `.claude/rules/visual-review-prs-detail.md` § Self-stability.

## New screens in the PR body

In addition to the sticky comment, brand-new views (`gallery.newCells`) are published inline at the
top of the PR body itself (ADR-0076) — no click required to see a first render. Two extra workflow
steps run after "Deploy gallery to per-SHA Pages" and "Post sticky comment":

- **Copy new-screen renders** — `bin/vr-review-comment --copy-new-screens=DEST` copies each new
  cell's first-render PNG into a `new-screens/` subdirectory of the Pages deploy tree.
- **Splice into the PR body** — `bin/vr-review-comment --update-pr-body=<PR#>` polls the first
  new-screen image URL for readiness (bounded, ~30s), then splices a marker-delimited
  (`<!-- vr-new-screens:begin/end -->`), idempotent block at offset 0 of the PR body via
  `gh pr edit --body-file`. The block self-removes once `newCells` is empty (baseline committed via
  `update-baselines`), so it never goes stale. `--dry-run` exercises the splice without mutating a
  real PR.

Both steps are `continue-on-error: true`. A failure here only means no inline image. The VR job
and sticky comment still succeed. The pure splice/copy-plan logic lives in
`ibl5/tests/e2e/vr-pr-body.ts` (unit-tested in `ibl5/tests/ts-unit/vr-pr-body.test.ts`); only `gh`/
`fetch`/`fs` I/O lives in the `bin/vr-review-comment` glue layer.

## Changed screens and agent shots in the PR body

See `.claude/rules/visual-review-prs-detail.md` (ADR-0181).

## Modifying the selection logic

Gallery cell selection lives in `bin/vr-build-gallery` and `ibl5/tests/e2e/vr-gallery.ts`
(`triageCell`, `buildGalleryHtml`); changed-files → coverage (banner only) lives in
`ibl5/tests/e2e/vr-coverage-map.ts` (`classifyChangedFiles`, `deriveModuleGlob`, `rowGlobs`); the
comment markup lives in `ibl5/tests/e2e/vr-review-comment.ts` (`buildComment`). The pure modules are
unit-tested (`ibl5/tests/ts-unit/vr-gallery.test.ts`, `ibl5/tests/ts-unit/vr-coverage-map.test.ts`,
`ibl5/tests/ts-unit/vr-review-comment.test.ts`, run via `bun run test:unit` from `ibl5/`). Per-row
source overrides use the optional `sourceGlobs` field on `VrRow`. **Changing this selection logic is
a mechanical-enforcement surface and requires an ADR** (current: ADR-0074, amended by ADR-0180 for the review-only strict pass). The PR-body new-screens
publishing surface (`--copy-new-screens`/`--update-pr-body` on `bin/vr-review-comment`,
`ibl5/tests/e2e/vr-pr-body.ts`, `ibl5/tests/ts-unit/vr-pr-body.test.ts`) is likewise a
mechanical-enforcement surface, covered by **ADR-0076**. The crop modules (`ibl5/tests/e2e/vr-crop.ts`,
`ibl5/tests/e2e/vr-crop-png.ts`), `--crop-changed` and `--publish-agent-shots` are covered by
**ADR-0076** and **ADR-0181**.

## Manual-row screenshots

A **Truly-manual** Verification-Matrix row about look and feel carries a `vr:` cell in its location
column (`bin/check-plan` gate `[Q]`; ADR-0126), so CI can screenshot the thing the human is being
asked to judge:

```
vr: label=team-page-header; role=anon; url=modules.php?name=Team&op=view&teamID=1; anchor=.ibl-title; setup=DELETE test-state.php?action=clear-throttle
```

`label=` is a kebab slug, `role=` is `anon|regular|admin`, `url=` is relative to the app root, and
`anchor=` is the selector waited on before the shot. Zero or more `setup=<GET|POST|DELETE> <path>`
clauses drive `ibl5/test-state.php` into the state the shot needs. An unknown `action=` there
answers **400**, so a typo surfaces in the PR comment as a failed row instead of a wrong
screenshot. Clauses are `;`-separated because `|` would break the matrix table. A row that
cannot be shot (print CSS, an email render) uses `no-vr: <reason ≥ 15 chars>` instead.

The pipeline is a **review aid**. Every step below is `continue-on-error: true` and
runs in its own config so a bad `vr:` cell can never turn the baseline-diff step red:

| Stage | Where |
|---|---|
| Parse the PR body's `## Manual Testing` bullets | `bin/vr-review-comment --manual-rows-from-pr=N --out-json=ibl5/vr-manual-rows.json` |
| Capture one PNG per row | `ibl5/playwright.manual-rows.config.ts` + `ibl5/tests/e2e/manual-rows.spec.ts` → `ibl5/vr-manual-shots/<label>.png` |
| Publish | copied into the gallery deploy tree, served at `<pages-url>manual-rows/<label>.png` |
| Post | `bin/vr-review-comment --manual-gallery=…` under sticky header `manual-row-screenshots` |

Capture runs **after** the baseline diff has shot its actuals, so a `setup=` mutation cannot
invalidate a baseline; the pure grammar/markup helpers live in `ibl5/tests/e2e/vr-manual-rows.ts`
(unit-tested in `ibl5/tests/ts-unit/vr-manual-rows.test.ts`). Both output paths are gitignored.

A base-SHA pass (ADR-0181) serves the base from a second PHP container, runs with
`VR_MANUAL_SIDE=before`, writes `<label>.before.png` and sets `beforeStatus`. The before shows above
the after only when `beforeStatus` is `ok`; otherwise the row stays after-only.

## One-time deployment prerequisite

GitHub Pages must be set to source = **GitHub Actions** (`build_type: workflow`) — a one-time
owner action. The gallery is served by `.github/workflows/pages-deploy.yml`, which uploads the
whole `gh-pages` tree as the Pages artifact; the `gh-pages` branch stays the durable per-SHA
accumulator. Repo Settings → Pages → Build and deployment → Source → GitHub Actions, or:

```bash
gh api --method PUT repos/a-jay85/IBL5/pages -f build_type=workflow
```

Until the source is flipped, the `Deploy VR gallery to Pages` runs go red at the deploy step
(expected; self-clears once flipped). After flipping, trigger one re-serve immediately via the
workflow's `workflow_dispatch` (Actions → Deploy VR gallery to Pages → Run workflow) instead of
waiting for the next PR.
