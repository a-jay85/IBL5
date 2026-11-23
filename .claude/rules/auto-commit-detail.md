---
description: bin/owner-approve arming mechanics and the commit-nudge Stop hook. Lazy companion to auto-commit.md.
last_verified: 2026-11-20
paths:
  - "bin/owner-approve"
  - "bin/test-owner-approve"
no-adr: lazy companion holding text moved from auto-commit.md; no new decision surface
---

# Auto-commit detail

## Owner-approve mechanics

`bin/owner-approve` posts the comment first. It then waits out a live post-plan run and arms only on a READY verdict for the current tree. A NOT READY verdict blocked only by the PR's kept-unplanned block counts as accepted by the approval; the script re-runs post-plan once with `--state-changed`. The harness matches each kept finding by the words before the first colon of its title, so a reworded re-review still matches. On a carried approval, `--finish` also accepts the kept items the approval listed. `resolve-pr-refs.py` injects the flags only on a typed prompt. A card pick or AskUserQuestion answer gets no injection; the run command and the `--fix-first` and `--finish` flags in `.claude/rules/auto-commit.md` § Owner approval comment cover that case. Usage and exit codes: script header.

## Commit-nudge Stop hook

`~/.claude/hooks/auto-commit-reminder.sh` nudges at turn-end on a dirty tree, once per HEAD, and stays silent under `CLAUDE_HEADLESS`. It cannot tell if the work is done.
