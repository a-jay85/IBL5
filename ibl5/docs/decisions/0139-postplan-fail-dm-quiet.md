---
description: The post-plan exit-3 Discord DM moves into bin/post-plan-fail-dm, which sends at once when no Claude session is live in the worktree and otherwise holds the DM 15 minutes in a launchd job, dropping it if the branch was re-fired.
last_verified: 2026-09-26
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0139: Hold the Post-Plan Exit-3 DM While a Claude Session Is Live in the Worktree

**Status:** Accepted
**Date:** 2026-09-26
**Deciders:** post-plan harness (auto-draft)

## Context

`bin/adr-check` flagged two new developer tools on this branch: `bin/post-plan-fail-dm` and its test script `bin/test-post-plan-fail-dm`. Both exist to change one alert. When the compiled harness launched by `bin/post-plan-now` exits 3, the run has hit a wall a human must clear: a rebase conflict, a diverged remote head, or a local gate denial such as a missing ADR. Until this change the exit-3 branch of `GATE_CLOSE` echoed the message and called `bin/discord-dm` straight away. In practice the Claude session that fired the run is often still open in that worktree. It reads the same message on stdout, fixes the cause, and re-fires within minutes. The DM in that case asked the human to do nothing, and a steady run of such DMs teaches the reader to skip the ones that do need action.

## Decision

The exit-3 branch of `GATE_CLOSE` in `bin/post-plan-now` passes the message to `bin/post-plan-fail-dm`, which owns the DM from then on. The helper reads the Claude session registry and looks for an entry whose repo resolves to the worktree root and whose pid is alive. With no such session it DMs at once. With one, it touches a stamp file beside the run log, writes a plist, and bootstraps a launchd job that sleeps `FAILDM_DELAY` seconds (900 by default) and then re-invokes the helper with `--deferred`. The deferred run looks for a `post-plan-now` log for the same branch slug that is newer than the stamp. The filename match requires the date-shaped suffix, so branch `foo` never matches a log for `foo-bar`. A newer log means someone re-fired, and the held DM is dropped because that newer run DMs on its own if it also fails. No newer log means nobody acted, and the DM goes out prefixed with "Nobody re-ran it within 15 min". Every uncertain path fails open to an immediate DM: an unreadable registry counts as no session, and a failed `launchctl bootstrap` removes the plist and stamp and sends at once. The held job deletes its own plist and boots itself out when done. Enforcement is `bin/test-post-plan-fail-dm`, whose eight cases stub `launchctl` and `bin/discord-dm` and run as a step in `.github/workflows/tests.yml`. `test_exit3_dm_and_echo_send_the_same_single_string` in `tools/postplan-harness/tests/test_post_plan_now_fallback.py` pins that the echo and the helper receive the same single `$msg`.

## Alternatives Considered

- **Keep the immediate DM.** Leave `bin/discord-dm` inline in `GATE_CLOSE`. Rejected because: it keeps sending the alert in the common case where a live session fixes the wall within minutes, which is the noise this change removes.
- **Drop the DM whenever a session is live.** Skip the delay and the re-fire check. Rejected because: an open session may be idle or its human may have left, and the failure would then go unreported with nothing to recover it.
- **Hold with a child sleep.** Background a `sleep` inside the run and skip the launchd plist. Rejected because: the run itself executes as a launchd job, and launchd reaps a job's process group when it exits, so a child sleep dies with the run and the DM is lost.
- **Re-check session liveness at wake time.** Suppress the DM if the session is still open after the delay. Rejected because: an open session says nothing about whether anyone acted. A newer log for the branch is direct evidence of a re-fire.

## Consequences

- Positive: an exit-3 DM now reaches the human only when no session is positioned to fix the wall, or when the session had 15 minutes and did not re-fire.
- Positive: each fail-open path reproduces the old behavior, so a broken registry or an unavailable `launchctl` costs noise and never a lost alert.
- Positive: a re-fire that fails again sends its own DM, so the human sees the latest failure once.
- Negative: when a live session does not act, the DM arrives up to 15 minutes later than before.
- Negative: liveness is a pid check. A session left open and idle in the worktree still triggers the hold, which is where the delay above comes from.
- Negative: the hold depends on macOS launchd. On a host without it the bootstrap fails and the helper sends at once, so the quieting only applies on the developer machine.
- Negative: any re-fire of the branch suppresses the held DM, including one fired by a different actor for an unrelated reason.

## References

- `bin/post-plan-fail-dm`: the session check, the stamp and re-fire check, the launchd hold, and the fail-open fallbacks.
- `bin/test-post-plan-fail-dm`: the eight regression cases with stubbed `launchctl` and DM command.
- `bin/post-plan-now`: the `GATE_CLOSE` exit-3 branch that now calls the helper.
- `bin/discord-dm`: the DM transport the helper still uses, with `--quiet --attempts 2`.
- `tools/postplan-harness/tests/test_post_plan_now_fallback.py`: the single-string invariant for the exit-3 message.
- `.github/workflows/tests.yml`: the CI step that runs `bin/test-post-plan-fail-dm`.
- `.claude/rules/workflow-continuity-detail.md`: the engine notes describing when the exit-3 DM is sent or held.
- `ibl5/docs/decisions/README.md`: the decision-record policy `bin/adr-check` enforces.
