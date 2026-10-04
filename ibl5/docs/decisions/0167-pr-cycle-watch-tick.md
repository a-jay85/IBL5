---
description: Scheduled pr-cycle watcher tick, its retry and in-flight guards, and the post-plan race fix in both directions.
last_verified: 2026-10-03
---

# ADR-0167: Schedule bin/pr-cycle on a launchd tick

**Status:** Accepted
**Date:** 2026-10-03
**Deciders:** A-Jay

## Context

`bin/pr-cycle` readies, rescues and arms open PRs in one pass, but only when a human runs it. A PR that turns DIRTY or settles red overnight waits for the next manual run. A watcher has to run unattended without three failure modes. It must not race a post-plan run already working the same PR. It must not burn Claude budget on a tick with nothing to do. It must not retry the same broken head forever. Exploration also found a race inside the existing pipeline. A worktree-fired `bin/post-plan-now` holds `com.ibl5.postplan-now-<slug>-*`, and a fleet rescue holds `com.ibl5.post-plan-fleet-<PR>`. Neither guard looked at the other family.

## Decision

A launchd LaunchAgent, `com.ibl5.watch-pr-cycle`, runs `bin/pr-cycle-tick` from the main checkout every 1800 seconds. The tick checks, in order, the stop file `$HOME/.ibl5-pr-cycle.stop`, the usage-gate zone (ADR-0143), and a live `bin/pr-cycle` worker. It then builds the qualifying set from `gh` reads alone. A PR qualifies when it is DIRTY or its checks have settled red, and when it has no post-plan run in flight under either label family. Its last post-plan `result.json` must also be at least 30 minutes old, and no rescue may already have been tried on its current head SHA. An empty set exits with no Claude call. Otherwise the tick runs `bin/pr-cycle --go --only <set> --max-ready <n>`, at most three PRs per tick. Retry state lives in `$HOME/.ibl5-pr-cycle-watch/`, outside the repo and safe from `/tmp` cleanup. A second failure on the same head sends one DM and then stays silent until a new push. The predicates live in `bin/lib/pr-cycle-watch.sh`, and `bin/pr-cycle` stays one-pass. Both race directions are closed. `bin/post-plan-fleet` skips a PR whose branch has a live `postplan-now` label. A detaching `bin/post-plan-now` exits 6 when a fleet slot for its PR is loaded.

## Alternatives Considered

- Skip predicates and a scheduled mode inside `bin/pr-cycle`: rejected because that file is already over a thousand lines, its harness pins "never polls, never sleeps", and the worker calls `_auth_probe` before any skip could run. The Claude spend an idle tick must avoid would already be made.
- `/loop` in a Claude session: rejected because every tick re-reads the full conversation context.
- A GitHub Actions schedule: rejected because a public repo has no self-hosted runner (ADR-0086).
- A shared lock file for the two post-plan families: rejected because a lock needs stale-lock reaping, while launchd labels already are the liveness record both guards read. Labels vanish when a job unloads.

## Consequences

- Positive: a PR that goes red or DIRTY gets one rescue per head SHA without a human starting it.
- Positive: an idle tick costs a handful of `gh` reads and no Claude call.
- Positive: the two post-plan families can no longer start on one PR at the same time through the scheduled paths.
- Negative: a tick that launches `bin/pr-cycle` also runs its Stage 2 arming pass over every armable PR, the same as a manual run.
- Negative: a human who runs `bin/post-plan-now --foreground` while a fleet rescue is live is not refused, because `--foreground` is the fleet runner's calling contract.
- Negative: a post-plan run killed before it writes `result.json` gets no 30-minute grace window. The in-flight check covers it only while its launchd label is loaded.

## Install

Run from the main checkout after the PR merges. The plist's Program is the main checkout's copy.

```bash
cd /Users/ajaynicolas/GitHub/IBL5 && git pull --ff-only
bin/pr-cycle-tick-cron-setup --install-schedule
launchctl list com.ibl5.watch-pr-cycle
bin/pr-cycle-tick --dry-run
```

Expected: `Installed com.ibl5.watch-pr-cycle (StartInterval 1800s)`, a `launchctl list` record, and a dry-run report naming each open PR's verdict with no launch.

## References

- `bin/pr-cycle-tick`: the tick with ordered cheapest-first guards
- `bin/lib/pr-cycle-watch.sh`: per-PR skip predicates
- `bin/pr-cycle`: the one-pass pipeline the tick hands PRs to
- `bin/post-plan-fleet`: fleet-side race fix
- `bin/post-plan-now`: now-side race fix
- `bin/lib/launchd-job.sh`: `ljob_safe_slug` and `ljob_postplan_now_live`
- `bin/wt-sync-cron-setup`: the installer precedent
