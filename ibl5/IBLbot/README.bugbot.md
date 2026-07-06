# Bug-Report Bot (Mac-local)

A **second** Discord bot process — a distinct Discord app/token from the prod
IBLbot — that turns a dedicated bug-report channel into a work queue. It runs on
the Mac only, never on prod. It writes nothing to MySQL directly: every DB effect
goes through PR #3's `/api/bug-pipeline/*` PHP endpoints.

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
   - `BUG_PIPELINE_API_BASE_URL` — the Mac-local PHP/docker stack base (NOT prod)
   - `API_KEY` — a valid `ibl_api_keys` key; **must be a high/unlimited rate-limit
     tier** so startup backfill isn't 429-throttled into silently dropping reports
   - `BUG_CHANNEL_ID` — the dedicated bug-report channel snowflake

## Start

```bash
cd ibl5/IBLbot
npm run build
pm2 start ecosystem.bugbot.config.cjs   # run inside tmux so it survives terminal close
```

The bug-bot uses its OWN PM2 ecosystem file (`ecosystem.bugbot.config.cjs`) — it is
deliberately NOT added to the prod `ecosystem.config.cjs`, so a prod deploy never
starts it.
