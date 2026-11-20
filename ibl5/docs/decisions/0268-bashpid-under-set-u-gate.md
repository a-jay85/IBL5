---
description: ADR-0268. A whole-tree CI gate, bin/check-bashpid-under-set-u, rejects bare BASHPID reads in bin/ shell scripts because macOS bash 3.2 has no such variable
last_verified: 2026-11-20
---

# ADR-0268: Gate bare BASHPID reads in bin/ shell scripts

**Status:** Accepted
**Date:** 2026-11-20

## Context

`BASHPID` is a bash 4 variable. macOS `/bin/bash` is 3.2, so a bare read under `set -u` aborts the script with an unbound-variable error. Callers that treat a nonzero exit as a normal result hide the abort. Review caught this twice in one PR (a-jay85/IBL5#3937, `bin/owner-approve`) before it merged (backlog a-jay85/IBL5-backlog#1859).

## Decision

`bin/` shell scripts read the variable only with a default operator, such as `"${BASHPID:-$$}"`. `bin/check-bashpid-under-set-u` scans every shell file under `bin/` and flags a dollar-prefixed or braced read with no `:-`, `-`, `:+` or `+` operator. Comment text is stripped first. CI runs the gate and its harness `bin/test-check-bashpid-under-set-u` in the `static-guards` job.

## Alternatives Considered

- **Rule doc only.** Rejected because the bare read works on Linux CI and on Homebrew bash, so review on those hosts does not catch it.
- **Flag every bash 4 feature.** Rejected for now because the set is open-ended and a broad matcher would need many false-positive exceptions. The gate can grow one variable or builtin at a time.
- **Fold the check into `bin/check-shell-portability`.** Rejected because that gate is a stat-order check with its own statement parser, and the two hazards share no code.

## Consequences

- Positive: a bare `BASHPID` read fails CI before it breaks a macOS run.
- Positive: the gate is a whole-tree scan, so it needs no base ref.
- Negative: comment stripping is quote-blind, so a `#` inside a quoted string can hide part of a line from the scan.
- Negative: the first version of the regex misses three braced reads: `${BASHPID?}`, `${#BASHPID}` and `${BASHPID[0]}`. Their harness cases fail until the regex catches them.
- Negative: one more `bin/check-*` gate and one more `bin/test-*` harness to maintain.

## References

- `bin/check-bashpid-under-set-u`
- `bin/test-check-bashpid-under-set-u`
- `tools/postplan-harness/harness/gate_backtest.py`
