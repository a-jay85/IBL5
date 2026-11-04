---
description: The Discord bug pipeline parks every new report as dormant and wakes it only when the league operator @mentions the bot; the operator's text rides in a trusted prompt block above the untrusted GM text.
last_verified: 2026-11-03
---

# ADR-0213: Bug pipeline wakes only on an operator mention

**Status:** Accepted
**Date:** 2026-10-22

## Context

Since ADR-0080 every top-level post in the bug channel started a classify run and, for bug-class rows, a hunter worktree. Spend tracked GM post volume, and a GM's phrasing was the only steering the model received. The classify, gather and hunter prompts wrap all GM text in an `<untrusted>` block (ADR-0081), so the league operator had no channel for adding trusted direction to a specific report. The approval reaction (`ReactionController`) already authorizes the operator by Discord author ID against `bug_pipeline_approver_discord_id`, and the bot already recognises its own user ID. Together these give both halves of a wake trigger without new identity plumbing.

## Decision

1. Enqueue writes `dormant`. The enumerator, `claim-next` and the tick's `main()` treat `dormant` as not actionable, so a GM post alone spawns zero `claude` processes.
2. The bot routes a thread message to `wake` only when its author ID equals `BUG_OPERATOR_DISCORD_ID` and it mentions the bot. The mention is the trigger and the author ID is the authorization. PHP re-checks the author against `Discord::getBugPipelineApproverDiscordId()` and fails closed on an empty value.
3. `POST /api/v1/bug-pipeline/wake` moves `dormant` to `queued`, restarts `needs_human`, `parked_idle` and `dropped` rows, and only appends context on every other status.
4. The operator's message text is stored as an append-only log (`ibl_bug_report_operator_context`, one row per mention, 4000-char cap). It renders in a dedicated `<operator>` block outside `<untrusted>` and still passes through `neutralize_untrusted`. The operator's mention messages are omitted from the transcript, so the same text never appears in both trust tiers.
5. A one-time migration parks rows that were queued, unclassified and threadless.

## Alternatives Considered

- **Delimited TEXT column.** One column on `ibl_bug_reports` holds every operator note. Rejected because idempotent appends and per-entry timestamps become a parsing problem in PHP and bash, and the facade would cross the hot-file threshold.
- **Separate wake config key.** Rejected because one snowflake already authorizes approvals; two keys could drift apart for a single-operator league.
- **Lookup endpoint.** A PHP route that reports whether a source exists. Rejected in favour of the bot retrying once after `handleEnqueue` on a 404, which keeps the API at one new route.

## Consequences

- Positive: an idle or chatty channel costs zero `claude` spawns until the operator picks a report up.
- Positive: the operator can steer classify, gather and the hunter with trusted text per report.
- Negative: a GM sees no pickup react until the operator wakes the report. The operator's wake message gets the bot's react.
- Negative: the operator block is trusted by construction, so the operator account is now a privileged input. Compromise of that account equals prompt authorship, the same exposure the approval reaction already carried.
- Reversal: drop the route and set enqueue back to `queued`. One `UPDATE` re-queues the `dormant` rows.

## Lineage

Supersedes nothing. Extends ADR-0080 (topology unchanged) and ADR-0081 (hunter trust split).

## References

- `ibl5/migrations/198_bug_pipeline_dormant_and_operator_context.sql`
- `ibl5/classes/Api/Controller/WakeController.php`
- `ibl5/classes/BugPipeline/BugReportOperatorContextRepository.php`
- `ibl5/IBLbot/src/bug-bot/handlers.ts`
- `bin/bug-pipeline-tick`
- `ibl5/docs/decisions/0080-mac-local-discord-bug-pipeline-cron-topology.md`
- `ibl5/docs/decisions/0081-hunter-trust-split-starved-env-sandbox.md`

## Addendum 2026-11-02: the wake route checks the key tier before the author

Decision 2 names the author ID as the authorization. The wake controller enforces one more check, and it runs first: the calling API key must have a `rate_limit_tier` other than `standard`. A standard-tier key gets 403 `forbidden` with "Only the pipeline bot can wake a report." Only a service key reaches the author check, whose rejection is 403 `forbidden` with "Only the pipeline operator can wake a report." The two rejections differ only in `error.message`. `bin/bug-pipeline-e2e` case 8.2 asserts the author message, so a mis-provisioned standard-tier test key fails the E2E run instead of passing it. Code: `ibl5/classes/Api/Controller/WakeController.php`. Tests: `ibl5/tests/Api/Controller/WakeControllerTest.php`.

## Addendum — wake trust boundary (2026-11-03) <!-- slop-ok -->

Decision item 2 says PHP re-checks the author. That check compares `author_id` from the request body with the configured operator snowflake. The bot fills `author_id` from the Discord message, and the PHP route cannot confirm where the value came from. Discord user ids are public, so the author check alone would let any caller name the operator.

Since [#3472](https://github.com/a-jay85/IBL5/pull/3472) the route also requires a service key. `ServiceKeyGuard::callerIsServiceKey()` rejects a missing key, an unknown key, and any key whose `rate_limit_tier` is `standard`. Both checks run before any repository read or write.

The trust boundary is the set of API keys at the `elevated` or `unlimited` tier. The self-service API Keys page (`ApiKeysRepository::createKey`) always creates `standard` keys. Keys at the `elevated` or `unlimited` tier exist only when the operator provisions them in the database, as `ibl5/IBLbot/README.bugbot.md` requires for the bot key. A holder of such a key can send the operator's id as `author_id` and wake a report with trusted operator text. Compromise of an `elevated` or `unlimited` key therefore equals compromise of the operator account under the second Negative consequence above. Both grant operator-prompt authorship.

`ReactionController` (the approval reaction) compares the body `reactor_id` with the same snowflake. Until [#3707](https://github.com/a-jay85/IBL5/pull/3707) it had no tier check, so a caller holding any valid API key could approve as the operator. It now runs the same service-key check first and returns 403 `forbidden` with "Only the pipeline bot can report a reaction." The bot sends one `API_KEY` to both routes, so the bot key already meets the tier rule. `ReactionControllerTest` pins the reaction gate.

Considered and rejected: pinning the wake route to one dedicated bot key, by key hash or a new config value. No self-service path produces a non-`standard` key, so the pin would add a column or config key without closing a reachable path. Revisit if a self-service flow ever hands out `elevated` keys.

`WakeControllerTest::testElevatedTierKeyWithOperatorAuthorIdIsAccepted` and `WakeControllerTest::testElevatedTierKeyWithNonOperatorAuthorIdRejected403AndNoRepositoryCall` pin this boundary, so a change to the tier rule fails CI.
