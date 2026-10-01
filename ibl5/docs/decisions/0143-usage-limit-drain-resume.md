---
description: Usage-limit drain, pause, and auto-resume for headless runners via an env-gated PreToolUse hook, pause markers, a drain token, and a launchd coordinator.
last_verified: 2026-10-01
---

# ADR-0143: Usage-limit drain, pause, and auto-resume

**Status:** Accepted
**Date:** 2026-09-28

## Context

Headless runners (`bin/plan-now`, `bin/post-plan-now`, `bin/automouse/run`) burn into the 5-hour and weekly plan limits and die as generic failures. Nothing drained work before the wall, paused a session cleanly, or resumed it after the window reset.

## Decision

Zones use configurable thresholds, 93 (drain) and 98 (stop). A PreToolUse hook (`~/.claude/hooks/usage-gate.sh`, active only when `IBL5_USAGE_GATE=1`) returns `continue:false` and writes a pause marker for the session in two cases: past the stop line, and in the drain zone when the session does not hold the drain token. The drain token is a `mkdir` lock the coordinator grants by priority, so one runner finishes while the rest pause. Resume goes through each runner's `--resume-paused` entry, which continues the same session id. Usage is cached persistently in the state dir by `bin/lib/usage-fetch.sh`, so a launchd job with no keychain access can read it. The limit-hit and env-error regexes are split, so a 401 or 529 never pauses. The post-plan harness is excluded from the gate env.

Meta-tooling bar: three new `bin/` scripts, `bin/usage-gate-coordinator`, `bin/usage-gate-cron-setup`, and `bin/test-usage-gate`. No existing host owns a periodic usage-driven resume, so the coordinator is a distinct launchd trigger. The installer copies the `bin/wt-sync-cron-setup` shape because a second label in that script would break its single responsibility.

## Alternatives Considered

- **Let runners die and requeue them.** Rejected because: a dead session loses its context and the retry counter burns attempts.
- **Poll usage from each runner.** Rejected because: it needs keychain access per process and gives no cross-runner drain order.
- **Fold the coordinator into `bin/wt-sync-cron-setup`.** Rejected because: it breaks that script's single responsibility.

## Consequences

- Positive: a runner that hits the limit pauses with its session intact and resumes on its own.
- Positive: drain order is deterministic, by priority then oldest pause.
- Negative: if a launchd job ever loses keychain access, the coordinator can read only the usage some other session cached, Once that cache is 300 s old, it resumes a marker only after its recorded reset time, and it DMs a stale-data warning at most every 30 minutes.
- Probe verdict (2026-09-28): a plain launchd job can read the keychain token. The probe plist from `bin/usage-gate-cron-setup --print-probe` sets no `AbandonProcessGroup` key, and it printed `ok 2026-09-29T05:11:15Z`. The coordinator plist sets `AbandonProcessGroup` for a separate reason: without it, launchd kills the runner resumes the coordinator starts when each tick exits.
- Neutral: `bin/post-plan-fleet` and `bin/pr-triage` skip a worktree whose post-plan run is paused, so they never race the coordinator's resume.

## References

- `bin/lib/usage-fetch.sh`
- `bin/test-usage-gate`

## Addendum — Fetch backoff and blind-gate trust (2026-10-01) <!-- slop-ok -->

On 2026-09-30 the usage endpoint answered most live fetches with HTTP 429, and `usage_fetch` logged each one as `reason=bad-body`. Every gated tool call in every session retried the live fetch, in bursts of about 50 per 10 minutes. The retries kept the endpoint rate-limited, so the cache aged past 300 s and the gate failed open. A post-plan-now prestart at 93% was let through.

Two changes follow.

1. `usage_fetch` shares one backoff and one fetch lock across processes, both in the usage-gate state dir. A 429 arms `fetch-backoff` for the Retry-After value when it is numeric (capped at 900 s), else for 30 s doubling per consecutive 429 up to 480 s. Until it expires no process makes a live request. A `mkdir` lock (`fetch.lock.d`, reclaimed after 15 s) allows one live request at a time. A caller that hits the backoff or loses the lock gets the cached body with rc 2 and never waits. A successful fetch clears the backoff. The log names each case: `fetch-failed reason=rate-limited`, `backoff-skip` (once per backoff window) and `fetch-skip reason=in-flight` (once per 30 s). `bad-body` keeps its meaning of a response without `.five_hour.utilization`.

2. The stale-usage fail-open rule changes for `usage_gate_decide` and `usage_prestart_gate`. The gate is blind when the live fetch fails and the cache is 300 s old or more. A blind gate now trusts the last reading when its zone is drain or stop and the limiting window's `resets_at` is still in the future. It acts on that reading exactly as on a fresh one, through the same `usage_marker_write` path, so the invariant stands: no pause output without a marker on disk. It logs `blind-trust zone=<z> age=<n>` and skips the delta log. The gate still fails open when the last reading is in the normal zone, when no reading exists, and when `resets_at` has passed, is missing, or cannot be parsed. A passed reset must not strand work behind a blind gate.

The coordinator is unchanged. Its stale branch only resumes markers whose `resets_at` has passed, so it already errs toward waiting. The max_age values (45, 60 and 120 s) also stay. Failed fetches drove the stampede, and a longer max_age would only add staleness to a zone decision near the 93% threshold.
