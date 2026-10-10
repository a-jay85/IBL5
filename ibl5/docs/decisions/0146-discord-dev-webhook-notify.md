---
description: Owner-bound notices post to a private #dev channel webhook, with an opt-in owner @mention for failures that need action.
last_verified: 2026-10-09
---

# ADR-0146: Route Owner-Bound Notices to a #dev Channel Webhook with an Opt-In Ping

**Status:** Accepted
**Date:** 2026-09-29

## Context

Owner-bound notices (CI failures, the merge digest, post-plan failures, launchd
health, morning digest, verdicts) arrive as IBLbot DMs. Every send tunnels over
ssh to the prod box and POSTs the bot's `/discordDM` route (ADR-0097). DMs mix
with personal traffic, give no shared history, and every notice alerts the same
way, so a failure that needs action looks like a routine success notice.

## Decision

Owner-bound notices post to a private #dev channel webhook. `bin/discord-dm`
routes to the webhook when the recipient is the owner, the call is not
`--raw`/`--route`, and `~/.iblbot-dev-webhook-url` holds a well-formed https
URL. Otherwise it keeps the IBLbot DM path: other recipients (bug-pipeline
approvers), button-bearing plan-review messages, and a host with no webhook file.
The notify-discord composite action posts to the webhook passed as
`secrets.DISCORD_DEV_WEBHOOK_URL`.

Every webhook payload sets `allowed_mentions` to `{"parse": []}` and
`flags: 4`, so text inside a PR title never pings anyone. A caller opts into an
owner @mention with `--ping` (host) or `ping: true` (action). Only failures that
need prompt action ping: db-backup failures, smoke-prod failure,
promote-to-production failure, launchd-health-check problems, post-plan
failures (immediate and ADR-0139's deferred send, whose 15-minute hold is
unchanged), and the merge digest (merge-digest-notify.yml and bin/pr-cycle's
end-of-run summary).

Any 2xx from the webhook counts as delivered. HTTP 429 retries after
`retry_after`. Other 4xx fail fast. The retry, spool and macOS fallback from
ADR-0097 still apply. The webhook URL never enters argv, logs, the spool or the
repo: curl reads it from a config on stdin.

## Alternatives Considered

- **A new `bin/discord-webhook` (example) script beside `bin/discord-dm`.** Rejected because every caller would need a send-logic rewrite, and the routing rule belongs in the one transport.
- **Keep DMs and add a mention-only channel.** Rejected because the DM stream would still mix routine and urgent notices.
- **Read the webhook URL from an env var.** Rejected because launchd jobs and generated runner scripts do not inherit the shell environment.

## Consequences

- A `bin/test-discord-dm` assertion pins the 15 action call sites and the ping set, and fails when a new site or a new `--ping` caller appears without an update to its expected table.
- CI notify jobs no longer need the Setup SSH step unless another step in the job uses ssh.
- A host with no webhook file keeps sending DMs, so a fresh machine still notifies.

## Addendum: merge notices move to #merged (2026-10-09)

The Decision above sends every action call site to `secrets.DISCORD_DEV_WEBHOOK_URL`. From 2026-10-09, merge-digest-notify.yml posts to the #merged channel through `secrets.DISCORD_MERGED_WEBHOOK_URL` instead, so merge notices stay out of #dev. Its owner ping is unchanged. The end-of-run summary from `bin/pr-cycle` still goes to #dev. The call-site table in `bin/test-discord-dm` now records which webhook each site passes (`webhook=dev` or `webhook=merged`). It holds 16 sites, up from the 15 in Consequences.

## References

- `ibl5/docs/decisions/0097-single-retrying-host-notification-transport.md` (the transport this extends)
- `ibl5/docs/decisions/0139-postplan-fail-dm-quiet.md` (the deferred post-plan send)
- `bin/discord-dm` (webhook transport, seams, `--ping`)
- `.github/actions/notify-discord/send.sh` (the action's webhook send)
- `bin/test-discord-dm` (routing, payload, call-site and ping-set assertions)
