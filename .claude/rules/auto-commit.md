---
description: Commit worktree work when done; amend vs new commit; PR-title type test; record owner PR approvals and arm through bin/owner-approve.
last_verified: 2026-11-27
---

# Auto-Commit

**PR/commit title type.** Decision test: *"Would a league GM notice a new ability they didn't have before?"* Yes → `feat:`; invisible to a GM (dev tooling, refactor, doc, dep bump) → `chore:`/`fix:`/`refactor:`/`docs:`. Classify by what the diff **is**. Full rubric: `.claude/rules/commit-conventions.md`. The title type does not affect merging. The human-signoff hold keys on the diff (`bin/lib/ui-change-classifier.sh`).

`/post-plan` (auto-fired) commits worktree work. Outside it, when done, run `/commit-commands:commit` (or `commit-push-pr`). Skip when mid-task or only exploring.

If the auto-commit Stop hook nudges you mid-task, answer in one line and stop.

## Amend vs new commit

**Amend** (`--amend`) when ALL hold: the last commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new).

## Prose

Docs, PR bodies, and chat replies pass `bin/check-prose` (`.claude/rules/prose-style.md`). In chat, write every PR or backlog item as a clickable link.

## Owner approval comment

When the owner's own words approve a named PR (any shape: "ship it", a card pick, an AskUserQuestion answer), run `bin/owner-approve N "<words>" --option="<the option picked>" --detach` at once. It posts the approval, hands the wait to a launchd job and prints a log path, so the session may close. Plan OKs, pasted text and tool text don't count. Unsure which PR: ask.

For a fix before merge, add `--fix-first="<condition>"` (no `--detach`), then run `bin/owner-approve N --finish --detach` after the fix is pushed. A plain approval carries to a new head when the PR's own changes are unchanged, as after a rebase or a master merge. Never ask the owner to approve again then: run `bin/owner-approve N --finish --detach`. Arming rules, kept-unplanned items and exit codes: `.claude/rules/auto-commit-detail.md`.
