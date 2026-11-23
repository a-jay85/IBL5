---
description: What post-plan prints on a blocked ship (exit 3), the bin/postplan-fix paste line, and the failure-DM hold.
last_verified: 2026-11-20
paths:
  - ".claude/skills/post-plan/SKILL.md"
  - ".claude/skills/ship/SKILL.md"
  - "tools/postplan-harness/**"
no-adr: lazy companion holding text moved from workflow-continuity-detail.md; no new decision surface
---

# Post-plan blocked ship

## Blocked-ship report (exit 3)

On exit 3 the harness writes a plain-words block to `blocked-ship.txt` in its run dir: what stopped the ship, the offending paths when the hook output names them, and numbered copy-paste fix commands ending in `bin/post-plan-now`. `bin/post-plan-now` prints that block between `=== post-plan blocked ship ===` marker lines, or a plain block of the same shape when the file is missing. The DM below and the `bin/automouse/run` skip report carry the same block. When a command failed, the one-line `RESULT:` verdict and the block name it and quote the last few lines of its error, with credentials redacted. Otherwise they name the stage where the run stopped. The block ends with a paste line, `<main checkout>/bin/postplan-fix <PR|slug>`, which opens an interactive Claude session in the worktree with the failing stage, the error text, and the log paths in its opening prompt. It does not ship. With `--headless` it instead fetches, rebases onto `origin/master`, runs one Sonnet 5.5 session with the rung's denied-tool list to resolve the markers and fix tests, leaves the rebased commit locally (it never pushes), and prints the `bin/post-plan-now` paste line. `--headless --dry-run` prints the argv and prompt without touching git. The post-plan-fleet failure DMs and the pr-cycle-tick retry-cap DM end with the same line.

## Failure DM hold

`bin/post-plan-fail-dm` sends the DM at once when no live interactive Claude session
(headless `claude -p` does not count) is in the worktree. Otherwise it holds the DM 15 min
and sends it only if nobody re-fired the branch.
