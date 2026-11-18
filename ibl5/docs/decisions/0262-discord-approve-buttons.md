---
description: Why a held PR can be approved from a Discord button. Server-side single-use token, Mac-side pull over the ssh tunnel, at-most-once ledger, and bin/owner-approve as the only arming path.
last_verified: 2026-11-17
---

# ADR-0262: Discord approve buttons

**Status:** Accepted
**Date:** 2026-11-17
**Deciders:** Repo owner

## Context

A held PR sends the owner a Discord DM, and approving it means going back to a terminal to run `bin/owner-approve`. The owner wants one tap on the phone. The bot runs on prod under pm2 and can reach Discord. It cannot reach the Mac, which holds `gh`, the post-plan verdicts and the `bin/owner-approve` script. Prod also must never hold a credential that can arm a merge.

## Decision

The bot sends the owner a DM with an Approve and a Fix first button next to the held-PR notice. A press is recorded in an append-only JSONL store on prod, keyed by a 32-hex nonce. The nonce binds the PR number, the head SHA and a 24h expiry, and a new button for a PR supersedes the older one. Only the owner id (`PLAN_REVIEW_OWNER_DISCORD_ID`) can press. An empty owner id turns all three routes off with a 503.

The Mac pulls. A launchd job runs `bin/approve-click-tick --tick` every 180s, lists pending presses over the same ssh tunnel that `bin/plan-review-drain` uses, and claims each one at most once. It re-checks the actor, a 1h claim window and the live PR head SHA before it starts a detached `bin/owner-approve` run. A moved head SHA runs nothing and sends a fresh button.

The ledger order is the at-most-once guarantee: a local `claimed` marker made with O_EXCL, then the bot claim, then the checks, then a `started` marker, then `bin/owner-approve`. A crash anywhere leaves the press consumed and not run, and `reconcile_local` reports it by DM. `bin/owner-approve` stays the only arming path, so the READY verdict and the post-plan wait keep their one implementation. Fix first passes its condition to `bin/owner-approve` as one array element, capped at 300 characters with no control characters, and the owner then runs `bin/owner-approve N --finish` by hand after the fix.

`bin/approve-click-cron-setup` installs the launchd job. The job sets `AbandonProcessGroup` so the detached runner outlives the tick. The enforcement is the vitest suite in `ibl5/IBLbot/src` and the `approve-click` cases in `bin/test-plan-review-seam`.

## Alternatives Considered

- **Prod calls the Mac.** Rejected because prod has no route to the Mac and opening one widens the attack surface.
- **A GitHub Actions dispatch from the bot.** Rejected because it needs a token on prod that can arm a merge, and it skips the post-plan wait in `bin/owner-approve`.
- **A bare PR number in the button id.** Rejected because a stale or replayed button would approve whatever head the PR has now. The token binds the SHA.
- **A webhook push to the Mac.** Rejected because the Mac sleeps and has no public address.

## Consequences

- Positive: one tap approves a held PR, with the same verdict checks as the terminal path.
- Positive: a replayed, stale or foreign press runs nothing.
- Negative: a press waits up to about 3 minutes for the next tick.
- Negative: a crash between claim and run needs a manual `bin/owner-approve`, which the DM says.

## References

- `ibl5/IBLbot/src/server/approve-click-store.ts`
- `ibl5/IBLbot/src/server/approve-dm.ts`
- `ibl5/IBLbot/src/interactions/approve-buttons.ts`
- `bin/approve-click-tick`
- `bin/approve-click-cron-setup`
- `bin/owner-approve`
- `ibl5/IBLbot/README.approve-buttons.md`
