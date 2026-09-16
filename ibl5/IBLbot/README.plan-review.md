# Plan-Review Button Bot — Prod Runbook

The plan-review feature sends a Discord DM to the owner with **Queue** / **Discard** buttons
when `POST /discordPlanReviewDM` is called by the plan drain script. Decisions are written to
`data/decisions.jsonl` (a sibling of `dist/`, created on first write) and drained by
`POST /planDecisions/ack`.

## One-time provisioning (after first deploy)

The deploy copies only `dist/` to the VPS — it never writes `.env`. Until
`PLAN_REVIEW_OWNER_DISCORD_ID` is set, the bot answers **503** on every plan-review request
and ignores every button press. That is the designed safe-off state: the main bot keeps
running normally.

```bash
echo 'PLAN_REVIEW_OWNER_DISCORD_ID=<your-discord-snowflake>' \
  >> /home/iblhoops/public_html/ibl5/IBLbot/.env
```

Replace `<your-discord-snowflake>` with your Discord user ID (right-click your name →
Copy User ID in Developer Mode).

## Restart with the new env var

pm2 caches the environment from the process's first start. A plain `pm2 restart iblbot`
will **not** pick up the new variable — the bot will keep 503-ing from a correctly-provisioned
box. The required flag is `--update-env`:

```bash
pm2 restart iblbot --update-env
```

This is the same flag `bin/iblbot-healthcheck:13-16` already passes on its unattended restart
path, so a watchdog-triggered restart also picks up the variable.

## Decision sink

Decisions are appended to `data/decisions.jsonl` in the IBLbot working directory
(`/home/iblhoops/public_html/ibl5/IBLbot/data/decisions.jsonl`). The directory is created
automatically with the correct owner on the first button press — do **not** pre-create it as
root; a root-owned `data/` causes `EACCES` on every press with no operator watching.

The sink is a sibling of `dist/` so that a `dist/`-only deploy never clobbers it and a
`git clean` of the source tree leaves it intact.

## Reading pending decisions by hand

```bash
curl -s http://127.0.0.1:50000/planDecisions
```

Returns the current pending JSONL as plain text. Useful when the drain script hasn't run yet
or you want to confirm a button press landed.

## Recovery

**An unacked decision is never deleted, so a failed drain loses nothing — re-run it.**

If the drain (`POST /planDecisions/ack`) fails mid-flight (network error, process restart,
etc.), the unacked decisions remain in `data/decisions.jsonl` exactly as they were. Re-run
the drain and it will process them. There is no deduplication concern: each decision record
carries its plan slug and the `appendDecision` writer guards against duplicate slugs
(`DecisionExistsError`).
