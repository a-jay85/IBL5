---
description: bin/automouse/morning-digest sends one aggregated Discord DM per morning summarising overnight automouse outcomes; bin/automouse/morning-digest-cron-setup generates and installs the launchd LaunchAgent that fires it at 07:00 local.
last_verified: 2026-09-10
owner: ajaynicolas
---

# ADR-0124: Automouse morning digest notification

**Status:** Accepted
**Date:** 2026-09-05
**Deciders:** ajaynicolas

## Context

`bin/automouse/run` writes a per-run report for each disposition (`done/`, `skipped/`, `queue/` sidecars) plus a daily costs roll-up under `reports/`, and sends a per-run Discord ping via `bin/discord-dm`. Nothing aggregates. Reading overnight outcomes requires manually trawling `reports/` and `gh pr list`. Plans parked in `queue/` behind a `*.attempts` sidecar are invisible until someone looks — which is the risk the backlog entry L3 ("Morning digest") existed to close.

## Decision

Add two new `bin/` scripts:

1. **`bin/automouse/morning-digest`** — composes and sends one aggregated Discord DM each morning summarising the prior night's automouse outcomes: completed plans, skipped plans, failed plans, parked plans, and a spend floor. Uses `bin/discord-dm --chunk` so long digests are delivered in full rather than truncated.
2. **`bin/automouse/morning-digest-cron-setup`** — generates and installs a launchd LaunchAgent (`com.ibl5.automouse-morning-digest`) that fires the digest at 07:00 local via `StartCalendarInterval`.

Five constraints shaped the design:

1. **Host-side launchd, not GitHub Actions.** `bin/discord-dm` reaches the bot over an SSH tunnel to `iblhoops.net` where it binds `127.0.0.1:50000`. A GitHub-hosted runner has no route to it. This is an architectural bind, not a convenience preference.
2. **Additive.** Per-run pings are unchanged. The backlog entry's original "replaces per-run pings" direction is superseded — a run that fails at 02:00 should still ping at 02:00; deferring that signal to 07:00 turns a five-hour-earlier alert into a five-hour-later one for no gain.
3. **Reuses `bin/discord-dm`.** No second Discord client, no webhook URL, no token in either new script.
4. **Reads spend, never re-derives it.** The digest sums the per-plan rows already written by `bin/automouse/run` / `bin/automouse/backfill-costs` into `reports/YYYY-MM-DD-costs.md`. It stops parsing at the `## Weekly aggregate` block, whose totals are seven-day figures; reading that block would over-report spend by roughly 7×.
5. **The collection window is anchored to the digest DATE, not to wall-clock now.** "Last night" is `mtime ∈ (prior-date 07:00:00, digest-date 07:00:00]`, matching the `StartCalendarInterval` Hour 7 below and the two-day span of costs files the spend section already reads. A relative `find -mmin -1440` would be equivalent for the 07:00 cron run but makes `--date <past>` silently incoherent — it would collect *today's* files while reporting the past date's spend. Consecutive daily windows are non-overlapping and gapless. The trade-off is deliberate: a **manual** run at, say, 14:00 no longer reports a plan that finished at 09:00 that same morning — that plan belongs to tomorrow's digest.

`StartCalendarInterval` Hour 7 / Minute 0 is a deliberate departure from `bin/sim-recap-cron-setup`, which uses `StartInterval 300` for polling semantics. A morning digest is a once-a-day event; `StartInterval` would need an internal wall-clock guard and would fire ~288 no-op ticks a day.

## Alternatives Considered

- **GitHub Actions cron trigger** — rejected: no route to `iblhoops.net` from a hosted runner without exposing the bot or duplicating the Discord client, and there is no existing automouse Actions job to extend.
- **Fold `--install-schedule` into `bin/automouse/morning-digest`** — rejected: that would put the intrinsic scheduling slice (launchd registration) and the fully-reducible logic slice in the same file and phase, requiring a blanket pre-prod exception. Separate scripts let the exception anchor to Phase 3 alone. Three in-repo precedents agree: `bin/sim-recap-cron-setup`, `bin/bug-pipeline-cron-setup`, `bin/wt-sync-cron-setup`.
- **`gh pr view` enrichment per plan** — rejected: makes the script network-dependent, rate-limit-exposed, and unfixturable. The terminal bucket is labelled "Completed" rather than "Merged" to avoid false precision.
- **Suppress the send on a zero-run night** — rejected: a silent morning is ambiguous between "quiet night" and "the digest is broken". A zero-run night sends a coherent `no plans ran last night` line so the absence of news is distinguishable from a broken sender.

## Consequences

- Positive: overnight automouse outcomes are visible in one Discord DM each morning without manual trawling.
- Positive: parked plans (`queue/*.attempts` without a `.failure` sidecar) are surfaced, closing the visibility gap L3 identified.
- Positive: spend floor is reported alongside outcomes, giving a nightly cost signal.
- Negative: the digest is host-bound — it does not run in CI and does not run on a machine without the SSH credential to `iblhoops.net`.
- Negative: merge-state enrichment (`gh pr view` per plan) is out of scope; the digest classifies by on-disk disposition, not by GitHub PR state.
- Neutral: the launchd registration and the 07:00 tick firing are intrinsic pre-prod exceptions (no route via CI or Docker); only the generated plist content and the install-refusal paths are assertable pre-merge.

## References

- `bin/automouse/morning-digest` — the digest script
- `bin/automouse/morning-digest-cron-setup` — the launchd scheduler
- `bin/test-automouse-morning-digest` — the 21-row fixture harness (rows 20–21 added as regression guards during implementation, beyond the plan's 19)
- `bin/discord-dm` — the send interface (stdin `-`, `--chunk`, exit codes 0/1/2)
- `bin/sim-recap-cron-setup` — launchd pattern copied for Phase 3
- `a-jay85/IBL5-backlog` (label `loop-engineering`) — the L3 resolution record (per ADR-0121, which retired the in-repo markdown backlog corpus)
