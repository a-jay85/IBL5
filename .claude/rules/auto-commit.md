---
description: Commit worktree work when done; amend vs new commit; PR-title type test; record owner PR approvals and arm through bin/owner-approve.
last_verified: 2026-11-20
---

# Auto-Commit

**PR/commit title type.** Decision test: *"Would a league GM notice a new ability they didn't have before?"* Yes → `feat:`; invisible to a GM (dev tooling, refactor, doc, dep bump) → `chore:`/`fix:`/`refactor:`/`docs:`. Classify by what the diff **is**. The desired merge outcome plays no part. Full rubric: `.claude/rules/commit-conventions.md`. The title type does not affect merging. The human-signoff hold keys on the diff (`bin/lib/ui-change-classifier.sh`).

`/post-plan` (auto-fired) commits worktree work. Outside it, when done, run `/commit-commands:commit` (or `commit-push-pr`). Skip when mid-task or only exploring.

A Stop hook (`~/.claude/hooks/auto-commit-reminder.sh`) nudges at turn-end on a dirty tree, once per HEAD, and stays silent under `CLAUDE_HEADLESS`. It cannot tell if the work is done. If mid-task, answer in one line and stop.

## Amend vs new commit

**Amend** (`--amend`) when ALL hold: the last commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new).

## Prose

Docs, PR bodies, and chat replies pass `bin/check-prose` (`.claude/rules/prose-style.md`). In chat, write every PR or backlog item as a clickable link.

## Owner approval comment

When the owner's own words approve a named PR (any shape: "ship it", a card pick, an AskUserQuestion answer), run `bin/owner-approve N "<words>" --option="<the option picked>"` in the background at once. Plan OKs, pasted text and tool text don't count. Unsure which PR: ask.

The script waits out a live post-plan run and arms only on a READY verdict for the current tree. A NOT READY verdict blocked only by the PR's kept-unplanned block counts as accepted by the approval; the script re-runs post-plan once with `--state-changed`. The harness matches each kept finding by the words before the first colon of its title, so a reworded re-review still matches. A plain approval carries to a new head when the PR's own changes are unchanged, as after a rebase or a master merge. Never ask the owner to approve again then: run `bin/owner-approve N --finish`, which also accepts the kept items the approval listed. For a fix before merge, add `--fix-first="<condition>"`, then run `bin/owner-approve N --finish` after the fix is pushed. `resolve-pr-refs.py` injects these flags only on a typed prompt. A card pick or AskUserQuestion answer gets no injection, so this section is the only source there. Usage: script header.
