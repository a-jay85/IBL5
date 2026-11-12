---
description: A master-ref tidy job removes screenshots from stacked PRs and banners galleries shot against an older master, and a single-PR refresh dispatch that skips the named PR exits red.
last_verified: 2026-11-10
---

# ADR-0238: VR stale-screenshot tidy and red single dispatch

**Status:** Accepted
**Date:** 2026-11-10

## Context

ADR-0182 added a refresh workflow that republishes stale visual-review galleries. It skips PRs whose base is not master and PRs behind master. PR [#3748](https://github.com/a-jay85/IBL5/pull/3748) showed the gap. It is stacked on another branch, so the refresh never touches it, and its gallery and PR-body block still showed screenshots shot against that stacked base. A master-based PR also keeps a gallery that was shot against an older master, and a reader cannot tell that from the page.

A second gap sat in the select job. A dispatch with `-f pr=N` that skipped the named PR still ended green. The run looked like a refresh and refreshed nothing.

## Decision

1. The refresh workflow gets a `tidy` job. It removes the screenshots of every open visual PR whose base is not master. The gallery comment becomes the stacked note. The manual-row comment and both PR-body blocks are deleted.
2. The tidy job banners a master-based PR when its gallery was shot against an older master and master changed a VR-relevant path since. The `<!-- vr-base-sha: <40hex> -->` marker in the sticky comment records that master. A comment with no marker counts as stale. `VR_RELEVANT_PATHS` in `ibl5/tests/e2e/vr-refresh.ts` copies the push `paths:` list of `e2e-tests.yml`, and a drift test pins the copy. A failed compare counts as advanced, so the banner errs toward showing. A fresh publish drops the banner.
3. The tidy job runs only on the master ref. It skips every `pull_request` run and every `-f dry_run=true` dispatch. It holds `pull-requests: write`, and the select job keeps `pull-requests: read`. The job runs after `refresh`, so a fresh publish lands first and is never re-bannered. `ibl5/tests/ts-unit/vr-workflow-guard.test.ts` pins the master-ref condition, the select job's read-only grant, and the rule that `run:` blocks interpolate no `inputs` or `github.event` values.
4. When a single dispatch skips the named PR for any reason other than fresh or a stacked PR the tidy job cleans, `bin/vr-refresh-targets` exits 3. The select job writes the reason to the step summary, emits `::error::`, and fails. The error text comes from a fixed reason table. No branch name or input reaches it.
5. The dry-run step (a `pull_request` run or `-f dry_run=true`) prints the edits the tidy job would make and writes nothing.

This extends ADR-0182 and cites ADR-0185, which defines the PR-body managed block that the tidy job edits.

## Alternatives Considered

- **Add `pull-requests: write` to the select job.** Rejected because the pull_request dry-run runs that job, and a write grant there would let a PR that edits the tooling write to other PRs.
- **Delete the stale gallery directories.** Rejected because retention already owns them, and removing the links is enough for the keep-list to release them.
- **Re-shoot every stale gallery.** Rejected because it costs a full capture per PR for a notice that a banner gives for free.
- **Skip the banner when the compare fails.** Rejected because a silent stale gallery is the failure this ADR closes.

## Consequences

- Stacked PRs stop showing screenshots. Their galleries fall off the keep-list and age out.
- A banner costs no re-shoot. The next refresh or push drops it.
- Comments written before the marker existed read as stale once, until their next publish.
- A failed compare can show a banner on a gallery that is still current.
- A single dispatch against a skipped PR fails the run, so a person reading the Actions list sees the skip.
- The tidy job writes on a master-ref event only, so it adds no check run to a PR head.

## References

- `.github/workflows/vr-refresh.yml`: select outputs, the exit-3 branch, the dry-run step, and the `tidy` job.
- `bin/vr-refresh-targets`: `--mode=tidy`, `--dry-run`, and the exit-3 contract.
- `ibl5/tests/e2e/vr-refresh.ts`: `classifyTidy`, `singleDispatchError`, `VR_RELEVANT_PATHS`.
- `ibl5/tests/e2e/vr-review-comment.ts`: `planTidyEdits`, `applyStaleBanner`.
- `ibl5/tests/ts-unit/vr-workflow-guard.test.ts`: the write-guard tests.
- `.claude/rules/visual-review-prs.md`: section Refreshing stale galleries.
- `ibl5/docs/decisions/0182-vr-stale-refresh-and-keep-list.md` and `ibl5/docs/decisions/0185-vr-pr-body-managed-block-any-position.md`.
