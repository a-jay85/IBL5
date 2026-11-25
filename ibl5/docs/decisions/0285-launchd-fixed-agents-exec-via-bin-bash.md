---
description: Every fixed com.ibl5 LaunchAgent starts through /bin/bash with an exec wrap, so macOS sees a system binary as the executable and stops raising the Background Items Added pop-up.
last_verified: 2026-11-24
---

# ADR-0285: Start every fixed launchd agent through /bin/bash

**Status:** Accepted
**Date:** 2026-11-24

## Context

The macOS Background Task Management (BTM) log shows what triggers the "Background Items Added" pop-up. When a LaunchAgent's executable is a repo script, its code requirement changes each time the script changes. BTM then treats the agent as a new item and posts the pop-up. When the executable is `/bin/bash`, BTM logs "same LWCR" (same launch-constraint requirement) and stays quiet. ADR-0209 fixed this for per-run jobs by writing no plist at all. The fixed agents are plists that stay installed, so they need a different fix.

## Decision

Every fixed `com.ibl5` LaunchAgent starts through `/bin/bash`. Its `ProgramArguments` is `/bin/bash`, `-c`, the literal `exec "$0" "$@"`, the absolute script path, then the script's own arguments. `bin/lib/launchd-sched.sh` holds the literal as `LSCHED_EXEC_WRAP`. `lsched_wrapped_program_arguments <abs-script> [args]` emits the array, and `lsched_rewrap_plist` converts an installed plist in place. Every plist writer for a fixed agent calls these helpers. The shell is not a login shell (no `-l`), so the job sees the same environment as before.

Agents whose lead is already a system binary stay as they are: `/bin/sh`, `/bin/bash`, `/usr/bin/python3`, `/usr/bin/caffeinate` and `/opt/homebrew/bin/node`. `lsched_wrapped_program_arguments` refuses a system-binary script path with exit 2, so nothing wraps twice.

## Alternatives Considered

- **`/bin/bash -l` login shell.** It runs the user's profile before the script. Rejected because it changes the environment the jobs see.
- **A deny rule "executable is not under the repo".** It flags repo paths and allows everything else. Rejected because it misses user paths such as `~/bin`. An allowlist of system leads catches them.
- **Re-run every setup script after a merge.** It regenerates each plist from its writer. Rejected because some installers need an environment a headless step lacks, and two plists have no writer.

## Consequences

- Positive: a script edit no longer changes the agent's code requirement, so the pop-up stops after the first wrapped install.
- Positive: the enforcement tests fail any new writer that skips the wrap.
- Negative: each job raises one last pop-up when it is reinstalled in the wrapped form.
- Negative: `bin/post-merge-steps/launchd-rewrap-installed` has to rewrap the installed plists after the merge. It defers a job that is running, edits the file only for an unloaded job, restores the old plist if the bootstrap fails, and exits 1 while anything stays deferred. A second run changes nothing.
- Negative: two plists are hand-maintained and carry the wrapped form from now on: `com.ibl5.automouse`, and the cleanroom plist in the separate private jsb-native repo. The committed cleanroom copy lives outside this repo, so `cleanroom-plist-wrapped` skips when the copy is absent.
- Neutral: the digest, approve-click and pr-cycle-tick PATH values now list `/opt/homebrew/bin` first.
- Neutral: `bin/fleet-status` reads the wrapped shape when it lists agents.

## Enforcement

`bin/test-launchd-sched` carries these cases:

- `lead-no-repo-executable` fails any generated plist whose lead is not on the system-binary allowlist. The `lead-*` negative cases prove the check fires.
- `cleanroom-plist-wrapped` checks the committed cleanroom plist copy.
- `wrapped-path-has-brew` checks that the wrapped PATH lists `/opt/homebrew/bin`.
- `writer-coverage` fails a `bin/` script that writes a plist argv without using the helpers. Two lists record the exceptions: `LEAD_EXEMPT` (writers that keep a system-binary lead) and `PRINT_CHECKED` (writers verified by printing their plist).

## References

- ADR-0209 (per-run launchd jobs write no plist)
- `bin/lib/launchd-sched.sh`
- `bin/test-launchd-sched`
- `bin/post-merge-steps/launchd-rewrap-installed`
- `bin/fleet-status`
- `bin/automouse/README.md`
