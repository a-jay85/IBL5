---
description: ADR-0235. A whole-tree CI gate, bin/check-shell-portability, rejects BSD-first stat -f calls in bin/ shell scripts; GNU stat -c must come first
last_verified: 2026-11-08
---

# ADR-0235: Gate BSD-first `stat -f` calls in bin/ shell scripts

**Status:** Accepted
**Date:** 2026-11-08

## Context

macOS ships BSD `stat` and Linux CI ships GNU `stat`. On Linux, `stat -f` selects filesystem status: it prints a block of text to stdout and exits 1. A BSD-first fallback such as `stat -f %m f || stat -c %Y f` captures that text plus the real epoch, and the next arithmetic test fails. Such lines pass on a Mac and break on CI or a Linux host. Several `bin/` scripts carried the BSD-first order, and `.claude/rules/shell-portability.md` already covered `date` but said nothing about `stat`.

## Decision

`bin/` shell scripts call GNU `stat -c` first and BSD `stat -f` second. `bin/check-shell-portability` enforces the order. It scans every shell file under `bin/` and flags a `stat -f` call with a format operand when no `stat -c` call comes earlier in the same statement. A line that is correct another way, such as a probe-picked GNU branch or output validated before use, ends with `# portability-ok: <reason>`. CI runs the gate and its harness `bin/test-check-shell-portability` on each pull request, and `bin/run-meta-checks-local` runs both at pre-push.

## Alternatives Considered

- **Rule doc only.** Rejected because the bad order passes on macOS, so review on a Mac does not catch it.
- **A shared `stat_mtime` helper in `bin/lib/`.** Rejected because scripts that do not source a lib would still need a check, and the gate is the cheaper way to keep the order right.
- **Fold the check into `bin/check-pipefail-grep-q`.** Rejected because that gate scans a diff range for a different hazard, while this one scans the whole tree.

## Consequences

- Positive: a BSD-first `stat` line fails CI before it can break a Linux run.
- Positive: the gate is a whole-tree scan, so it needs no base ref and replays in the gate backtest registry as a tree gate.
- Negative: comment stripping is quote-blind, so a `#` inside a quoted string can hide part of a line from the scan.
- Negative: one more `bin/check-*` gate and one more `bin/test-*` harness to maintain.

## References

- `bin/check-shell-portability`
- `bin/test-check-shell-portability`
- `.claude/rules/shell-portability.md`
- `bin/run-meta-checks-local`
- `tools/postplan-harness/harness/gate_backtest.py`
