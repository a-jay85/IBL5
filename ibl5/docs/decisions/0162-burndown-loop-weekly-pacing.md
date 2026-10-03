---
description: bin/burndown-loop runs one fresh headless /burndown session per batch, paced so weekly utilization tracks a straight line to (100 - reserve)% at seven_day.resets_at.
last_verified: 2026-10-02
---

# ADR-0162: Unattended /burndown loop paced against the weekly window

**Status:** Accepted
**Date:** 2026-10-02

## Context

The owner re-ran /burndown by hand every few minutes. Each run pays about 20 turns of fixed
overhead, and runs stop at the 5-unit cap (`BD_BUDGET` in `bin/lib/burndown.sh`) while the
triage report holds hundreds of open, never-dispatched issues. Cache-read cost per turn
grows with session length (about 42k at 28 turns, 89k at 127 turns), so a bigger single
session costs more than several fresh 5-unit sessions. The binding limit is the weekly
window. Running into the 93% drain zone (ADR-0143) starves interactive work for the rest
of the week.

## Decision

`bin/burndown-loop` is a launchd-detached loop. Each iteration reads usage live and
computes allowed = (100 - R) * (168 - hours_left) / 168, where hours_left counts down to
`seven_day.resets_at` and R is `IBL5_BURNDOWN_RESERVE_PCT` (default 10). It starts a new
headless `claude -p` /burndown session only when weekly utilization is below allowed and
`usage_zone` is normal. Otherwise it sleeps for at most 30 minutes, logged, and re-reads. A
missing or failed reading fails closed (sleep), because the loop is discretionary work.
Drain or stop zones pause through `usage_prestart_gate` (exit 75, runner `burndown-loop`,
priority 4), and `bin/usage-gate-coordinator` resumes it through `--resume-paused`. The
outcome of a batch is read from new `burndown-batch-*.json` ledgers. A first-round
`LEDGER: none` writes no ledger, so the session prints a sentinel line for that case. The
loop ends on supply exhaustion, a fail-closed exit 3 from /burndown, a graceful `stop`, or
3 consecutive failed batches, and sends one Discord DM.

The pacing math is `usage_pace_verdict` in `bin/lib/usage-gate.sh`, a pure function
tested in `bin/test-usage-gate`. The loop is tested by `bin/test-burndown-loop`.

Meta-tooling bar: two new `bin/` scripts. `bin/plan-now` launches one session and owns its
dispatch and review flow. `bin/lib/burndown.sh` is a sourced selection library with no
process lifecycle. A long-lived paced loop with a lock, a launchd unit and gate resume is
a distinct trigger that neither hosts without straining its single job.

## Alternatives Considered

- Loop inside one /burndown session, or raise `BD_BUDGET`. Rejected because cache-read cost per turn grows with session length, so one long session costs more than several fresh ones.
- Estimate a burn rate from two usage readings. Rejected because it needs persisted history and is noisy over short gaps. One live reading plus `resets_at` is stateless.

## Consequences

- Batches run back to back early in a window only when utilization is under the line,
  and the loop slows itself as it nears the line.
- The loop never yields to automouse and has no open-PR cap. Its dispatched children are
  gated on their own.
- Raising `BD_BUDGET` or looping inside one session stays rejected (cost per turn).
