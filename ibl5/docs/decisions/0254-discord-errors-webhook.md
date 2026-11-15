---
description: Failure notices post to a private #errors channel webhook, other owner notices stay on #dev, and every owner @mention is removed, the merge-digest ping included.
last_verified: 2026-11-15
---

# ADR-0254: Route Failure Notices to an #errors Webhook and Remove Every Owner @Mention

**Status:** Accepted
**Date:** 2026-11-15
**Deciders:** a-jay85
**Supersedes:** the opt-in owner ping in ADR-0146 and the merge-digest ping in ADR-0208

## Context

ADR-0146 put owner-bound notices on a #dev webhook and let a failure opt into an owner @mention (`--ping` on the host, `ping: 'true'` in the notify-discord action). ADR-0208 added a second mention: the #merged digest pinged the owner when a post-merge step needed them. The mention was the only thing that told a failure apart from a routine notice. It also cost upkeep: an owner ID in two scripts, `allowed_mentions.users`, a ping set pinned in tests, and a fail-closed `ping:` expression in `merge-digest-notify.yml`. A channel the owner is notified for on every message gives the same signal with no mention.

## Decision

1. Failure notices post to a private #errors channel. Workflows pass `secrets.DISCORD_ERRORS_WEBHOOK_URL`. Host scripts pass `bin/discord-dm --error`, or call `dm_error` from `bin/lib/dm.sh`.
2. Routine and needs-you notices stay on #dev (`secrets.DISCORD_DEV_WEBHOOK_URL`, plain `bin/discord-dm`, `dm_send`). #merged and #reviews keep their own secrets.
3. Every owner @mention is removed. That covers the action's `ping` input, the owner ID in `send.sh`, `--ping`, `dm_ping`, `allowed_mentions.users`, the merge-digest ping, and the morning digest's ping for a skipped run. Every webhook payload keeps `allowed_mentions: {"parse": []}` and `flags: 4`. `bin/digest-dm-build` still puts a zero-width space after each `@` in PR text.
4. `bin/discord-dm --error` posts to the URL in `~/.iblbot-errors-webhook-url` (test seam `DISCORD_DM_ERRORS_WEBHOOK_FILE`). It uses the same eligibility rules as the #dev route: the recipient is the owner, and the call is not `--raw` or `--route`. A missing or malformed errors file falls back to the #dev webhook file, then to the IBLbot DM. `--error` with `--raw` exits 2, and so does `--error=1`. The removed `--ping` flag exits 2 as an unknown flag.
5. The merge digest keeps its top block, which names a manual row, a failed auto row, a non-numeric count, or a needed job whose result is not `success`. `pmr_ping_decision` is renamed `pmr_needs_you_reasons` and sets only `PMR_NEEDS_YOU_REASONS`. `bin/digest-dm-build --ping-out FILE` also switched after-merge mode on, so a new valueless `--after-merge` flag takes over that job. Without `--after-merge`, the after-merge flags still change nothing. `--ping-out` now exits 2 as an unknown flag.
6. `bin/test-discord-dm` enforces the rule. Its call-site table pins the webhook token of each of the 16 notify-discord sites. A `ping:` line in a site shows as `stray=ping`, which no row carries. Its `owner_mention_scan` reads every tracked `.github/` and `bin/` file and fails on an owner mention, `users: [`, `--ping`, `dm_ping`, `--ping-out`, or a `ping:` key. The carve-outs are `bin/sim-recap-*` and `bin/lib/sim-recap-exemplar.txt` (sim recaps mention league GMs by design), `bin/test-*` (hostile-text fixtures that prove neutralisation), and `bin/fixtures/*` (frozen diff and corpus fixtures).

### Routing table

| Site | Channel |
|------|---------|
| `db-backup.yml` (4 failure steps) | #errors |
| `smoke-prod.yml` (2 failure steps) | #errors |
| `docfix-health.yml` (2 alert steps) | #errors |
| `main.yml` (deploy failure) | #errors |
| `mutation.yml` (failure or cancelled) | #errors |
| `promote-to-production.yml` result notice | #errors on failure, #dev otherwise |
| `promote-to-production.yml` paused notice | #dev |
| `docs-refreshed-notify.yml` | #dev |
| `merge-digest-notify.yml` | #merged |
| `log-review.yml`, `flake-report.yml` | #reviews |
| `bin/db-backups-pull`, `bin/launchd-health-check`, `bin/main-checkout-guard`, `bin/post-plan-fail-dm` | #errors (`--error` or `dm_error`) |
| `bin/pr-cycle`, `bin/pr-cycle-tick` failure notices | #errors (`dm_error`) |
| `bin/pr-cycle-tick` stuck-PR and needs-you notices | #dev (`dm_send`) |
| `bin/automouse/morning-digest` skipped-run line | #dev, no mention |

The site PHP error logger (`discord_webhook_url` in the prod logging config under `ibl5/config/`) also points at #errors. That file is untracked, and the owner sets it by hand on the server. `ibl5/docs/OPERATIONS_RUNBOOK.md` lists the step.

## Alternatives Considered

- Keep the ping and add the channel. Rejected because the channel already carries the signal, and the ping is extra surface to maintain.
- Use one webhook and mark errors with a prefix. Rejected because the owner could not mute routine #dev traffic without also muting failures.
- Keep `--ping-out` as the after-merge switch and ignore its file. Rejected because a flag that takes a path nobody reads misleads the next reader.

## Consequences

- Positive: one routing rule replaces the owner ID, the ping set and the fail-closed ping expression.
- Positive: `bin/test-discord-dm` G4 fails any new owner mention anywhere in `.github/` or `bin/`.
- Negative: the owner must keep #errors notifications on for every message, since nothing mentions them any more.
- Negative: a manual post-merge step is named on #merged but no longer notifies the owner, so the owner reads #merged after each merge.
- Neutral: a host with no errors file sends failures to #dev, and a host with neither file sends DMs, so a fresh machine still notifies.

## References

- `ibl5/docs/decisions/0146-discord-dev-webhook-notify.md` (the #dev route this extends)
- `ibl5/docs/decisions/0208-merged-digest-pings-owner-for-post-merge-steps.md` (the digest ping this removes)
- `ibl5/docs/decisions/0097-single-retrying-host-notification-transport.md` (the transport)
- `bin/discord-dm` (`--error` route and fallback)
- `bin/lib/dm.sh` (`dm_send`, `dm_error`)
- `bin/digest-dm-build` (`--after-merge`)
- `bin/lib/post-merge-rows.sh` (`pmr_needs_you_reasons`)
- `.github/actions/notify-discord/send.sh` (the action's webhook send)
- `bin/test-discord-dm` (routing, payload, call-site and mention-scan assertions)
