# Bug-Report Bot (Mac-local)

A **second** Discord bot process — a distinct Discord app/token from the prod
IBLbot — that turns a dedicated bug-report channel into a work queue. It runs on
the Mac only, never on prod. It writes nothing to MySQL directly: every DB effect
goes through PR #3's `/api/v1/bug-pipeline/*` PHP endpoints.

Two I/O directions:
- **Inbound (Discord → PHP):** `MessageCreate`, `MessageReactionAdd`, and a
  `ClientReady` backfill forward events to the pipeline endpoints.
- **Outbound-command (cron → Discord):** six loopback Express endpoints
  (`127.0.0.1:50001`) let the PR #5 cron drive Discord actions.

## One-time setup

1. **Create the SECOND Discord application + bot token** in the Developer Portal
   (separate from the prod IBLbot app — one token cannot hold two gateway
   connections). **Enable the `MESSAGE CONTENT` privileged intent** on this new
   app; without it the bot connects but reads empty `message.content`.
2. **Invite the new bot to the guild** with permissions: View Channel / Read
   Messages, Read Message History, Add Reactions, Create Public Threads, Send
   Messages, Send Messages in Threads.
3. `cp .env.bugbot.example .env.bugbot` and fill in:
   - `BUG_BOT_DISCORD_TOKEN` — the new app's token
   - `BUG_PIPELINE_API_BASE_URL` — the always-up main stack `http://main.localhost/ibl5`
     (NOT a worktree slug, torn down on merge; NOT prod)
   - `API_KEY` — a valid `ibl_api_keys` key; **must be a high/unlimited rate-limit
     tier** so startup backfill isn't 429-throttled into silently dropping reports
   - `BUG_CHANNEL_ID` — the dedicated bug-report channel snowflake

## Start

```bash
cd ibl5/IBLbot && npm run build && cd ../..
bin/bug-pipeline-cron-setup --install-bot
```

That installs and loads the `com.ibl5.bug-bot` LaunchAgent (`RunAtLoad` + `KeepAlive`),
the same launchd topology the pipeline cron already uses (ADR-0080). It starts at login,
respawns on crash, needs no tmux and no `sudo`. Verify:

```bash
launchctl print gui/$(id -u)/com.ibl5.bug-bot | grep -E 'state|pid|last exit'
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:50001/
```

Logs are `bug-bot-stdout.log` / `bug-bot-stderr.log` beside the cron's, under
`~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/bug-pipeline/logs/`.
`bin/bug-pipeline-cron-setup --uninstall-bot` removes it; `--print-bot` dumps the
generated plist without touching anything.

`bin/bug-pipeline-check` covers the bot as well as the cron, and separates the two
ways it can be down: `bot:unloaded` means the LaunchAgent is not registered (fix
with `--install-bot` above), `bot:unreachable` means it is registered but not
answering on `http://127.0.0.1:50001/` (restart it and read the log). Either exits 1.

**The bot is never started by a prod deploy.** It is a LaunchAgent in your user's gui
domain on this Mac — prod has no such job and no bug-bot token. (It ran under its own PM2
ecosystem file until 2026-09-14; that file is deleted, because leaving it would let
`pm2 start` open a *second* gateway connection on the one token. PM2 is still used by
`bin/bug-pipeline-test-env` for the **test** bot on port 50002 — ADR-0111 — which is a
separate app and unaffected.)

**Runtime dependency:** the bot targets `http://main.localhost/ibl5`, so the always-up
main stack must be running (`bin/dev-up`, which prod-syncs the DB by default). It is not
tied to any worktree stack (those are torn down on merge).
