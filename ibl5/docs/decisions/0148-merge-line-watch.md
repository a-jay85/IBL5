---
description: bin/merge-line-watch polls master from a developer shell and runs the same head-of-line update-branch pass as the update-behind-prs workflow, reusing bin/merge-line-select for the decision.
last_verified: 2026-09-30
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0148: Local Merge-Line Watcher That Reuses the Workflow's Selector

**Status:** Accepted
**Date:** 2026-09-30
**Deciders:** post-plan harness (auto-draft)

## Context

ADR-0081 and its Addendum keep the armed-PR merge line moving with `.github/workflows/update-behind-prs.yml`. Each pass asks `bin/merge-line-select` for the head of the line and calls update-branch on that one PR. The workflow has two triggers that can leave the line stalled. Its `push` trigger carries a `paths-ignore` list, so a master merge that touches only markdown or `.claude/` files starts no pass. Its cron backstop is the event GitHub delays and drops, and the workflow file records delays of 6 to 12 hours on 2026-08-27 and 2026-08-28. When either gap hits, the head PR sits BEHIND master and nothing behind it can merge.

`bin/adr-check` flagged this PR because it adds two new developer tools under `bin/`: `bin/merge-line-watch` and its harness `bin/test-merge-line-watch`.

## Decision

Add `bin/merge-line-watch`, a foreground loop a developer runs in a spare terminal. It reads the master SHA every `--interval` seconds (default 30). When the SHA changes it waits `--settle` seconds (default 45) so GitHub can recompute mergeability, then runs one pass. The pass calls `bin/merge-line-select --live` and acts only when the selector prints `action=update`. It then calls `PUT repos/<repo>/pulls/<head>/update-branch` with the developer's own `gh` auth. When the selector prints `action=unknown`, the pass retries up to 8 times before it gives up. `--once` runs a single pass and exits, and `--dry-run` never writes.

The watcher holds no selection logic of its own. The workflow and the watcher share `bin/merge-line-select`, so the two cannot disagree on which PR is the head. `bin/test-merge-line-watch` drives `--once` passes against a stub `gh` and a stub selector through the `GH_CMD` and `MERGE_LINE_SELECT_CMD` seams, with no network access. The `Shell harness regression tests` job in `.github/workflows/tests.yml` runs it in CI.

## Alternatives Considered

- **Drop the push trigger's `paths-ignore`.** Rejected because every docs-only merge would then start a workflow run, and the cron delay would still apply to any pass that GitHub cancels or loses.
- **Run the cron more often.** Rejected because the recorded failure is GitHub delaying and dropping scheduled events. A shorter interval does not make those events arrive on time.
- **Copy the selector logic into the watcher.** Rejected because a second copy of the selection rules could drift from `bin/merge-line-select`, and the watcher would then update a PR the workflow would skip.
- **Run the watcher as a launchd job.** Rejected for this change because a long-lived background writer with the owner's token needs its own health checks and failure notices. A foreground tab that the developer starts and stops keeps the scope small.

## Consequences

- Positive: while the watcher runs, every master move gets a head-of-line pass within about `interval + settle` seconds, whatever files the merge touched.
- Positive: the watcher and the workflow pick the same head PR for a given master SHA, so a pass from each cannot push two different PRs forward.
- Negative: when both run on one master move, the second update-branch call can fail on a branch the first already updated. The watcher prints that failure as one log line and keeps polling.
- Negative: the watcher covers the gap only while a developer keeps it open. The workflow stays the unattended path.
- Negative: the update-branch push is made with the developer's personal token, so it appears under their account in the PR timeline.
- Negative: one more `bin/` script and one more `test-*` harness to maintain under the meta-tooling bar.

## References

- `bin/merge-line-watch`
- `bin/test-merge-line-watch`
- `bin/merge-line-select` (the shared head-of-line selector)
- `.github/workflows/update-behind-prs.yml` (the workflow this stands in for)
- `.github/workflows/tests.yml` (runs the harness in CI)
- `ibl5/docs/decisions/0081-scheduled-branch-update-for-armed-behind-prs.md`
- `.claude/rules/meta-tooling-bar.md`
