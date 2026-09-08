---
description: Shell scripts under set -o pipefail must use herestrings (grep -q <<< "$var") not printf-pipe-grep-q, which causes SIGPIPE false failures on Linux.
last_verified: 2026-09-08
owner: ajaynicolas
---

# ADR-0123: Shell `pipefail` + `grep -q` Portability Rule

**Status:** Accepted
**Date:** 2026-09-08
**Deciders:** ajaynicolas

## Context

PR #2174 added `bin/test-architect-contract-split` with 37 bold-token assertions using `printf '%s' "$var" | grep -qF -- "$token"` under `set -euo pipefail`. All 37 passed on macOS in development but failed in CI (ubuntu-latest) because `grep` found a match and exited early (exit 0), sending SIGPIPE to `printf` (exit 141). Under `pipefail`, the pipeline's exit status is the leftmost non-zero — 141 — making the `if !` check enter the failure branch even though `grep` succeeded. ShellCheck does not catch this pattern.

## Decision

Under `set -o pipefail`, never pipe into `grep -q` (or `grep -c`). Replace `printf '%s' "$var" | grep -qF -- "$token"` with `grep -qF -- "$token" <<< "$var"`. No pipe means no SIGPIPE possible. This rule is codified in `.claude/rules/shell-pipefail-grep.md` (lazy-loaded, path-scoped to `bin/**`) so it attaches when shell scripts are edited.

## Alternatives Considered

- **`echo "$var" | grep -qF`** — same SIGPIPE hazard; `echo` can also receive SIGPIPE from an early-exiting grep. Rejected for the same reason.
- **`[[ "$var" == *"$token"* ]]`** — bash-only substring test, no grep involved. Rejected: works for plain substring checks but loses grep's fixed-string and regex modes; not a general substitute.
- **`set +o pipefail` guard around the grep** — temporarily disables pipefail for the check. Rejected: silences all pipe failures in the block; easy to scope incorrectly across a refactor.

## Consequences

- Positive: the herestring form is behaviorally identical between Linux and macOS bash, eliminating the macOS-passes/Linux-fails flake class.
- Positive: path-scoped lazy loading means the rule attaches only when `bin/` shell scripts are touched, not on every session.
- Negative: adds a non-obvious rule that ShellCheck won't catch; future authors must read the rule doc or encounter the CI failure to learn it.

## References

- `.claude/rules/shell-pipefail-grep.md` — the operational rule doc for agents
- `bin/test-architect-contract-split` — the harness that exposed the failure
