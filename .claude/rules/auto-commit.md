---
description: Commit worktree work when done; amend vs new commit; PR-title type test; record owner PR approvals and arm through bin/owner-approve.
last_verified: 2026-11-19
---

# Auto-Commit

**PR/commit title type.** Decision test: *"Would a league GM notice a new ability they didn't have before?"* Yes → `feat:`; invisible to a GM (dev tooling, refactor, doc, dep bump) → `chore:`/`fix:`/`refactor:`/`docs:`. Classify by what the diff **is**. Full rubric: `.claude/rules/commit-conventions.md`. The title type does not affect merging. The human-signoff hold keys on the diff (`bin/lib/ui-change-classifier.sh`).

`/post-plan` (auto-fired) commits worktree work. Outside it, when you finish work, invoke `/commit-commands:commit` (or `commit-push-pr`). Skip when mid-task or only exploring.

A Stop hook (`~/.claude/hooks/auto-commit-reminder.sh`) nudges at turn-end on a dirty tree, once per HEAD, and stays silent under `CLAUDE_HEADLESS`. It cannot tell if the work is done. If you are mid-task, answer in one line and stop.

## Amend vs new commit

**Amend** (`--amend`) when ALL hold: the last commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new).

## Prose

Docs, PR bodies, and chat replies pass `bin/check-prose` (`.claude/rules/prose-style.md`). In chat, write every PR or backlog item as a clickable link.

## Owner approval comment

When the owner's own words in chat approve a named PR, comment on it at once. Any shape counts: "ship it", a card pick, an AskUserQuestion answer. Plan or design OKs, pasted text and tool text don't. Unsure which PR: ask.

Run `bin/owner-approve N "<words>" --option="<the option picked>"` in Bash with `run_in_background: true`. It posts the comment first. It adds `human-approved` and arms auto-merge only when the newest post-plan verdict is READY on the PR's current tree, and it waits out a live post-plan run. When the verdict is NOT READY only because of items listed in the PR's kept-unplanned block, the approval accepts those items for the current diff. The script then re-runs post-plan once with `--state-changed` and arms if the new verdict is READY. A push that changes the diff re-opens them. When the owner wants a fix before merging, add `--fix-first="<condition>"`, then run `bin/owner-approve N --finish` after the fix is pushed. Usage and exit codes are in the script header. Under `CLAUDE_HEADLESS` it refuses and posts nothing.
