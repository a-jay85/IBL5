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

### Post-plan carry

Post-plan carries an owner approval across its own fix commits. After it posts its verdict comment, the harness runs `bin/owner-approve N --carry` when a dropped approval is what holds the PR. The script re-adds `human-approved` only when three rules hold:

- Every diff fingerprint step since the newest genuine approval is a link in the local approval ledger. The harness writes a link when it makes a fix commit, and the ledger sits under the git common dir, so no push can add one.
- The verdict on the head is READY or READY WITH NOTES.
- No linked file is under `LOCAL_GATE_PATH_PREFIXES` in `tools/postplan-harness/harness/fidelity.py`, which now lists `bin/owner-approve`.

A PR that touches a UI path never carries. The CI `human-approved-fingerprint` job still decides: it keeps the label only on a bot record equal to the current fingerprint. The carry never arms and never waits. Its last line is `RESULT: carried`, `RESULT: carry-unchanged` (exit 0) or `RESULT: not-carried` (exit 4, nothing posted), and bad flags exit 2. A carry comment never counts as an approval.

A fix-first `--finish` records the files the fix changed. A kept-unplanned line that cites only those files, gate files excluded, then counts as owner-requested scope. So `--finish` may re-fire post-plan once.

### Detached mode

`bin/owner-approve N "<words>" --detach` posts the approval from the session. It then submits launchd job `com.ibl5.owner-approve-N`, which runs `bin/owner-approve N --finish`. The log is `~/Library/Caches/com.ibl5.owner-approve/owner-approve-N.log`. The job DMs the owner on every exit except an armed one. A second `--detach` while the job is listed exits 7 and posts nothing. A `--fix-first` posted while the job waits makes the job refuse before it arms. A failed launch leaves the approval in place, and `bin/owner-approve N --finish --detach` resumes it. `bin/approve-click-tick` keeps the foreground form. The script avoids `nohup` because a `nohup` child dies with the Bash tool's process group. Closing the session no longer cancels a waiting approval. To cancel it, run `launchctl remove com.ibl5.owner-approve-N`.

## Commit-nudge Stop hook

`~/.claude/hooks/auto-commit-reminder.sh` nudges at turn-end on a dirty tree, once per HEAD, and stays silent under `CLAUDE_HEADLESS`. It cannot tell if the work is done.
