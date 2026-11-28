---
description: bin/owner-approve arming mechanics and the commit-nudge Stop hook. Lazy companion to auto-commit.md.
last_verified: 2026-11-27
paths:
  - "bin/owner-approve"
  - "bin/test-owner-approve"
no-adr: lazy companion holding text moved from auto-commit.md; no new decision surface
---

# Auto-commit detail

## Owner-approve mechanics

`bin/owner-approve` posts the comment first. It then waits out a live post-plan run and arms only on a READY verdict for the current tree. A NOT READY verdict blocked only by the PR's kept-unplanned block counts as accepted by the approval; the script re-runs post-plan once with `--state-changed`. An approval covers a kept finding when one approved line cites every changed file the finding cites, at the approved diff, so a reworded re-review still matches. A finding that cites no changed file is not covered. On a carried approval, `--finish` also accepts the kept items the approval listed. `resolve-pr-refs.py` injects the flags only on a typed prompt. A card pick or AskUserQuestion answer gets no injection; the run command and the `--fix-first` and `--finish` flags in `.claude/rules/auto-commit.md` § Owner approval comment cover that case. Usage and exit codes: script header.

## Commit-nudge Stop hook

`~/.claude/hooks/auto-commit-reminder.sh` nudges at turn-end on a dirty tree, once per HEAD, and stays silent under `CLAUDE_HEADLESS`. It cannot tell if the work is done.
