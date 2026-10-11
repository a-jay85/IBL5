---
description: Automouse checks the Claude login before each queue item with a zero-token probe, parks the queue and sends one Discord DM per outage when the login has expired, and fails open on any ambiguous probe.
last_verified: 2026-10-02
---

# ADR-0159: Automouse login preflight

**Status:** Accepted
**Date:** 2026-10-02

## Context

Ten automouse runs ended in an env stop within 1 to 6 seconds because the Claude OAuth session had expired (`Failed to authenticate: OAuth session expired and could not be refreshed`, `cost=$0`). That string matches neither `USAGE_ENV_ERROR_RE` in `bin/lib/usage-gate.sh` nor anything but the sub-minute clause of `should_impl_env_stop`. Each night burned an attempt on the first item, wrote an env-stop report, and sent no alert anyone acted on. `claude auth status` is a local read that reports `loggedIn: true` for credentials whose refresh token has expired, so it cannot detect expiry alone.

## Decision

Before each queue item, `bin/automouse/run` calls `login_preflight` from `bin/lib/login-preflight.sh`. The call sits before `claim_next_plan` and before the attempt-counter write. The probe spends zero inference tokens and is tiered on positive signals only:

1. `claude auth status` exits 1 with `"loggedIn": false`.
2. The keychain `refreshTokenExpiresAt` is at or before now.
3. The `api/oauth/usage` endpoint answers 401 or 403 while the access token is still unexpired.

Every ambiguity fails open. The loop logs a WARNING and writes a dated `login-probe-inconclusive` report, and the env-stop breaker stays the backstop. On a positive expiry the loop parks: `break`, queue intact, no lock, no attempt. It sends one Discord DM per outage. An O_EXCL-created `login-expired.alerted` marker in `$NIGHTLY_DIR` dedupes the alert, and the next `ok` verdict deletes it. `bin/test-automouse-login-preflight` covers the behavior in CI with stubbed `claude`, `security`, and `curl` binaries.

## Alternatives Considered

- **`claude auth status` alone.** A local read of the stored login. Rejected because it reports `loggedIn: true` for credentials whose refresh token expired a day ago.
- **A real `claude -p` call.** The only end-to-end check. Rejected because it spends tokens on every item.
- **Park on any non-`ok` verdict.** The simplest rule. Rejected because a false "expired" would idle the whole pipeline every night.

## Consequences

- Positive: an expired login stops the queue before any attempt is spent, and one DM tells the user to re-login.
- Positive: the probe never writes the token anywhere. The tier-3 header travels on curl's stdin, so it stays out of `ps`.
- Negative: a login revoked server-side while the access token is already expired is not caught. The first item then env-stops as before, and the next run probes again.
- Negative: re-login stays manual (`claude auth login`).

## References

- `bin/lib/login-preflight.sh`
- `bin/automouse/run`
- `bin/test-automouse-login-preflight`
- `bin/lib/usage-gate.sh`
- ADR-0143 (`ibl5/docs/decisions/0143-usage-limit-drain-resume.md`): the park-and-resume precedent.
- ADR-0066 (`ibl5/docs/decisions/0066-automouse-deliberate-skip-not-environmental.md`): a deliberate automouse skip is not an environmental failure.
