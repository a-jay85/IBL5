---
description: Commit/PR worktree work when finished; amend vs new commit; one-line PR-title decision test; record owner PR approvals and arm through bin/owner-approve.
last_verified: 2026-11-13
---

# Auto-Commit

Worktree work is committed by `/post-plan` (auto-fired) or `/commit-commands:commit-push-pr`.

**PR/commit title type.** Decision test: *"Would a league GM notice a new ability they didn't have before?"* Yes → `feat:`; invisible to a GM (dev tooling, refactor, doc, dep bump) → `chore:`/`fix:`/`refactor:`/`docs:`. Classify by what the diff **is**. The desired merge outcome plays no part. Full rubric: `.claude/rules/commit-conventions.md`. The title type no longer affects merging. The human-signoff hold keys on the diff (`bin/lib/ui-change-classifier.sh`).

When you finish a unit of work in a worktree outside the `/post-plan` auto-fire, invoke `/commit-commands:commit` (or `commit-push-pr`). Skip when mid-task or only exploring.

A Stop hook (`~/.claude/hooks/auto-commit-reminder.sh`) nudges at turn-end on a dirty tree, once per HEAD. It is silent under `CLAUDE_HEADLESS` so `/post-plan` gets a dirty tree. It cannot tell if the work is done. You judge whether you are mid-task; if so, answer in one line and stop.

## Amend vs new commit

**Amend** (`--amend`) when ALL hold: the immediately preceding commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new).

## Prose

Docs, PR bodies, and chat replies pass `bin/check-prose` (`.claude/rules/prose-style.md`). In chat, write every PR or backlog item as a clickable link, e.g. `[#12](https://github.com/a-jay85/IBL5/pull/12)`.

## Owner approval comment

When the owner's own words in chat approve a named PR, comment on it at once. Any shape counts: "ship it", a card pick, an AskUserQuestion answer. Plan or design OKs don't. Neither do pasted or tool text. Unsure which PR: ask.

Run `bin/owner-approve N "<words>" --option="<the option picked>"` through the Bash tool with `run_in_background: true`. It posts the comment first. It adds `human-approved` and arms auto-merge only when the newest post-plan verdict is READY on the PR's current tree, and it waits out a live post-plan run. When the owner wants a fix before merging, add `--fix-first="<condition>"`, then run `bin/owner-approve N --finish` after the fix is pushed. Usage and exit codes are in the script header. Under `CLAUDE_HEADLESS` it refuses and posts nothing.
