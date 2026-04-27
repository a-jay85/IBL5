---
description: Nightly autonomous workflow — launchd fires claude -p at midnight, looping through queued plans with time guards and incremental checkpoints.
last_verified: 2026-04-26
paths: "bin/nightly-*"
---

# Nightly Autonomous Workflow

A headless `claude -p` process runs at 00:03 daily via macOS `launchd`. It loops through all queued plans — one fresh `claude -p` invocation per plan — until the queue is empty or the ~4h45m time guard is exceeded.

## Quick Reference

| Action | Command |
|--------|---------|
| Queue a plan | `bin/nightly-queue <slug>` |
| Show queue | `bin/nightly-queue` (no args) |
| Check morning results | `ls ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/reports/` |
| Cancel tonight's run | `rm ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/queue/*.md` |
| Disable nightly job | `launchctl unload ~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` |
| Re-enable nightly job | `launchctl load ~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` |
| Force-trigger now | `launchctl start com.ibl5.nightly-claude` |
| Check logs | `cat ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/logs/$(date +%Y-%m-%d).log` |

## Directory Layout

```
~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/
  queue/    symlinks to ~/.claude/plans/*.md (oldest runs first)
  done/     symlinks moved here after successful execution
  skipped/  symlinks moved here when skipped (ambiguity/errors/poison-pill)
  reports/  per-night markdown reports (YYYY-MM-DD-{done|skipped|no-queue|error}-<slug>.md)
  logs/     claude -p output logs + launchd stdout/stderr
```

## How It Works

1. **Daytime:** Work with Claude in plan mode. After approval, queue the plan: `bin/nightly-queue <slug>`
2. **00:03:** `launchd` fires `bin/nightly-run`
3. **Loop:** For each queued plan (oldest first), `nightly-run` fires a fresh `claude -p` with `bin/nightly-prompt`. Each invocation gets a clean context window.
4. **Per plan:** Claude creates a worktree (or resumes an existing one), implements, makes incremental checkpoint commits, runs `/post-plan` (tests, code review, security audit, commit, push, PR)
5. **Guards:** The loop stops when the queue is empty or 4 hours have elapsed. Plans that fail twice are moved to `skipped/` as poison pills.
6. **Morning:** Check `gh pr list` for new PRs, read reports for details

## Queue Mechanism

`bin/nightly-queue` creates **symlinks** in `queue/` pointing to the original plan in `~/.claude/plans/`. The original plan never moves. After execution, the symlink is moved to `done/` or `skipped/`.

Cancelling: `rm queue/<file>.md` removes only the symlink, not the original plan.

## Multi-Plan Loop

`bin/nightly-run` loops through the queue, firing a fresh `claude -p` per plan. Each invocation processes exactly one plan, then exits. The bash loop provides:

- **Time guard:** Won't start a new plan if >4h45m have elapsed (configurable via `MAX_ELAPSED` in `bin/nightly-run`)
- **Poison-pill protection:** Tracks attempts per plan via `.attempts` sidecar files. After 2 failures in one night, the plan is moved to `skipped/` with a report. Prevents one bad plan from consuming the entire window.
- **Turn cap:** `--max-turns 200` per invocation prevents runaway tool-call loops

## Incremental Checkpoints

Each `claude -p` invocation commits and pushes after major milestones (migration applied, PHP sweep done, tests passing). If the session dies mid-plan — usage limit, crash, timeout — the branch has the latest checkpoint. The next invocation detects the existing worktree and resumes from where it left off.

## Resume

When a plan's worktree already exists (from a previous interrupted run), Claude checks the branch state:
- **Commits ahead of master:** Reads commit messages to determine what's done, continues from there
- **Branch merged on master:** Stale worktree — removes it and starts fresh
- **No commits ahead:** Treats as fresh worktree, starts implementation

## 11am Resume

When the loop exits with plans still in the queue (time guard exceeded or usage limit hit), `nightly-run` writes a one-shot launchd plist (`com.ibl5.nightly-resume`) that fires at 11:00 AM PT — after peak usage hours. On entry, the resume run removes its own plist so it doesn't repeat. The resume run is a normal `nightly-run` invocation: it picks up the queue, resumes partial worktrees, and applies the same time guard and poison-pill logic.

The resume plist lives at `~/Library/LaunchAgents/com.ibl5.nightly-resume.plist` and is ephemeral — created only when needed, deleted on next run.

## Headless Mode

`bin/nightly-run` sets `CLAUDE_HEADLESS=1`. This environment variable gates `/post-plan` Phase 11 (Worktree Preview Environment), which is skipped since no human is present to verify visually. All other phases run normally.

## Files

- `bin/nightly-queue` — symlink queue helper
- `bin/nightly-prompt` — prompt text (source of truth for what claude -p receives)
- `bin/nightly-run` — wrapper script called by launchd (loop + time guard + 11am resume)
- `~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` — launchd schedule (00:03 daily)
- `~/Library/LaunchAgents/com.ibl5.nightly-resume.plist` — one-shot resume (created dynamically, self-cleaning)
