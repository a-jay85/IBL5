---
description: Host-side Discord notifications go through one retrying transport (bin/discord-dm) that never fails silently; callers never re-implement the ssh+curl recipe.
last_verified: 2026-08-04
---

# ADR-0097: A single retrying host-side notification transport

**Status:** Accepted
**Date:** 2026-08-04

## Context

IBLbot binds `127.0.0.1:50000` on prod, so any host-side sender has to tunnel over ssh and POST to localhost. That recipe was copy-pasted per caller, one-shot, with `curl -sf` swallowing the HTTP status. On 2026-08-04 a watcher detected an automouse plan's finish in 52 seconds, built the correct message with the correct PR link, POSTed it once into a transient 500 during a `bin/iblbot-healthcheck` pm2 restart window, logged nothing legible, and exited 0. The notification was simply lost, and the only symptom was silence — the caller had no way to tell "delivered" from "dropped".

## Decision

Every host-side Discord notification goes through **`bin/discord-dm`**, the one place the ssh+curl recipe lives. It retries across a window longer than a pm2 restart (6 attempts, ~5min15s), logs `ssh_rc`/`curl_rc`/`http` on every attempt, treats 4xx as permanent and fails fast, and on total failure spools the message to a durable log, raises a macOS notification, and exits non-zero — so a caller can always distinguish delivery from loss. Callers pass the message on stdin and never construct the payload themselves; the recipient snowflake stays a JSON string end-to-end (`jq --arg`) and defaults from `~/.iblbot-dm-id`, which lives outside the repo so no snowflake is committed. `bin/watch-automouse-plan` is the first consumer.

## Alternatives Considered

- **Fix the retry in each caller** — add a loop where the recipe is pasted. Rejected because: the bug is the duplication; the next caller re-inherits it.
- **Expose the bot on a public port and drop the ssh leg** — Rejected because: it turns a localhost-only service into an internet-facing one to save a hop.
- **Let a failed send stay advisory (log and exit 0)** — Rejected because: that is exactly the failure mode being fixed; a notification that can vanish silently is worse than none, since it is trusted.
- **Route through `.github/actions/notify-discord`** — Rejected because: that action is a GitHub Actions runner surface, not runnable from the host; `bin/discord-dm` is its host-side sibling.

## Consequences

- Positive: a lost notification is now impossible to miss — worst case it is spooled, flagged on screen, and signalled by a non-zero exit.
- Positive: one place to change if the bot's port, host, or payload shape moves.
- Negative: a **bad snowflake** costs the full ~5-minute retry window, because IBLbot answers an invalid recipient with **500**, not 4xx — indistinguishable from the transient failure the retries exist for. Accepted deliberately: 500 is exactly the code the originally-lost message returned, so refusing to retry it would reintroduce the bug this ADR closes.
- Negative: delivery can now take minutes instead of failing instantly, so a caller that blocks on the send blocks longer.

## References

- `bin/discord-dm` — the transport; header documents the retry window and the 500 trade-off.
- `bin/watch-automouse-plan` — first consumer; routes every notification through it.
- `bin/iblbot-healthcheck` — the pm2 cron watchdog whose restart windows the retry budget is sized against.
- `.github/actions/notify-discord` — the CI-side sibling.
- `bin/README.md` — registers both scripts.
