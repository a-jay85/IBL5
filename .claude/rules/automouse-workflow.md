---
description: Automouse autonomous workflow (formerly "nightly") — launchd fires claude -p on a recurring schedule, running two context-isolated agents per plan (implementation + post-plan) with time guards and incremental checkpoints.
last_verified: 2026-06-20
paths: "bin/automouse-*"
---

# Automouse Autonomous Workflow

> **"Automouse" is this pipeline — the autonomous plan-execution machinery (`bin/automouse-*`, this rule).** It was **formerly called "nightly"**; the term was renamed because the user runs it outside nighttime too, so "nightly" was a misnomer that sent people hunting through `cron` / `/schedule` / `CronCreate` / launchd-by-hand. When you read "automouse" (or legacy "nightly") referring to autonomous plan execution, it means **`bin/automouse-run` fired by launchd**, draining the queue built by `bin/automouse-queue` — *not* a generic scheduler. (The macOS `launchd` agent is the scheduling substrate, but the concept lives in these scripts.)

A headless `claude -p` process runs on a recurring schedule via macOS `launchd`. It loops through queued plans — two `claude -p` invocations per plan (implementation, then post-plan) — until the queue is empty or the time guard is exceeded.

## Quick Reference

| Action | Command |
|--------|---------|
| Queue a plan | `bin/automouse-queue <slug>` |
| Show queue | `bin/automouse-queue` (no args) |
| Remove a plan from queue | `bin/automouse-queue remove <slug>` |
| Check morning results | `ls ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/reports/` |
| Cancel the next run | `rm ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/queue/*.md` |
| Schedule a one-shot run | `bin/automouse-run schedule "2026-05-28 20:00 PDT"` (self-cleaning launchd agent; TZ optional) |
| Disable the automouse job | `launchctl unload ~/Library/LaunchAgents/com.ibl5.automouse.plist` |
| Re-enable the automouse job | `launchctl load ~/Library/LaunchAgents/com.ibl5.automouse.plist` |
| Force-trigger now | `launchctl start com.ibl5.automouse` |
| Requeue skipped plans | `bin/automouse-queue requeue` |
| Check logs | `cat ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/logs/$(date +%Y-%m-%d).log` |

## Directory Layout

```
~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/
  queue/    symlinks to ~/.claude/plans/*.md (oldest runs first)
  done/     symlinks moved here after successful execution
  skipped/  symlinks moved here when skipped (ambiguity/errors/poison-pill)
  handoff/  JSON files bridging state from implementation to post-plan agent
  reports/  per-run markdown reports (YYYY-MM-DD-{done|skipped|env-stop|no-queue|error}-<slug>.md);
            plus YYYY-MM-DD-costs.md — per-phase token cost roll-up written by automouse-run
  logs/     claude -p output logs + launchd stdout/stderr
  *.archive/  startup archival: logs/reports/done/skipped entries idle >7 days are
              moved here (logs.archive/, reports.archive/, …) at run launch
```

### Startup archival

At launch, `automouse-run` sweeps `logs/`, `reports/`, `done/`, and `skipped/` and moves any
entry untouched for more than `NIGHTLY_ARCHIVE_AGE_DAYS` (default **7**) into a sibling
`<dir>.archive/`. This keeps the working dirs small without deleting history. Symlinks
(`done/`, `skipped/`) are judged on their *own* mtime — the disposition date — and their
absolute targets keep resolving after the move. `queue/` (pending work) and `handoff/`
(transient) are never touched. The step is non-fatal: an archival error never aborts the run.

## How It Works

1. **Daytime:** Work with Claude in plan mode. After approval, queue the plan: `bin/automouse-queue <slug>`
2. **On schedule:** `launchd` fires `bin/automouse-run`
3. **Loop:** For each queued plan (oldest first), `automouse-run` fires two `claude -p` invocations sequentially:
   - **Implementation agent** (`bin/automouse-prompt-impl`): creates worktree, implements the plan, makes checkpoint commits, writes a handoff file. Its model is selectable per-plan via a line-1 `impl_model:` frontmatter field (`sonnet` → Sonnet, `haiku` → Haiku, absent or anything else → the Opus default), resolved by `bin/lib/plan-impl-model`; declare `sonnet` only for uniformly-mechanical plans whose every verification row is objectively machine-checkable. The post-plan agent is always Sonnet.
   - **Post-plan agent** (`bin/automouse-prompt-postplan`): reads the handoff file, runs `/post-plan` (code review, security audit, PR, CI monitoring, auto-merge), writes the completion report
4. **Guards:** The loop stops when the queue is empty or ~4h45m have elapsed. Plans that fail 3 times (after genuine, full-length attempts) are moved to `skipped/` as poison pills.
   - **Environmental failures stop the run cleanly instead of skipping.** A usage/rate limit, auth error, or any transient that kills an agent — detected by a known limit/auth signature in the log, by a sub-minute phase exit (a real impl/postplan runs 15–50 min), *or* by a watchdog stall-kill (no stream events for 10 min, e.g. a dead/wedged inference stream) — refunds the attempt and breaks the loop, leaving the **entire queue intact** to resume next run. This prevents the failure mode where one dead-budget run ground every queued plan into `skipped/`. Each such stop writes a `YYYY-MM-DD-env-stop-<slug>.md` report. A **deliberate impl disposition is not environmental**: when the impl agent legitimately skips a plan (already-merged, stale-plan, ambiguity, wt-new failure, missing-info) it moves the plan out of `queue/`, which the breaker treats as an outcome (not a transient kill) and the loop continues to the next plan — even though that skip also exits fast with no handoff. The impl decision lives in `should_impl_env_stop()` and is locked by `bin/test-automouse-env-breaker`.
5. **After a run:** Check `gh pr list` for new PRs, read reports for details

## Headless Mode

`bin/automouse-run` sets `CLAUDE_HEADLESS=1`. This environment variable gates `/post-plan` Phase 10 (Preview Environment), which is skipped since no human is present to verify visually. All other phases run normally.

## Feature PRs cannot auto-merge

Conventional-commit **`feat:`** PRs are gated by the required `human-signoff` check and will **not** auto-merge unattended — they wait for a human to apply the `human-approved` label after inspection (ADR-0062). `/post-plan` Phase 6.5 condition (8) deterministically **never arms** a `feat:` PR (a literal title grep), so there is no arm-then-strip; the required `human-signoff` check remains the independent floor that blocks the merge regardless. Maintenance PRs (`fix`/`refactor`/`chore`/`ci`/`docs`/`revert`) auto-merge as before — still subject to Phase 6.5's other conditions, including the PR-time safety verdict (9) on the realized diff. Check `gh pr list` afterward for `feat:` PRs awaiting your label.
