---
description: Host-side gh calls in the burndown and merge-line scripts retry only transient GitHub failures through one sourced helper, bin/lib/gh-retry.sh, with a fixed backoff and the final exit code passed through. A 2026-10-22 addendum adds a read mode, gh_retry_json, for the All checks settled reads.
last_verified: 2026-10-22
---

# ADR-0195: Retry transient gh failures through one sourced helper

**Status:** Accepted
**Date:** 2026-10-11
**Deciders:** repo tooling maintainers

## Context

`bd_fetch_issues` in `bin/lib/burndown.sh` and the live GraphQL fetch in `bin/merge-line-select` called `gh` once. A short GitHub outage (HTTP 502 or 503, a timeout, a reset connection) failed a whole burndown batch or a merge-line pick at once. `bin/burndown-loop` then slept a flat 600s after each `/burndown` abort and retried, so a service that stayed down was hit at the same rate every time.

## Decision

1. `bin/lib/gh-retry.sh` defines `gh_retry <cmd> [args...]`. It is sourced, never run, and reports through return codes only, so it is safe under a caller's `set -euo pipefail` on bash 3.2 and 5.
2. It retries only when the failed attempt's stderr names an HTTP 5xx, a gateway error, a timeout, or a reset or refused connection. An `HTTP 4xx` never retries, even when a timeout word also appears. Any other failure, including a silent non-zero exit, fails at once.
3. Backoff comes from `IBL5_GH_RETRY_DELAYS` (default `5 20 60`: one try plus three retries). A malformed value returns 64 and never runs the command.
4. The final attempt's exit code is returned unchanged. Every attempt's stderr is replayed, and only the final attempt's stdout is printed, so callers parsing JSON never see a partial earlier response.
5. `bin/burndown-loop` doubles its post-abort sleep per consecutive abort (600s, 1200s, 2400s, ...).
6. `bin/test-gh-retry` pins the classification, backoff, and pass-through contract and runs in `.github/workflows/tests.yml` under `env -i PATH=/usr/bin:/bin`.

## Alternatives Considered

- **Inline retry loops in each caller.** Rejected because two copies of the classification would drift, and each would need its own tests.
- **Retry every non-zero exit.** Rejected because a 4xx (bad query, missing auth, missing object) never heals on retry, and retrying it only delays the real error.
- **`gh api --retry` or a gh extension.** Rejected because `gh` has no built-in retry flag for these calls, and an extension adds an install step on every host.

## Consequences

- Positive: a brief GitHub 5xx no longer fails a burndown batch or a merge-line fetch.
- Positive: one helper and one harness own the retry rules, so a new caller gets the same behaviour by sourcing it.
- Negative: a persistent 5xx now takes about 85 seconds to fail each wrapped call, where it used to fail at once.
- Negative: the second consecutive burndown abort sleeps 1200s where it used to sleep 600s.

## References

- `bin/lib/gh-retry.sh`
- `bin/test-gh-retry`
- `bin/lib/burndown.sh`
- `bin/merge-line-select`
- `bin/burndown-loop`
- ADR-0162 (burndown loop weekly pacing)

## Addendum 2026-10-22: read mode for the settle aggregator

`bin/lib/gh-retry.sh` gains `gh_retry_json`, a read mode for idempotent GETs. It shares one loop, `_gh_retry_run`, with `gh_retry`. The strict classifier is unchanged for `bin/lib/burndown.sh` and `bin/merge-line-select`, and strict mode keeps HTTP 429 in the never-retry bucket because no incident justifies changing those callers.

Read mode retries any non-zero exit unless stderr names an HTTP 4xx other than 429, so a silent failure, a network error and HTTP 429 all retry. On exit 0 it checks the body with `jq -e 'true'` and retries an empty or non-JSON body. Exhaustion returns the last non-zero exit, or 65 when the last attempt exited 0 with a bad body. It returns 1 without running the command when `jq` is not on PATH. Read mode must never wrap a command that reads its payload from stdin.

The callers are `bin/settle-pr-status` and `bin/check-pr-checks-green` (ADR-0149 addendum 2026-10-22). Each sets a 2s/5s budget through `IBL5_GH_RETRY_DELAYS`. Cases J1-J13 in `bin/test-gh-retry` pin read mode, and G1-G11 still pin strict mode.
