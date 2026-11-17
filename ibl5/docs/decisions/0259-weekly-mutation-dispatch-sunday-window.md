---
description: The weekly full mutation run starts by dispatch from mutation-weekly-dispatch.yml on Sunday 3am to 9am Pacific, with catch-up starts until 8pm, gated in Pacific time by bin/mutation-weekly-dispatch.
last_verified: 2026-11-16
---

# ADR-0259: Weekly mutation dispatch in a Sunday Pacific window

**Status:** Accepted
**Date:** 2026-11-16
**Deciders:** Repo owner

## Context

`mutation.yml` ran its full suite on the cron `0 11 * * 1`, meaning Monday 3am PST. GitHub throttles schedule events across this public repo. `gh run list --event schedule` showed weekly crons firing 4.8 to 10 hours late, and the Monday run fired from 17:04Z to 21:13Z. A denser cron does not fire more often, because GitHub drops missed ticks and never fires a tick early. The owner wants the weekly results on Sunday morning Pacific time.

## Decision

The new workflow `.github/workflows/mutation-weekly-dispatch.yml` ticks every 15 minutes from Sunday 05:00Z to Monday 03:45Z and runs `bin/mutation-weekly-dispatch`. The script waits until 3am Pacific when a tick fires early. It starts the primary run from 3am to 7am and catch-up runs until 8pm. It skips when a mutation run is live on master, or when the week already has a schedule run or a run dispatched by `github-actions[bot]`. All time math uses `zoneinfo` America/Los_Angeles, so one cron set covers PST and PDT. `mutation.yml` loses its `schedule:` trigger. An `alert-cancelled` job in the same file sends the Discord timeout alert for dispatched runs. Check W1 in `bin/check-composite-contracts` enforces the triggers, the single `actions: write` scope, the job-level concurrency, the timeout above the 5-hour sleep cap, the cron coverage, and a schedule-free `mutation.yml`.

## Alternatives Considered

- Move the cron to Sunday 11:00Z. Rejected because the measured delays push most fires past 15:00Z, after the last start that finishes by 9am.
- Write the gate as inline bash inside the workflow. Rejected because DST-correct week math needs `zoneinfo`, and a script lets `bin/test-mutation-weekly-dispatch` test it offline with a fake `gh` and a fake clock.
- Keep the dedupe record in a repository variable. Rejected because writing one needs an admin token. Run history needs only `actions: read`.
- Put the cancel alert in its own workflow file. Rejected because it adds workflow surface. A dispatch input on `mutation.yml` was also rejected, since it would change the `notify-mutation-failure` `if:` that other plans own.

## Consequences

- Positive: the weekly run starts on Sunday Pacific time even when GitHub fires ticks hours late.
- Positive: each week gets at most one bot run, and a failed or timed-out run is not retried.
- Negative: an early tick holds a runner for up to 5 hours while it waits. Runner minutes are free on a public repo.
- Negative: the real `GITHUB_TOKEN` dispatch and the cancel alert only run from master, so the first Sunday after merge is their first live test.

## References

- `.github/workflows/mutation-weekly-dispatch.yml`
- `.github/workflows/mutation.yml`
- `bin/mutation-weekly-dispatch`
- `bin/test-mutation-weekly-dispatch`
- `bin/check-composite-contracts`
- `ibl5/docs/decisions/0149-required-ci-aggregator-all-checks-green.md`
