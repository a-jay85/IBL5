---
description: Commit/PR worktree work when finished; amend vs new commit; one-line PR-title decision test.
last_verified: 2026-10-05
---

# Auto-Commit

Worktree work is committed by `/post-plan` (auto-fired) or `/commit-commands:commit-push-pr`.

**PR/commit title type.** Decision test: *"Would a league GM notice a new ability they didn't have before?"* Yes → `feat:` (trips the human-signoff hold by design); invisible to a GM (dev tooling, refactor, doc, dep bump) → `chore:`/`fix:`/`refactor:`/`docs:`. Classify by what the diff **is**. The desired merge outcome plays no part. Full rubric: `.claude/rules/commit-conventions.md`.

When you finish a unit of work in a worktree outside the `/post-plan` auto-fire, invoke `/commit-commands:commit` (or `commit-push-pr`). Skip when mid-task or only exploring.

A Stop hook (`~/.claude/hooks/auto-commit-reminder.sh`) nudges at turn-end on a dirty tree, once per HEAD. It is silent under `CLAUDE_HEADLESS` so `/post-plan` gets a dirty tree. It cannot tell if the work is done. You judge whether you are mid-task; if so, answer in one line and stop.

## Amend vs new commit

**Amend** (`--amend`) when ALL hold: the immediately preceding commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new).

## Prose

Docs, PR bodies, and chat replies pass `bin/check-prose` (`.claude/rules/prose-style.md`). In chat, write every PR or backlog item as a clickable link, e.g. `[#12](https://github.com/a-jay85/IBL5/pull/12)`.
