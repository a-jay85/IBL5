---
description: Usage-limit drain, pause, and auto-resume for headless runners via an env-gated PreToolUse hook, pause markers, a drain token, and a launchd coordinator.
last_verified: 2026-09-28
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
