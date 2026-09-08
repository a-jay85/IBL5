---
description: Avoid printf-pipe-grep-q under set -o pipefail — use herestring to prevent SIGPIPE false failures on Linux.
last_verified: 2026-09-08
paths: "bin/**"
---

# Shell Pipefail + grep -q Portability

## Rule

Under `set -o pipefail`, never write:

```bash
printf '%s' "$var" | grep -qF -- "$token"
```

When grep finds a match and exits early, printf gets SIGPIPE (exit 141). Under pipefail, the pipeline's exit status is the leftmost non-zero — 141 — even though grep succeeded with 0. The `if !` check then enters the failure branch on macOS-passing, Linux-failing grounds.

**Use a herestring instead:**

```bash
grep -qF -- "$token" <<< "$var"
```

No pipe means no SIGPIPE possible. Behavior is identical between Linux and macOS bash.

## Why

PR #2174's `bin/test-architect-contract-split` used the printf-pipe form under `set -uo pipefail`. All 37 bold-token assertions passed on macOS in development but failed in CI (ubuntu-latest) because grep exited early on every match, sending SIGPIPE to printf. `bin/test-architect-contract-split: line 197: printf: write error: Broken pipe` appeared alongside each false-failure line.

## Applies to

Any shell script with `set -o pipefail` (or `set -euo pipefail`) that pipes into `grep -q` or `grep -c`. The same fix applies to `grep -qE`, `grep -qP`, etc.

ShellCheck does not catch this pattern — it passes SC lint.
