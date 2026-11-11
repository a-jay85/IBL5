---
description: VR PRs get a change-driven before/after gallery and a sticky review comment grouped by module, with a coverage-gap section. The update-baselines label commits only strict-pass-flagged views. A refresh workflow republishes stale galleries; retention keeps linked galleries. Changed cells and agent shots also get cropped before/after pairs in the PR body.
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
last_verified: 2026-11-10
---

# Visual-review PRs

See ADR-0068, ADR-0069, ADR-0073, ADR-0074, and ADR-0076 for the decisions and rationale. ADR-0074
is the current gallery-selection model. The gallery is **change-driven**, built from rows whose PR
render differs from master's committed baseline. ADR-0076 also publishes brand-new views
(`gallery.newCells`) inline at the top of the PR body. ADR-0181 extends ADR-0076 to changed cells, agent shots and manual-row befores.

## What runs

On a PR run of the visual-regression (VR) step in `.github/workflows/e2e-tests.yml` (the `e2e`
"Visual Regression" job) — whenever the `update-baselines` label is **absent** — five steps fire,
in order, AFTER the VR run. They fire on **pass OR fail** (ADR-0074), so the gallery publishes
even when VR is green. The `update-baselines` label run skips them (see § Approving).

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
3. **Deploy gallery to per-SHA Pages.** `bin/vr-dedupe-gallery` re-shapes `ibl5/vr-gallery` into a stage dir, `ibl5/vr-pages-stage` (example), which is pushed to the `gh-pages` branch under
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
   out of the count, so it cannot block later deploys. A push fan-out collapses to about **two** deploys (one in-flight, one pending). Every open PR's VR job pushes to the same `gh-pages` ref, so concurrent runs
   collide on the ref lock. The deploy runs **one attempt plus two retries** (each re-clones
   `gh-pages`, so a retry sees the ref that beat it). An **assert step** fails the job if all three
   fail. The retries never soften the gate. The per-cell before/after/diff PNGs
   are published once each to the shared `_vr-blobs/` store at the `gh-pages` root by
   `bin/vr-dedupe-gallery`, and the gallery's `index.html` refers to them there (ADR-0211).
4. **Build comment** — `bin/vr-review-comment` consumes the pre-classified `gallery.json` and renders
   the sticky markdown.
5. **Post sticky comment** — `marocchino/sticky-pull-request-comment@v3`, header `visual-review`.

