---
description: Store VR gallery before/after/diff PNGs once in a shared _vr-blobs/ store at the gh-pages root so the Pages artifact stops growing with PR volume.
last_verified: 2026-10-26
---

# ADR-0211: Shared blob store for VR gallery images

**Status:** Accepted
**Date:** 2026-10-22
**Deciders:** automouse implementation of the vr-gallery-pages-artifact-size plan

## Context

The VR gallery is served from GitHub Pages. `.github/workflows/pages-deploy.yml`
uploads the whole `gh-pages` tree as one artifact on every deploy. On 2026-10-22 that
tree was 8.72 GB in 34,655 files across 215 per-SHA dirs, and the artifact was
7.29 GB, up 1.2 GB in a day. Pages fails near 10 GB, and the 2026-10-15 failure burst
was that limit. The per-SHA HTTP-200 assert also timed out on 2026-10-20 and
2026-10-21 after upload steps of 190 to 241 seconds.

Per-cell `before`, `after`, and `diff` PNGs made up 94.7% of the tree (8,260 MB).
97.5% of those bytes were copies of the same images in other dirs: the distinct
content was 206.6 MB. Every PR gallery carries about 54 cells because the review-only
strict pass (ADR-0180) surfaces cross-run render drift as changed cells.

The retention caps (7 days, 150 dirs) were already cut once, from 300 dirs. Cutting
them again shortens how long a PR's gallery link works without removing the
duplication.

## Decision

Store each gallery image once, by content, in `_vr-blobs/<sha256>.png` at the
`gh-pages` root. Each `<sha>/visual-review/index.html` refers to its images as
`../../_vr-blobs/<sha256>.png`. Anchors, `new-screens/`, `manual-rows/`,
`playwright-report/`, and `changed/` stay in the per-SHA dir, so every link already
posted in a PR comment or body keeps working. Pages forbids symlinks and hard links in
the artifact, so the sharing is by reference.

- `bin/vr-dedupe-gallery` writes a new gallery in this form at publish time, and its
  `--migrate` mode converts existing dirs in place. Migration is idempotent.
- `bin/prune-vr-galleries` removes a blob only when no surviving gallery references
  it and its newest commit is older than `--cutoff-days`. Dirs the same run prunes
  do not count as references. Dirs on the open-PR keep-list do.
- `bin/check-vr-gallery-refs` fails when a gallery image reference resolves to no
  file. It runs before the cleanup job pushes and before `pages-deploy.yml` uploads.
  The cleanup run passes `--deleted-list` with the files its own commit deletes, so it
  fails only on a ref to one of those. A ref that already dangled on `gh-pages` prints
  a warning, so one bad ref cannot block every later prune, GC, and migration.
- `pages-deploy.yml` reports the artifact size and the number of attempts the assert
  needed in the step summary, and warns above 1 GiB. The 600 second assert budget is
  unchanged.
- `bin/test-vr-gallery-store` and new cases in `bin/test-prune-eligibility` pin the
  behavior on fixture trees.

## Alternatives Considered

- **Cut the retention caps again.** Fewer days and fewer dirs. Rejected because: it shortens how long a posted gallery link works and leaves the duplication in place.
- **Short hash prefix for blob names.** Saves a few bytes per reference. Rejected because: a collision would silently show one PR the wrong image.
- **A separate GC script.** Keeps prune untouched. Rejected because: GC must know which dirs the same run prunes and which the keep-list exempts, and prune already holds both sets.

## Consequences

- Positive: the tree drops from about 8.7 GB to about 0.66 GB after migration, which also shortens the upload that precedes the HTTP-200 assert.
- Positive: links and anchors in open PRs keep working, because only image `src` values change.
- Negative: a blob GC could race a concurrent publish. The age gate covers only blobs newly committed within the cutoff. A publish that reuses an existing blob makes no commit on it, so that blob's age never refreshes. Three guards cover that race: the refs check in the cleanup retry loop on files that run deleted (after every rebase, before each push), peaceiris re-cloning `gh-pages` on each retry, and the refs check in `pages-deploy.yml` before upload. The PR publish path checks only its own stage, so a dangling tree can reach `gh-pages` but never the served site.
- Negative: a PR that republishes an old-form dir leaves its legacy PNGs unreferenced until retention prunes that dir.
- Negative: the first cleanup run rewrites every legacy dir, so each dir's newest commit time becomes the migration time. Dir age is read from that commit time, so for one retention window (7 days) the age-based prune and the stale-gallery refresh see those dirs as new. Size stays bounded, because migrated dirs hold HTML only and the 150-dir cap still applies. Dirs tied on age are evicted in SHA-name order until real ages build up again. The effect happens once and is accepted.
- Follow-up: arm a hard size fail in `pages-deploy.yml` after one clean cycle of telemetry.
- Follow-up, out of scope here: ADR-0180 drift fills every gallery with about 54 changed cells, which deserves its own look.

## References

- `bin/vr-dedupe-gallery`
- `bin/check-vr-gallery-refs`
- `bin/prune-vr-galleries`
- `bin/test-vr-gallery-store`
- `bin/test-prune-eligibility`
- `.github/workflows/e2e-tests.yml`
- `.github/workflows/pages-deploy.yml`
- `.claude/rules/visual-review-prs.md`
