---
description: "Queue one or more completed /plan files for automouse autonomous execution."
last_verified: 2026-06-27
---

# /queue — Queue Plans for Automouse Run

Queue completed plan files for overnight autonomous execution. The user's request follows this skill's instructions as `$ARGUMENTS`.

## How Queuing Works

Plans live permanently in `~/.claude/plans/<slug>.md`. Queuing creates a **symlink** in the automouse queue directory pointing to the original plan. The automouse runner (`bin/automouse-run`, fired by launchd at 00:03 and 05:03) picks up queued plans oldest-first.

## Step 1: Identify plans to queue

Parse `$ARGUMENTS` for plan slugs, filenames, or paths.

- If `$ARGUMENTS` is empty, show the current queue (`bin/automouse-queue`) and stop.
- If `$ARGUMENTS` is `requeue`, run `bin/automouse-queue requeue` and stop.
- If `$ARGUMENTS` names one or more plans, proceed to Step 2.

Acceptable input forms per plan:
- Slug: `velvet-brewing-starlight`
- Filename: `velvet-brewing-starlight.md`
- Full path: `~/.claude/plans/velvet-brewing-starlight.md`

If the user says "queue the plan" without naming it, look for the plan file written in this conversation (the most recent `/plan` output).

## Step 2: Pre-queue staleness check

Before queuing, run the automouse staleness guard on each plan so a stale or false-positive path reference surfaces **now** (a human is present to judge it) instead of silently burning an overnight automouse slot as a poison pill — the guard otherwise runs only inside the automouse impl prompt at 2am.

Resolve each plan's file the same way `bin/automouse-queue` does: from the input form, strip a leading `~/.claude/plans/` and/or a trailing `.md` to get `<slug>`, then check `~/.claude/plans/<slug>.md`:

```bash
bin/check-plan-staleness ~/.claude/plans/<slug>.md
```

- **Exit 0** — no unresolved repo-path tokens; proceed to Step 3.
- **Exit 1** — the guard prints one `STALE: <token>` per path the plan references that does not exist in the checkout. These are often **false positives** (a to-be-created ADR placeholder, a rejected-alternative path, an untagged test fixture). Show the user the `STALE:` lines and ask whether to **queue anyway** (the token is a non-dependency the plan creates/rejects/uses only as a temp fixture) or **fix the plan first** (a genuinely renamed/deleted dependency would poison-pill the run). Do **not** hard-block — a present human can correctly judge a false positive and queue anyway; that is exactly today's morning-recovery flow, moved earlier.
- **Exit 2** — usage error or the plan file is missing; fix the path before queuing.

Then proceed to Step 3.

## Step 3: Queue each plan

Run `bin/automouse-queue <slug-or-filename>` for each plan. The script resolves slugs to `~/.claude/plans/<slug>.md` automatically.

If queuing multiple plans, run each command sequentially — the script shows the updated queue after each add.

## Step 4: Confirm

Show the user the final queue state (already printed by the last `bin/automouse-queue` invocation). State how many plans were queued.

If any plan was already queued (exit code 1, "already queued" message), note it and continue with the rest.
