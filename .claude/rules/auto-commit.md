---
description: Commit/PR work from the worktree autonomously when finished; never ask the user whether to commit. A Stop hook nudges at turn-end on a dirty tree. Amend only unpushed fixes. Carries the one-line PR-title decision test (full rubric in commit-conventions.md).
last_verified: 2026-09-16
---

# Auto-Commit

Worktree work is committed by `/post-plan` (auto-fired) or `/commit-commands:commit-push-pr`.

**PR/commit title type.** Decision test: *"Would a league GM notice a new ability they didn't have before?"* Yes → `feat:` (this trips the human-signoff hold, which is the gate working as designed); invisible to a GM (dev tooling, a slash command, an internal refactor, a doc, a dep bump) → `chore:`/`fix:`/`refactor:`/`docs:`. Classify by what the diff **is**. The desired merge outcome plays no part. Full rubric: `.claude/rules/commit-conventions.md`.

When you finish a unit of work in a worktree and are not using the `/post-plan` auto-fire handoff, invoke `/commit-commands:commit` (or `commit-push-pr`). Skip when mid-task or only exploring.

A Stop hook (`~/.claude/hooks/auto-commit-reminder.sh`, outside the repo) nudges at turn-end on a dirty tree, once per HEAD. It is silent under `CLAUDE_HEADLESS` so `/post-plan` still gets the dirty tree it needs. It cannot judge whether the work is finished; that call stays yours. If mid-task, answer in one line and stop.

## Amend vs new commit

You decide.

**Amend** (`--amend`) when ALL hold: the immediately preceding commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new, the safer choice).

Heuristic: same task + unpushed + correcting yourself = amend.

## Prose

Docs, PR bodies, and chat replies pass `bin/check-prose` (tell list and fixes: `.claude/rules/prose-style.md`).
