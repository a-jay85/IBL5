---
description: ADR for the nightly autonomous workflow — headless Claude executes queued plans via launchd at midnight.
last_verified: 2026-04-13
---

# ADR-0007: Nightly Autonomous Workflow

**Status:** Accepted
**Date:** 2026-04-13

## Context

Daytime plan-mode sessions generate implementation plans that are approved but not always executed in the same session. Manually resuming plans the next day wastes context and momentum. The user wanted plans to execute overnight autonomously, producing PRs ready for morning review, without requiring an interactive REPL to be open.

## Decision

Use macOS `launchd` to fire a headless `claude -p` process at 00:03 daily. The process reads one queued plan (symlinked from `~/.claude/plans/`), creates a worktree, implements the plan, and runs `/post-plan` for code review, security audit, testing, and PR creation. The `CLAUDE_HEADLESS=1` environment variable gates `/post-plan` Phase 11 (Worktree Preview Environment) since no human is present to verify visually.

Enforcement: `launchd` plist at `~/Library/LaunchAgents/com.ibl5.nightly-claude.plist`. Queue managed by `bin/nightly-queue`. Prompt authored in `bin/nightly-prompt`.

## Alternatives Considered

- **CronCreate (durable, in-REPL)** — session-local cron that fires while the REPL is idle. Rejected because: requires keeping a Claude Code REPL running overnight; context window pollution from daytime conversation; multi-instance ambiguity.
- **RemoteTrigger (cloud)** — Anthropic-hosted remote agent on a cron schedule. Rejected because: no Docker/MariaDB access for testing; no local filesystem access for plan files; limited tool set.
- **Manual resume** — user re-opens the plan the next morning. Rejected because: wastes time, breaks flow, and doesn't leverage overnight compute.

## Consequences

- Positive: Plans execute overnight without human presence, producing review-ready PRs by morning.
- Positive: Symlink-based queue preserves original plans in `~/.claude/plans/` while tracking execution state.
- Positive: `CLAUDE_HEADLESS` env var is a clean, extensible gate for headless-specific behavior in any skill.
- Negative: Mac must remain powered on overnight.
- Negative: `--dangerously-skip-permissions` grants full tool access to the headless agent — mitigated by plan validation and skip-on-ambiguity behavior.

## References

- `bin/nightly-queue` — symlink queue helper
- `bin/nightly-prompt` — self-contained prompt for headless execution
- `bin/nightly-run` — launchd wrapper script
- `.claude/rules/nightly-workflow.md` — workflow documentation
- `.claude/skills/post-plan/SKILL.md` — Phase 11 `$CLAUDE_HEADLESS` gate
