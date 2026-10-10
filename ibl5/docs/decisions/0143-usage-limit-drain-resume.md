---
description: Usage-limit drain, pause, and auto-resume for headless runners via an env-gated PreToolUse hook, pause markers, a drain token, and a launchd coordinator.
last_verified: 2026-10-10
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

## Addendum (2026-09-30): the post-plan harness joins the gate

This addendum narrows one sentence of the Decision. The post-plan harness now runs gated. The skill leg's `GATE_ENV` is unchanged.

- `bin/post-plan-now` exports three context vars into the harness launch: `IBL5_USAGE_GATE_RUNNER`, `IBL5_USAGE_GATE_RESUME_BIN`, and `IBL5_USAGE_GATE_SESSION_ID`, the run's session id S. It does not export `IBL5_USAGE_GATE=1`. The harness adapter sets that flag on its own `claude` children after it validates the context. An older harness, such as the main checkout during version skew, therefore runs ungated as before.
- `usage_gate_decide` keys the marker, the drain-token check, and the delta row on `IBL5_USAGE_GATE_SESSION_ID` when it is set. Unset keeps the hook's own session id. Set-but-empty or a malformed value fails open, because a marker keyed on a child session would have no saved argv and would strand.
- Toolless harness calls cannot fire the PreToolUse hook, so the adapter runs the same `usage_gate_decide` before every spawn. A pause stops every harness thread at its next call. The harness exits 75 only while a marker for S is on disk, and it clears that marker on every other exit.
- A harness pause raised while a tooled edit left the worktree changed is fail-closed. The harness clears the marker and exits 3 for a human, since a resumed run would otherwise commit a partial edit.
- Resume replays `post-plan-now --resume-paused S`. The harness has no resumable state, so the run re-enters at its start. Git and GitHub steps are already idempotent on re-entry. Review-comment posts are skipped when an earlier launch under the same S already posted them at the same head, using a ledger at `runs/<S>.effects.json` in the gate state dir.

## Addendum (2026-10-08): login switch fast resume

On 2026-10-08 a `/login` to a second account left three paused sessions waiting about 15 minutes. The cache drop from the 2026-10-01 addendum fired. The first fetch under the new token then got a 429 with `retry-after: 3598`, the shared backoff armed for 900 s, and the coordinator's stale branch kept waiting for the old account's `resets_at`.

- **Account identity.** `~/.claude.json` `.oauthAccount.accountUuid` survives a token refresh and changes on a switch. Its 16-hex sha256 (`acct_fp`) is stamped on each marker and kept as `acct-last` beside the cache. The raw id is never stored or logged. Reading it needs no keychain.
- **Switch signal.** `usage_fetch` writes the `cred-switch` state file at its cache-drop site unless both account fingerprints are known and equal. The coordinator also writes it, and drops the cache, when the cached reading's account differs from the current one.
- **Short first fetches.** Within 1800 s of the signal, up to 3 rate-limited fetches under the new fingerprint back off for at most 45 s. After that the normal arm applies. A 429 with no signal behaves as before.
- **Fast resume.** In the stale branch, a marker whose `acct_fp` differs from the current account resumes on the next tick. When either account is unknown, a marker paused before the signal, or one carrying another `cred_fp`, resumes instead. Each marker takes one fast resume per switch (`switch_resumed_for`). `check_runaway` and `usage_marker_eligible` still apply.
- **Keychain access.** The coordinator may be able to read the keychain (the setup probe reported `ok`), and the design does not depend on it. The library comments claim only that it may lack access.

A switch now resumes paused work within about one coordinator tick, without a fresh reading. The pause hook re-pauses a session if the new account is near its limit too. A same-account token refresh never fast-resumes while `~/.claude.json` is readable. When it is unreadable, a refresh can cost one extra resume per marker, capped by the 5-resume runaway rule. If a Claude Code release stops writing `oauthAccount`, the signal fallback applies. If it writes the field but stops updating it on `/login`, the account test vetoes the fast path and the coordinator waits for `resets_at` as it did before this addendum.

## Addendum: account switch clears the runaway cap (2026-10-10)

The addendum above kept `check_runaway` ahead of every resume. A marker paused 5 times under one account was marked stuck, and a later `/login` to a fresh account never resumed it. On 2026-10-10 the burndown loop sat stuck at the old account's 94% weekly reading while the new account read 69%.

`check_runaway` now clears the pause count and the stuck flag when the marker's `acct_fp` and the current account are both known and differ. The 5-pause cap counts pauses within one account's usage window, so a new account starts a new count. The reset records the current account in the marker's `reset_acct`, leaving `acct_fp` for the switch fast-resume, so it fires once per switch even when the resumed run exits without pausing; a run that keeps failing then hits the 5-resume cap under the new account. When either account is unknown, the cap still applies as before. Tests: `test_coord_runaway_account_switch`, `test_coord_runaway_same_account_stays_stuck`, and the updated `test_coord_switch_respects_runaway_and_hold` in `bin/test-usage-gate`.
