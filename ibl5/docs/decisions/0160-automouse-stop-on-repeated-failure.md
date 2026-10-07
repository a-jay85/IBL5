---
description: Automouse stops retrying an impl phase when two attempts fail with the same normalized tool error, instead of spending the Opus final retry on a repeat.
last_verified: 2026-10-02
---

# ADR-0160: Stop Automouse Retries When the Same Failure Repeats

**Status:** Accepted
**Date:** 2026-10-02
**Deciders:** automouse implementation run

## Context

Automouse retries a failed impl phase up to `MAX_ATTEMPTS` (3) times. The final retry escalates to Opus (ADR-0085) and costs $4 to $7 per item. When attempts 1 and 2 fail on the same command with the same error, a third attempt rarely changes the outcome (pipeline review of 2026-10-02, item 2b). No attempt recorded the failing command or the tool error text. The `.failure` sidecar held a raw log tail, overwritten on each failure, so the runner could not tell a repeat from a new failure.

## Decision

The stream filter writes the last failing tool call of each impl attempt (tool name, first command line, error text) to a side file. It never writes that text to the run log, because the env-error scan reads the log. `bin/lib/automouse-failure-signature` normalizes the record into one line: it strips ANSI codes, SHAs, timestamps, dates, epochs, durations, line numbers, temp paths, and directory parts, and keeps small integers. The runner stores the result as a `failure-signature:` line inside the `.failure` sidecar, so the ADR-0085 sidecar lifecycle covers it. On a genuine impl failure from attempt 2 onward, the runner compares the new signature with the stored one as exact strings. A match moves the plan to `skipped/` and writes a skipped report naming the signature and both attempt numbers. An empty signature never matches, so an unrecognized failure keeps today's ladder. Post-plan failures and the model ladder are unchanged.

## Alternatives Considered

- **Fingerprint the raw `.failure` log tail.** Rejected because: it is free-form prose that varies between runs, so equality would almost never fire.
- **Echo tool errors into the run log.** Rejected because: the env-error scan reads that log, and a quoted `API Error: 401` would halt the whole run.
- **Fuzzy similarity match.** Rejected because: it needs a tuned threshold and is hard to test in both directions.

## Consequences

- Positive: a true repeat costs two attempts instead of three.
- Positive: a different second failure still gets the Opus retry.
- Negative: a normalizer that over-strips can stop a recoverable item. The keyword-plus-two-lines window and the kept small integers bound that risk, and the skipped report shows the signature so a human can requeue.

## References

- `bin/lib/automouse-failure-signature`
- `bin/lib/automouse-stream-filter.sh`
- `bin/automouse/run`
- `bin/test-automouse-repeat-failure`
- ADR-0085 (failure-report caps and the final-retry escalation)
