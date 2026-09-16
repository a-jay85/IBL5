# Plan-Review Button Bot — Prod Runbook

The plan-review feature sends a Discord DM to the owner with **Queue** / **Discard** buttons
when `POST /discordPlanReviewDM` is called by the plan drain script. Decisions are written to
`data/plan-decisions.jsonl` (a sibling of `dist/`, created on first write). Read pending
decisions with `GET /planDecisions`; drain with `POST /planDecisions/ack`.

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

```bash
pm2 restart iblbot
```

A plain restart is enough for the provisioning step above. `src/config.ts:1-3` calls
`dotenv.config()` at import, and that re-reads the file from disk on **every** process start —
including a plain restart. `ecosystem.config.cjs` declares no `env:` block, so pm2 never
injects `PLAN_REVIEW_OWNER_DISCORD_ID` itself and the value dotenv reads is the one that lands.

`--update-env` matters for a different case: `dotenv.config()` does **not** overwrite a key
already present in the process environment. If pm2 has a value cached for a key from an earlier
start, editing that key and plainly restarting leaves the stale value in place.

| What changed | What to run |
|---|---|
| **Adding** a key pm2 has never seen (the provisioning step above) | `pm2 restart iblbot` |
| **Changing** a key pm2 already has cached | `pm2 restart iblbot --update-env` |

`--update-env` is never harmful, so pass it if unsure. `bin/iblbot-healthcheck:13-16` passes it
on its unattended path, so a watchdog-triggered restart also picks the variable up.

## Decision sink

Decisions are appended to `data/plan-decisions.jsonl` in the IBLbot working directory
(`/home/iblhoops/public_html/ibl5/IBLbot/data/plan-decisions.jsonl`). The directory is created
automatically with the correct owner on the first button press — do **not** pre-create it as
root; a root-owned `data/` causes `EACCES` on every press with no operator watching.

The sink is a sibling of `dist/` so that a `dist/`-only deploy never clobbers it and a
`git clean` of the source tree leaves it intact.

## Reading pending decisions by hand

```bash
curl -s http://127.0.0.1:50000/planDecisions
```

Returns pending decisions as JSON `{"decisions":[…]}` — each object has `id`, `slug`, `action`, `actor`, `ts`. Useful when the drain script hasn't run yet
or you want to confirm a button press landed.

## Recovery

**An unacked decision is never deleted, so a failed drain loses nothing — re-run it.**

If the drain (`POST /planDecisions/ack`) fails mid-flight (network error, process restart,
etc.), the unacked decisions remain in `data/plan-decisions.jsonl` exactly as they were. Re-run
the drain and it will process them. There is no deduplication concern: each decision record
carries its plan slug and the `appendDecision` writer guards against duplicate slugs
(`DecisionExistsError`).
