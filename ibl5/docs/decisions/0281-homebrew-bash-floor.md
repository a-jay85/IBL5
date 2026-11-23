---
description: ADR-0281. Repo shell scripts need bash 4 or newer. The Mac runs them on Homebrew bash, bin/check-shell-portability enforces env-bash shebangs and a version guard, and the BASHPID gate from ADR-0268 is retired.
last_verified: 2026-11-23
---

# ADR-0281: Repo shell scripts need bash 4 or newer

**Status:** Accepted
**Supersedes:** ADR-0268
**Date:** 2026-11-23
**Deciders:** repo owner

## Context

macOS ships bash 3.2 at `/bin/bash`. It lacks `BASHPID`, associative arrays, `mapfile` and other bash 4 features. CI and production run Linux bash 5, so a script that uses one of them passes CI and then breaks on the Mac. ADR-0268 added a gate for one such variable, `BASHPID`. That gate needed two fixes in one week (a-jay85/IBL5#4081, a-jay85/IBL5#4159). Each new bash 4 feature would need its own gate.

## Decision

Repo shell scripts need bash 4 or newer. The Mac runs them on Homebrew bash at `/opt/homebrew/bin/bash`.

- Every bash script starts with `#!/usr/bin/env bash`, so PATH picks the shell.
- Every executable bash script under `bin/`, `ibl5/bin/` and `tools/` carries a one-line version guard before its first command. Under bash 3.2 the guard prints `brew install bash` and exits 2. It does not re-exec under another shell.
- `bin/check-shell-portability` fails a pull request that adds an absolute-path bash shebang or an executable script with a missing or moved guard.
- launchd plists and login shells put `/opt/homebrew/bin` ahead of `/usr/bin` in PATH. `bin/lib/launchd-sched.sh` holds the plist PATH value.

## Alternatives Considered

- **Keep gating one bash 4 feature at a time.** Rejected because the feature list is open-ended and each gate needs its own harness and fixes.
- **Re-exec under Homebrew bash from the guard.** Rejected because a silent re-exec hides a broken PATH. A loud exit 2 names the fix.
- **Add a macOS CI leg.** Rejected for its runner cost. The static gate and the run-time guard catch the same failures.

## Consequences

- Positive: scripts may use bash 4 features. The Mac fails loudly with the fix named when PATH reaches the old shell.
- Positive: `bin/check-bashpid-under-set-u` (example) and its harness are retired.
- Negative: a Mac without Homebrew bash cannot run repo scripts until it installs it.
- Negative: hook scripts under `bin/hooks/` and fixture files carry no guard, so a hook that runs under bash 3.2 fails without the guard's message.
- Neutral: existing code keeps its 3.2-safe style. Nothing rewrites it to use bash 4 features.

## References

- `bin/check-shell-portability`
- `bin/test-check-shell-portability`
- `bin/lib/launchd-sched.sh`
- `.claude/rules/shell-portability.md`
- `ibl5/docs/decisions/0268-bashpid-under-set-u-gate.md`
