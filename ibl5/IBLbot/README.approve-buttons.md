---
description: Prod and Mac runbook for the Discord Approve and Fix first buttons on a held PR. Covers deploy, the Mac poller install, the state files and how to read a lost press.
last_verified: 2026-11-17
---

# Approve Buttons Runbook

A held PR DM can carry an **Approve** and a **Fix first** button. A press is recorded on prod. A Mac job picks it up and runs `bin/owner-approve`. The decision record is `ibl5/docs/decisions/0262-discord-approve-buttons.md`.

## Prod side

The bot reuses `PLAN_REVIEW_OWNER_DISCORD_ID`. An empty value turns the three approve routes off, and each answers 503. Nobody else can press a button, because the handler compares the presser to that id before it answers.

Deploy is the normal `dist/` copy, then `pm2 restart iblbot`. Presses go to `data/approve-clicks.jsonl`, a sibling of `dist/` that a `dist/`-only deploy never overwrites. The `.gitignore` in this directory already ignores `data/`.

Routes, all on 127.0.0.1:50000 and all reached from the Mac over ssh:

| Route | Use |
|-------|-----|
| `POST /discordApproveDM` | Body `{ pr, head_sha }`. Mints a token and sends the button DM. |
| `GET /approveClicks` | Presses that are not yet claimed, inside the 1h claim window. |
| `POST /approveClicks/claim` | Body `{ nonce }`. 200 with the record, 404 unknown, 409 already claimed, 410 lapsed. |

A token lives 24h. A new button for the same PR replaces the older one.

## Mac side

Install once, from the main checkout, after the PR has merged:

```bash
bin/approve-click-cron-setup --install
```

This loads `com.ibl5.approve-click-tick`, which runs `bin/approve-click-tick --tick` every 180s and logs to `~/Library/Logs/approve-click-tick.log`. Remove it with `bin/approve-click-cron-setup --uninstall`. The job needs `gh`, `jq` and a working `ssh iblhoops.net`.

`bin/post-plan-fleet` calls `bin/approve-click-tick --send-button=<pr>` right after the held DM. That call needs no headless override and arms nothing.

Read the pending presses by hand:

```bash
ssh iblhoops.net "curl -s http://localhost:50000/approveClicks"
```

## State files

The Mac ledger is `~/.local/state/approve-click/clicks/<nonce>/`. Each marker file is written once.

| File | Meaning |
|------|---------|
| `claimed` | The Mac took this press. It never runs twice. |
| `record.json` | The press as the bot returned it. |
| `started` | The runner began `bin/owner-approve`. |
| `outcome` | The exit code and the last `RESULT:` line. |
| `reported` | The outcome DM went out. |
| `seen-again` | The bot listed a taken press again, and one DM said so. |

## A "started but recorded no outcome" DM

The runner died after it began. Look at the PR comments for the owner approval, then run `bin/owner-approve <N>` by hand if the PR is not armed. The press stays consumed and nothing re-runs it.

## Fix first

The press records a condition of up to 300 characters. The runner calls `bin/owner-approve <N> --fix-first=<condition>`, and the DM says so. After the fix is pushed, run `bin/owner-approve <N> --finish` on the Mac.

## One-time check after deploy

Before the first held notice carries buttons, run the `GET /approveClicks` command above. It must answer `{"clicks":[]}`.
