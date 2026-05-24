---
description: "Queue one or more completed /plan files for nightly autonomous execution."
last_verified: 2026-05-24
---

# /queue — Queue Plans for Nightly Run

Queue completed plan files for overnight autonomous execution. The user's request follows this skill's instructions as `$ARGUMENTS`.

## How Queuing Works

Plans live permanently in `~/.claude/plans/<slug>.md`. Queuing creates a **symlink** in the nightly queue directory pointing to the original plan. The nightly runner (`bin/nightly-run`, fired by launchd at 00:03 and 05:03) picks up queued plans oldest-first.

## Step 1: Identify plans to queue

Parse `$ARGUMENTS` for plan slugs, filenames, or paths.

- If `$ARGUMENTS` is empty, show the current queue (`bin/nightly-queue`) and stop.
- If `$ARGUMENTS` is `requeue`, run `bin/nightly-queue requeue` and stop.
- If `$ARGUMENTS` names one or more plans, proceed to Step 2.

Acceptable input forms per plan:
- Slug: `velvet-brewing-starlight`
- Filename: `velvet-brewing-starlight.md`
- Full path: `~/.claude/plans/velvet-brewing-starlight.md`

If the user says "queue the plan" without naming it, look for the plan file written in this conversation (the most recent `/plan` output).

## Step 2: Queue each plan

Run `bin/nightly-queue <slug-or-filename>` for each plan. The script resolves slugs to `~/.claude/plans/<slug>.md` automatically.

If queuing multiple plans, run each command sequentially — the script shows the updated queue after each add.

## Step 3: Confirm

Show the user the final queue state (already printed by the last `bin/nightly-queue` invocation). State how many plans were queued.

If any plan was already queued (exit code 1, "already queued" message), note it and continue with the rest.
