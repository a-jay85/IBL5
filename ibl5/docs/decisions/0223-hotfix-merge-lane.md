---
description: A PR labeled hotfix sorts ahead of every other armed PR in the merge line, and a new pull_request_target workflow, hotfix-dispatch.yml, starts an update-behind-prs pass the moment an armed PR carries the label. Gates, the armed filter, and the feat floor are unchanged.
last_verified: 2026-11-08
owner: ajaynicolas
---

# ADR-0223: A hotfix lane at the front of the merge line

**Status:** Accepted
**Date:** 2026-11-02
**Deciders:** a-jay85

## Context

ADR-0081 orders the armed merge line by arm time, oldest first. An urgent fix armed behind a long line waited its turn, and it waited for the next push or scheduled pass even after it reached the head. There was no way to say "this one goes next" short of disarming the PRs ahead of it.

## Decision

A PR labeled `hotfix` sorts ahead of every other armed PR. `bin/merge-line-select` adds `is_hotfix`, read from the same `labels` selection as `is_running`, and puts it first in the sort key. Hotfix PRs keep arm-time order among themselves, and the other armed PRs keep arm-time order behind them.

Order is the only change. The armed filter still decides who is in the line, so a labeled PR that is not armed stays out. A hotfix PR that is red, conflicted, or labeled `post-plan-running` is skipped exactly as before, and the line falls to the next hotfix, then to arm-time order. Required checks, `human-signoff`, and the `feat:` floor apply to a hotfix PR unchanged. Nothing arms auto-merge for it.

Sorting first does nothing until the next pass runs, so `.github/workflows/hotfix-dispatch.yml` starts one. It is a `pull_request_target` workflow on the `labeled` and `auto_merge_enabled` events. It fires only when the PR is armed and carries the label, then runs `gh workflow run update-behind-prs.yml --ref master -f hotfix=true`. The `auto_merge_enabled` event covers a PR that was labeled before it was armed. The workflow holds `actions: write` and nothing else. It never checks out the repository, and no PR-controlled string reaches its `run:` line. `bin/test-merge-line-select` case W7 pins that shape.

The hotfix pass runs in the `update-behind-prs-hotfix` concurrency group, so it cannot cancel a push pass that is waiting out its coalesce sleep or its bot-commit wait. It skips the 300-second coalesce sleep, still runs `master_bot_wait`, and gets the push pass's 20 mergeability attempts. A push pass and a hotfix pass that overlap pick the same head, and the second `update-branch` call only logs a warning.

## Alternatives Considered

- **Dispatch with the `pr` input.** Rejected because `--pr` mode skips the armed filter and does not refuse a red PR, so it could update a PR the line would pass over. The dispatch is a head-of-line pass instead.
- **Share the push pass's concurrency group.** Rejected because a hotfix dispatch would cancel a push pass mid-sleep or mid-wait.
- **Wait for the next scheduled or push pass.** Rejected because the label would change order without changing when anything happens.

## Consequences

- Positive: an urgent fix reaches the head of the line and gets refreshed within one workflow run.
- Positive: no gate is weakened. A hotfix PR still needs green required checks and, for `feat:`, human signoff.
- Negative: the label audit found that `mutation.yml` put every label event in the same concurrency group as a real `mutation-test` run, so any other label cancelled that run. Other label events now get a per-run group, and `bin/test-merge-line-select` case W8 pins it.
- Negative: on a `feat:` PR a `hotfix` label event cancels the in-flight `human-signoff` run and starts a new one that reaches the same verdict. `human-signoff.yml` stays as it is, because splitting its group would touch the `feat:` floor. `auto-mergeable-label.yml` already gives unrelated labels their own group, and `e2e-tests.yml` no longer runs on label events at all (ADR-0225).

The label was created with `gh label create hotfix --force --color D73A4A --description "Jumps to the front of the merge line"`. Only collaborators can apply a label.

## References

- `bin/merge-line-select`
- `bin/test-merge-line-select`
- `.github/workflows/hotfix-dispatch.yml`
- `.github/workflows/update-behind-prs.yml`
- `.github/workflows/mutation.yml`
- ADR-0081, `ibl5/docs/decisions/0081-scheduled-branch-update-for-armed-behind-prs.md`

## Addendum: coalesce sleep removed (2026-11-08)

The Decision says the hotfix pass skips the 300-second coalesce sleep. That sleep no longer runs on any path. Push passes dropped it on 2026-11-08, as recorded in the ADR-0081 addendum of that date. The hotfix pass and the push pass now start the same way, at the bot-commit wait. The hotfix lane keeps its own `-hotfix` concurrency group, so a hotfix dispatch still never cancels a push pass in its bot-commit wait. Its head start over a push pass now comes from the sort order and the dispatch at label time.
