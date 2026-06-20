---
description: The automouse env-breaker treats a deliberate impl skip (plan left queue/) as an outcome, not a transient kill, so one fast skip can't strand the queue.
last_verified: 2026-06-20
---

# ADR-0066: A deliberate impl skip is not an environmental failure

**Status:** Accepted
**Date:** 2026-06-20

## Context

The automouse runner's impl phase has an environmental-failure breaker: a usage/rate limit, auth error, or transient that kills the impl agent refunds the attempt and **stops the loop**, leaving the whole queue intact to resume next run — this prevents one dead-budget night from grinding every queued plan into `skipped/`. The breaker's strongest transient signal is a sub-minute exit (a real impl runs 15–50 min). But a *legitimate* impl disposition (already-merged, stale-plan, ambiguity, `wt-new` failure, missing-info) also exits fast and writes no handoff. On 2026-06-20 the impl agent correctly skipped `god-class-split-standingsview` (already merged as PR #1146) in 57s; the breaker misread that clean skip as a transient kill, refunded the attempt, and stopped the loop — stranding the other 5 queued plans until the next scheduled fire.

## Decision

The impl env-breaker fires **only when the agent produced no outcome** — no handoff (success) *and* the plan is still in `queue/` — *and* a transient signature is present. A deliberate disposition `mv`s the plan out of `queue/`, so its absence is the discriminator: queue-exit means "outcome, continue the loop," never "transient, stop." The decision is the pure predicate `should_impl_env_stop()` in `bin/automouse-run` (env-stop iff `handoff=0 AND in_queue=1 AND (env_err|stalled|fast)`), and its truth table is locked by the CI-wired regression test `bin/test-automouse-env-breaker`. This mirrors the postplan breaker, which already gates its sub-minute check on the handoff still being present.

## Alternatives Considered

- **String-match the skip reason in the log** — scan the phase log for "already merged"/"stale" before env-stopping. Rejected because: log-string matching is exactly what the sub-minute check exists to be immune to (the async-stderr flush race), and it couples the breaker to disposition phrasing in `bin/automouse-prompt-impl`.
- **Raise `MIN_VIABLE_SECS` so skips look "slow enough"** — Rejected because: a skip and a true transient both legitimately finish in under a minute; no duration threshold separates them, and raising it would let real fast transients slip through.
- **Drop the sub-minute check entirely** — Rejected because: it is the race-immune backbone of the breaker; without it a transient that flushes no known signature before dying would be misread as a genuine plan failure and burn an attempt.

## Consequences

- Positive: a legitimate fast skip continues the loop; the queue is no longer stranded by one already-merged/stale plan at the head.
- Positive: the env-stop decision is a pure, unit-tested predicate (`should_impl_env_stop()` + `bin/test-automouse-env-breaker`), so the load-bearing negative ("a queue-exit is never environmental") can't silently regress.
- Negative: the breaker now trusts that every deliberate disposition path actually `mv`s the plan out of `queue/`; a future skip path that exits fast *without* moving the plan would be (correctly, by this rule) treated as a transient and stop the loop — the disposition-moves-the-plan invariant in `bin/automouse-prompt-impl` is now load-bearing.

## References

- `bin/automouse-run` — `should_impl_env_stop()` predicate and the impl-breaker call site that computes its five boolean inputs.
- `bin/test-automouse-env-breaker` — CI-wired regression test that extracts the predicate from the runner and locks its truth table (incl. the 2026-06-20 fast-skip case).
- `bin/automouse-prompt-impl` — the impl agent whose deliberate dispositions `mv` the plan out of `queue/`.
- `.claude/rules/automouse-workflow.md` — the workflow rule describing the breaker and the disposition-is-not-environmental carve-out.
- `.github/workflows/tests.yml` — wires `bin/test-automouse-env-breaker` into CI alongside the impl-model parser test.