A `vr-pages-cleanup` job (push-to-master only, not part of the required gate) prunes
per-SHA gallery dirs whose newest commit is older than 7 days. The same job removes `_vr-blobs/` files
no surviving gallery references once they are older than 7 days, migrates any legacy-form gallery into
the store, and runs `bin/check-vr-gallery-refs` before it pushes. Its `gh-pages` push carries the same
rebase-and-retry loop for the same ref contention. A prune reaches the served site when
the cleanup job dispatches `pages-deploy.yml` after its push lands.
It keeps every dir an open PR still links to. See [Refreshing stale galleries](#refreshing-stale-galleries).

## Refreshing stale galleries

`.github/workflows/vr-refresh.yml` republishes the gallery, both sticky comments, and the PR-body block for open visual PRs whose screenshots went stale. It calls the `Visual Regression` job of `e2e-tests.yml` through its `workflow_call` inputs.

- **Daily schedule.** Refreshes each open visual PR whose linked gallery dir is older than 7 days. At most 10 PRs per sweep, 2 at a time.
- **Manual dispatch.** `gh workflow run vr-refresh.yml -f pr=<N>` refreshes one PR. If that PR is skipped for a reason other than fresh, the select job exits 3 and the run goes red with the reason. A green run never skips the named PR. A blank `pr` sweeps every open visual PR, with the same cap. `-f dry_run=true` lists what would refresh, what the tidy would edit, and what cleanup would keep.
- **Visual PR.** A PR whose `visual-review` sticky comment links a gallery, as `<sha>/visual-review` or `pr/<N>/visual-review`. The first link wins. The banner-only comment is never refreshed.
- **Age.** The `gh-pages` commit time of the dir that comment links to (`<sha>` or `pr/<N>/visual-review`). Every publish writes `refreshed-at.txt` into the dir, so a refresh always moves the age forward.
- **Skipped PRs.** Forks, PRs labeled `update-baselines`, drafts (unless named by `pr`), and PRs behind `master`. The refresh skips a PR whose base is not `master`, and the tidy job removes that PR's stale screenshots (see Stale banner). A master-based PR that still shows the stacked note (the parent merged and GitHub retargeted it) is refreshed. A push republishes a behind PR anyway (ADR-0182).
- **Publish key.** A refresh publishes under the PR head SHA and rewrites the links to it. A push to the PR still publishes under the test-merge SHA.
- **Never a gate.** Every write runs on a master-ref event, so a refresh adds no check run to a PR head and a failed refresh leaves required checks alone. One sweep dispatches `pages-deploy.yml` once. The `tidy` job runs only on the master ref and holds `pull-requests: write`. The select job keeps `pull-requests: read`, and `ibl5/tests/ts-unit/vr-workflow-guard.test.ts` pins both.
- **Stale banner.** A master-based PR's gallery counts as stale when it was shot against an older master (the `<!-- vr-base-sha: <40hex> -->` marker in the sticky comment) and master changed a VR-relevant path since, or when the marker is missing. `VR_RELEVANT_PATHS` is a copy of the `e2e-tests.yml` push `paths:` list, pinned by a drift test. The tidy job then prepends a `<!-- vr-stale-banner -->` banner to the comment and to the PR-body block. A failed compare counts as advanced. A stacked PR (base not `master`) gets its gallery comment replaced by the stacked note, and its manual-row comment and PR-body blocks are removed. A fresh publish drops the banner.
- **Retention.** `vr-pages-cleanup` keeps every per-SHA dir that any open PR's comments or body link to, plus each open PR's head SHA, through both the 7-day age pass and the 150-dir cap. When the keep-list cannot be computed, that run prunes nothing. The keep-list holds 40-hex SHAs only; `bin/prune-vr-galleries` never prunes `pr/<N>/`.
- **Pull-request dry-run.** A PR that edits the refresh workflow, `bin/vr-refresh-targets`, `ibl5/tests/e2e/vr-refresh.ts`, `bin/prune-vr-galleries`, `bin/vr-dedupe-gallery`, or `bin/check-vr-gallery-refs` runs the read-only `VR refresh select` job, which prints both lists and rehearses the migration.

## Reading the comment

See `.claude/rules/visual-review-prs-detail.md` § Reading the comment.

## Approving (sign-off)

Apply the **`update-baselines`** label. The label run reruns the strict pass against the branch's
own baselines (`bin/vr-build-gallery --base-sha=HEAD`), then `bin/vr-apply-baselines` copies the
changed and new cells' `.a.png` over their baselines and commits only those. Flake, infra, and
unflagged cells stay byte-identical. That commit is the approval record; no flagged view, no
commit. The label is removed once the VR step ran, before the bot pushes. A leftover flake cell keeps VR red: re-run the
VR job (ADR-0214).

The label starts `update-baselines.yml`, which calls `e2e-tests.yml`. Its checks report as `Update baselines / <job>`.

The gallery also feeds the human-signoff hold. Each E2E run uploads the `vr-gallery-json-<head sha>`
artifact, and `bin/lib/ui-change-classifier.sh` holds the PR when it lists a changed or new
gating cell. Cells marked `strict: true` and `flakeCells` never hold. `update-baselines` runs
publish no artifact.

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

Both steps are `continue-on-error: true`, so a failure only loses the inline image. The pure splice/copy-plan logic lives in
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
`ibl5/tests/ts-unit/vr-review-comment.test.ts`, run via `bun run test:unit` from `ibl5/`). The refresh
tidy logic is pure too: `classifyTidy`, `singleDispatchError`, and `VR_RELEVANT_PATHS` live in
`ibl5/tests/e2e/vr-refresh.ts`, and `planTidyEdits` and `applyStaleBanner` live in
`ibl5/tests/e2e/vr-review-comment.ts`. Per-row
source overrides use the optional `sourceGlobs` field on `VrRow`. **Changing this selection logic is
a mechanical-enforcement surface and requires an ADR** (current: ADR-0074, amended by ADR-0180 for the review-only strict pass). The PR-body new-screens
publishing surface (`--copy-new-screens`/`--update-pr-body` on `bin/vr-review-comment`,
`ibl5/tests/e2e/vr-pr-body.ts`, `ibl5/tests/ts-unit/vr-pr-body.test.ts`) is likewise a
mechanical-enforcement surface, covered by **ADR-0076**. The crop modules (`ibl5/tests/e2e/vr-crop.ts`,
`ibl5/tests/e2e/vr-crop-png.ts`), `--crop-changed` and `--publish-agent-shots` are covered by
**ADR-0076** and **ADR-0181**.

## Manual-row screenshots

See `.claude/rules/visual-review-prs-detail.md` § Manual-row screenshots (ADR-0126, ADR-0181).

## One-time deployment prerequisite

See `.claude/rules/visual-review-prs-detail.md` § One-time deployment prerequisite.
