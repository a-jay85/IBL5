---
description: Nightly autonomous workflow — launchd fires claude -p at midnight to implement one queued plan and produce a PR.
last_verified: 2026-04-13
paths: "bin/nightly-*"
---

# Nightly Autonomous Workflow

A headless `claude -p` process runs at 00:03 daily via macOS `launchd`, implementing one queued plan per night and producing a PR ready for morning review.

## Quick Reference

| Action | Command |
|--------|---------|
| Queue a plan | `bin/nightly-queue <slug>` |
| Show queue | `bin/nightly-queue` (no args) |
| Check morning results | `ls ~/.claude/projects/-Users-ajaynicolas-Documents-GitHub-IBL5/nightly/reports/` |
| Cancel tonight's run | `rm ~/.claude/projects/-Users-ajaynicolas-Documents-GitHub-IBL5/nightly/queue/*.md` |
| Disable nightly job | `launchctl unload ~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` |
| Re-enable nightly job | `launchctl load ~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` |
| Force-trigger now | `launchctl start com.ibl5.nightly-claude` |
| Check logs | `cat ~/.claude/projects/-Users-ajaynicolas-Documents-GitHub-IBL5/nightly/logs/$(date +%Y-%m-%d).log` |

## Directory Layout

```
~/.claude/projects/-Users-ajaynicolas-Documents-GitHub-IBL5/nightly/
  queue/    symlinks to ~/.claude/plans/*.md (oldest runs first)
  done/     symlinks moved here after successful execution
  skipped/  symlinks moved here when skipped (ambiguity/errors)
  reports/  per-night markdown reports (YYYY-MM-DD-{done|skipped|no-queue|error}-<slug>.md)
  logs/     claude -p output logs + launchd stdout/stderr
```

## How It Works

1. **Daytime:** Work with Claude in plan mode. After approval, queue the plan: `bin/nightly-queue <slug>`
2. **00:03:** `launchd` fires `bin/nightly-run`, which runs `claude -p` with `bin/nightly-prompt`
3. **Claude:** Reads queue, validates plan, creates worktree, implements, runs `/post-plan` (tests, code review, security audit, commit, push, PR)
4. **Morning:** Check `gh pr list` for new PRs, read reports for details

## Queue Mechanism

`bin/nightly-queue` creates **symlinks** in `queue/` pointing to the original plan in `~/.claude/plans/`. The original plan never moves. After execution, the symlink is moved to `done/` or `skipped/`.

Cancelling: `rm queue/<file>.md` removes only the symlink, not the original plan.

## Headless Mode

`bin/nightly-run` sets `CLAUDE_HEADLESS=1`. This environment variable gates `/post-plan` Phase 11 (Worktree Preview Environment), which is skipped since no human is present to verify visually. All other phases run normally.

## Files

- `bin/nightly-queue` — symlink queue helper
- `bin/nightly-prompt` — prompt text (source of truth for what claude -p receives)
- `bin/nightly-run` — wrapper script called by launchd
- `~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` — launchd schedule
