---
description: Daily and at-login health check for the nine com.ibl5 launchd UserAgents, with once-per-day Discord alerting.
last_verified: 2026-10-23
---

# ADR-0142: Daily and At-Login Health Check for the com.ibl5 launchd Jobs

**Status:** Accepted
**Date:** 2026-09-28

## Context

Nine long-lived `com.ibl5.*` jobs run the league's unattended plumbing: `automouse`, `automouse-morning-digest`, `backups-sync`, `bug-bot`, `bug-pipeline-cron`, `db-sync-nightly`, `docfix-poll`, `sim-recap-poll`, and `wt-sync`. A job that is unloaded, or a KeepAlive job with no PID, fails silently. The first sign is missing downstream output days later. One-shot `plan-now`, `postplan-now`, `pr-review-now`, and `docfix-run` plists can also outlive their runner script and accumulate in `~/Library/LaunchAgents`.

## Decision

`bin/launchd-health-check` compares `launchctl list` against the expected-job list in `bin/lib/launchd-expected-jobs.sh`, which is the single source that both the checker and `bin/test-launchd-health-check` read. It checks `bug-bot` for a non-empty PID and checks Docker Desktop plus the three main-stack containers. It exempts `sim-recap-poll` from the not-loaded check only when `bin/db-query` confirms `Current Season Phase` is something other than `Regular Season`. A failed phase query counts as Regular Season, so the alert fires. Problems go to `bin/discord-dm`, deduplicated to once per problem key per day through stamp files under the `launchd-health/logs/stamps/` dir. Stale one-shots are logged and never DM'd. `bin/launchd-health-cron-setup` installs a `com.ibl5.launchd-health-login` plist that fires at load with `--startup-delay 180` and a `com.ibl5.launchd-health-daily` plist that fires at 08:45. Both harnesses run as steps in `.github/workflows/tests.yml`.

## Alternatives Considered

- **Inline the expected-job list in the checker.** Rejected because the test would have to copy the list, and a job added to one copy but not the other would pass the test while the live check drifted.
- **Read the phase from `bin/sim-recap-tick`'s `claim-next` output.** Rejected because `claim-next` claims queue work, and a read-only monitor must not mutate the queue.
- **DM on every run with no dedup.** Rejected because the at-login run and the daily run would both DM the same unloaded job each day. Repeat alerts for one known fault teach the reader to skip the channel. That failure mode is what ADR-0139 addresses.
- **Rely on launchd `KeepAlive` alone.** Rejected because KeepAlive restarts a crashed process but cannot report a job whose plist was booted out or never loaded. That unloaded case is the one this check exists to catch.

## Consequences

- Adding a new long-lived `com.ibl5.*` job now requires one line in `bin/lib/launchd-expected-jobs.sh`. Otherwise the checker ignores it, and it has no coverage.
- The checker needs a reachable DB to exempt `sim-recap-poll` off-season. When the DB is down in the off-season, it sends one false "not loaded" DM per day, accepted in exchange for never hiding a real in-season outage.

## References

- `bin/launchd-health-check` (the checker)
- `bin/launchd-health-cron-setup` (plist installer)
- `bin/lib/launchd-expected-jobs.sh` (single source of truth for expected jobs)
- `bin/test-launchd-health-check` (stubbed harness)
- `bin/test-launchd-health-cron-setup` (plist-generation harness)
- `.github/workflows/tests.yml` (CI wiring)

## Addendum (2026-10-23): bug-pipeline verdicts route through this check

`bin/bug-pipeline-check` had no alert route, so a degraded verdict reached nobody unless someone ran it by hand (a-jay85/IBL5-backlog#1573). `bin/launchd-health-check` now runs it after `check_docker` in a `check_bug_pipeline` step.

- Exit 0 adds nothing. Exit 1 or 2 adds one problem per `SIGNAL:` line, keyed `bug-pipeline:<signal>`. Each key goes through the existing stamp dedup, so a new signal still DMs on a day an older one was already reported.
- Any other exit code, a missing binary, or exit 1 or 2 with no `SIGNAL:` line adds `bug-pipeline:check-failed`. A `SIGNAL:` line the parser cannot key adds `bug-pipeline:unparsed-signal`.
- A signal is dropped only when an earlier check already reports the same fault. `process:unloaded` is dropped when `missing:com.ibl5.bug-pipeline-cron` is present. `bot:unloaded` is dropped when `missing:com.ibl5.bug-bot` is present. Any `*:db-unavailable` signal is dropped when a `docker:*` or `container:ibl5-mariadb` problem is present, which covers the at-login run firing before Docker is up.
- The test seam `BUG_PIPELINE_CHECK_BIN` selects the checker. Unset or empty means the sibling `bin/bug-pipeline-check`. `bin/test-launchd-health-check` cases B0 to B14b cover the step, including a drift guard over the checker's emit sites and two cases that run the real checker.
