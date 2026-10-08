---
description: Visual-review galleries and manual-row screens live at gh-pages pr/<N>/, overwritten on every push and removed on close; manual rows render before and after at phone and desktop width in a vr-screens PR-body block.
last_verified: 2026-10-07
---

# ADR-0179: PR-keyed visual-review storage and phone-readable manual-row screens

**Status:** Accepted
**Date:** 2026-10-07

## Context

The e2e job published each visual-review gallery to `gh-pages` under `<github.sha>/visual-review/` (ADR-0078 serves that tree). On a `pull_request` event, `github.sha` is the merge commit. It changes whenever master moves. When the tree-hash memo (ADR-0131) skips the e2e job, nothing writes the new directory. The comment links and PR-body images then point at a path that was never written, and they 404 while the PR is still open.

Manual-row screenshots (ADR-0126) were one 1280 px desktop shot per row, posted in a sticky comment below the PR body. On a phone the shots are unreadable, and the reviewer has to leave the Manual Testing checklist to find them. ADR-0076 already splices a managed block into the PR body for new screens, so a body block is an established channel.

## Decision

1. **Storage key.** Every PR-scoped artifact lives under `gh-pages:pr/<N>/`, where `<N>` is a validated PR number. The subtrees are `visual-review/` (the e2e gallery), `meta/` (`manifest.json`, `rows.json`, `block.md`), `manual/before/` and `manual/after/`. Each push overwrites them, so the paths exist for the life of the open PR. Only `bin/vr-pages-publish` writes them. It replaces one validated subtree per call, retries by refetching and re-applying, and fails the job after three rejected pushes.
2. **Rendering.** `.github/workflows/vr-pr-screens.yml` shoots each manual-row `vr:` cell at 375 px and 1280 px. The before side is the merge ref's first parent and the after side is the merge ref. Both sides run the merge ref's tooling. A manifest (`cellsHash`, both tree hashes) re-renders only a side whose input changed. Every push re-assembles the block, so image URLs always carry the current head as `?sha=<head>&r=<token>`, including when ADR-0131's memo skips the e2e job.
3. **Body block.** A `vr-screens` marker block holds a before/after table (phone and desktop) per cell and one gallery link. It is spliced in just before the first `## Manual Testing` heading. A body with no such heading gets the block appended at the end. The splice leaves every other marker block byte-identical. Each write re-reads the body before writing and verifies after, with three attempts. Any write that would push the body past 60000 characters is refused. A body `edited` event re-runs the plan cheaply and re-splices a lost block.
4. **Lifecycle.** A `closed` event deletes `pr/<N>/`. The master-push retention job sweeps numeric `pr/` dirs that are absent from the open-PR list. The sweep fails closed: an unreadable, truncated or empty list deletes nothing. Open-PR directories are never pruned.
5. **Retirement.** The `manual-row-screenshots` sticky comment is deleted on the next e2e run. Its capture steps leave the e2e job.

## Alternatives Considered

- Key `<sha>/` dirs by the PR head instead of the merge sha. Rejected because a memo skip still leaves the new head unwritten.
- Upload-artifact URLs. Rejected because they cannot back a markdown image (ADR-0126).
- Capture inside the e2e job. Rejected because the memo skip and the `update-baselines` flow would gate the screens.

## Consequences

- Positive: new-screen image URLs (ADR-0076) become stable without a cache-bust.
- Positive: a reviewer sees phone-width before and after shots inside the Manual Testing context.
- Negative: a second workflow adds one render runner per push for PRs with `vr:` cells, and about 1 min of plan work for other PRs.
- Negative: the block stays in a closed PR's body with dead images.

## Lineage

This ADR replaces two parts of earlier decisions, and both stay Accepted. From ADR-0076 it takes over the per-SHA image URL shape. From ADR-0126 it takes over the publish and post stages (the sticky comment). The `vr:` grammar and the offset-0 new-screens block are unchanged.

## References

- ADR-0076, ADR-0078, ADR-0126, ADR-0131.
- `.github/workflows/vr-pr-screens.yml`: plan, render, publish and cleanup jobs.
- `bin/vr-pages-publish`: the only writer for `pr/` paths; `bin/test-vr-pages-publish` is its harness.
- `ibl5/tests/e2e/vr-pr-screens.ts`: the pure plan, token and block helpers, unit-tested in `ibl5/tests/ts-unit/vr-pr-screens.test.ts`.
