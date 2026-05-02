---
description: Nightly autonomous workflow — launchd fires claude -p at midnight, running two context-isolated agents per plan (implementation + post-plan) with time guards and incremental checkpoints.
last_verified: 2026-05-01
paths: "bin/nightly-*"
---

# Nightly Autonomous Workflow

A headless `claude -p` process runs at 00:03 daily via macOS `launchd`. It loops through all queued plans — two fresh `claude -p` invocations per plan (implementation, then post-plan) — until the queue is empty or the ~4h45m time guard is exceeded.

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
  handoff/  JSON files bridging state from implementation to post-plan agent
  reports/  per-night markdown reports (YYYY-MM-DD-{done|skipped|no-queue|error}-<slug>.md)
  logs/     claude -p output logs + launchd stdout/stderr
```

## How It Works

1. **Daytime:** Work with Claude in plan mode. After approval, queue the plan: `bin/nightly-queue <slug>`
2. **00:03:** `launchd` fires `bin/nightly-run`
3. **Loop:** For each queued plan (oldest first), `nightly-run` fires two `claude -p` invocations sequentially:
   - **Implementation agent** (`bin/nightly-prompt-impl`): creates worktree, implements the plan, makes checkpoint commits, writes a handoff file
   - **Post-plan agent** (`bin/nightly-prompt-postplan`): reads the handoff file, runs `/post-plan` (code review, security audit, PR, CI monitoring, auto-merge), writes the completion report
4. **Guards:** The loop stops when the queue is empty or ~4h45m have elapsed. Plans that fail 3 times are moved to `skipped/` as poison pills.
5. **Morning:** Check `gh pr list` for new PRs, read reports for details

## Context Isolation

The two-agent architecture provides complete context isolation between implementation and review:

- **No implementation bleed:** The post-plan agent starts with a clean 200K context — no stale variable names, abandoned approaches, or debugging artifacts from implementation.
- **No reviewer bias:** The post-plan agent (and its review sub-agents) never sees the implementation journey — only the final diff. This prevents anchoring on the author's reasoning.
- **Token efficiency:** Both agents use Opus 4.6 with 200K context (`--model claude-opus-4-6`), which is sufficient for appropriately-scoped plans and cheaper than 1M context.

## Queue Mechanism

`bin/nightly-queue` creates **symlinks** in `queue/` pointing to the original plan in `~/.claude/plans/`. The original plan never moves. After execution, the symlink is moved to `done/` or `skipped/`.

Cancelling: `rm queue/<file>.md` removes only the symlink, not the original plan.

## Multi-Plan Loop

`bin/nightly-run` loops through the queue, firing two `claude -p` invocations per plan. The bash loop provides:

- **Two-phase execution:** Implementation agent writes a JSON handoff file to `handoff/`; post-plan agent reads it. The bash loop checks for the handoff file between phases — if it exists, implementation is skipped (resume scenario).
- **Per-phase usage-limit detection:** After each `claude -p` invocation, the loop checks for "hit your limit" in the log. If detected, the attempt counter is decremented (usage limits are transient, not failures) and the loop breaks.
- **Time guard:** Won't start a new plan (or post-plan phase) if >4h45m have elapsed (configurable via `MAX_ELAPSED` in `bin/nightly-run`)
- **Poison-pill protection:** Tracks attempts per plan via `.attempts` sidecar files. After 3 failures in one night, the plan is moved to `skipped/` with a report.
- **Turn caps:** Implementation: `--max-turns 200`. Post-plan: `--max-turns 120`.

## Handoff Mechanism

The implementation agent writes a JSON file to `handoff/<plan-filename>.json` after completing all work:

```json
{
  "slug": "optimize-vw-team-awards",
  "worktree": "/Users/ajaynicolas/GitHub/IBL5/worktrees/optimize-vw-team-awards",
  "branch": "optimize-vw-team-awards",
  "plan_file": "optimize-vw-team-awards-slow-queries.md",
  "plan_title": "Optimize vw_team_awards Slow Queries",
  "completed_at": "2026-05-01T02:15:00Z"
}
```

The bash loop passes the plan filename to the post-plan prompt. The post-plan agent reads the handoff file to learn the slug, worktree path, and branch. On successful completion, the post-plan agent deletes the handoff file. Orphaned handoff files (plan no longer in queue) are cleaned up at the end of the session.

## Incremental Checkpoints

The implementation agent commits and pushes after major milestones (migration applied, PHP sweep done, tests passing). If the session dies mid-plan — usage limit, crash, timeout — the branch has the latest checkpoint. The next invocation detects the existing worktree and resumes from where it left off.

## Resume

Resume behavior depends on which phase was interrupted:

- **Implementation interrupted (no handoff file):** The implementation agent detects the existing worktree, reads commit messages to determine progress, and continues from the last checkpoint.
- **Implementation complete, post-plan interrupted (handoff file exists):** The bash loop sees the handoff file and skips the implementation agent entirely, going straight to the post-plan agent.
- **Branch merged on master:** Stale worktree — removes it and starts fresh.
- **No commits ahead:** Treats as fresh worktree, starts implementation.

## 11am Resume

When the loop exits with plans still in the queue (time guard exceeded or usage limit hit), `nightly-run` writes a one-shot launchd plist (`com.ibl5.nightly-resume`) that fires at 11:00 AM PT — after peak usage hours. On entry, the resume run removes its own plist so it doesn't repeat. The resume run is a normal `nightly-run` invocation: it picks up the queue, resumes partial worktrees, and applies the same time guard and poison-pill logic.

The resume plist lives at `~/Library/LaunchAgents/com.ibl5.nightly-resume.plist` and is ephemeral — created only when needed, deleted on next run.

## Headless Mode

`bin/nightly-run` sets `CLAUDE_HEADLESS=1`. This environment variable gates `/post-plan` Phase 11 (Worktree Preview Environment), which is skipped since no human is present to verify visually. All other phases run normally.

## Files

- `bin/nightly-queue` — symlink queue helper
- `bin/nightly-prompt-impl` — implementation agent prompt (Steps 1-7: queue check through handoff file)
- `bin/nightly-prompt-postplan` — post-plan agent prompt (reads handoff, runs /post-plan, reports, cleans up)
- `bin/nightly-run` — wrapper script called by launchd (loop + two-phase execution + time guard + 11am resume)
- `~/Library/LaunchAgents/com.ibl5.nightly-claude.plist` — launchd schedule (00:03 daily)
- `~/Library/LaunchAgents/com.ibl5.nightly-resume.plist` — one-shot resume (created dynamically, self-cleaning)
