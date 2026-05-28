---
description: Nightly autonomous workflow — launchd fires claude -p at 00:03 and 05:03 daily, running two context-isolated agents per plan (implementation + post-plan) with time guards and incremental checkpoints.
last_verified: 2026-05-28
paths: "bin/nightly-*"
---

# Nightly Autonomous Workflow

A headless `claude -p` process runs twice daily via macOS `launchd`. It loops through queued plans — two `claude -p` invocations per plan (implementation, then post-plan) — until the queue is empty or the time guard is exceeded.

## Quick Reference

| Action | Command |
|--------|---------|
| Queue a plan | `bin/nightly-queue <slug>` |
| Show queue | `bin/nightly-queue` (no args) |
| Remove a plan from queue | `bin/nightly-queue remove <slug>` |
| Check morning results | `ls ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/reports/` |
| Cancel tonight's run | `rm ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/queue/*.md` |
| Schedule a one-shot run | `bin/nightly-run schedule "2026-05-28 20:00 PDT"` (self-cleaning launchd agent; TZ optional) |
| Disable nightly job | `launchctl unload ~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` |
| Re-enable nightly job | `launchctl load ~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` |
| Force-trigger now | `launchctl start com.ibl5.nightly-claude` |
| Requeue skipped plans | `bin/nightly-queue requeue` |
| Check logs | `cat ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/logs/$(date +%Y-%m-%d).log` |

## Directory Layout

```
~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/nightly/
  queue/    symlinks to ~/.claude/plans/*.md (oldest runs first)
  done/     symlinks moved here after successful execution
  skipped/  symlinks moved here when skipped (ambiguity/errors/poison-pill)
  handoff/  JSON files bridging state from implementation to post-plan agent
  reports/  per-night markdown reports (YYYY-MM-DD-{done|skipped|no-queue|error}-<slug>.md);
            plus YYYY-MM-DD-costs.md — per-phase token cost roll-up written by nightly-run
  logs/     claude -p output logs + launchd stdout/stderr
```

## How It Works

1. **Daytime:** Work with Claude in plan mode. After approval, queue the plan: `bin/nightly-queue <slug>`
2. **00:03 and 05:03:** `launchd` fires `bin/nightly-run`
3. **Loop:** For each queued plan (oldest first), `nightly-run` fires two `claude -p` invocations sequentially:
   - **Implementation agent** (`bin/nightly-prompt-impl`): creates worktree, implements the plan, makes checkpoint commits, writes a handoff file
   - **Post-plan agent** (`bin/nightly-prompt-postplan`): reads the handoff file, runs `/post-plan` (code review, security audit, PR, CI monitoring, auto-merge), writes the completion report
4. **Guards:** The loop stops when the queue is empty or ~4h45m have elapsed. Plans that fail 3 times are moved to `skipped/` as poison pills.
5. **Morning:** Check `gh pr list` for new PRs, read reports for details

## Headless Mode

`bin/nightly-run` sets `CLAUDE_HEADLESS=1`. This environment variable gates `/post-plan` Phase 10 (Preview Environment), which is skipped since no human is present to verify visually. All other phases run normally.
