---
description: The bug-pipeline isolated test environment — three orthogonal barriers (separate Discord app, uniquely-named scratch DB, stubbed GH_BIN) and the guards that make isolation a runtime-enforced property rather than a prose claim.
last_verified: 2026-08-21
---

# ADR-0111: The bug-pipeline test-isolation boundary

**Status:** Accepted
**Date:** 2026-08-21

## Context

The pipeline autonomously reacts, threads, and messages on Discord, writes MariaDB rows, and shells out to `gh`. Two prior silent-death incidents make the case for *structural* over *configural* isolation: a `claude` rc=127 that ran unnoticed for 8 days, and a MariaDB outage that dispatched 84 hunters against report id `""` on 2026-08-04 (`bin/bug-pipeline-tick:88-95`). Both were configuration-shaped failures that presented as normal operation — exactly the failure mode a test environment sharing any production target would reproduce at higher frequency.

The isolation challenge is non-trivial because `ibl5/IBLbot/src/bug-bot/config.ts:8` hardcodes `dotenv.config({ path: '.env.bugbot' })` with no `override`, and both PM2 apps share a `cwd`. A variable the test process fails to set silently resolves to the production token.

## Decision

Structural isolation via three orthogonal barriers, all asserted by `bin/bug-pipeline-e2e` **before the first write**:

1. **Separate Discord application.** The test bot process holds a different token, injected by `ibl5/IBLbot/ecosystem.bugbot-test.config.cjs` which `dotenv.parse`s the untracked `ibl5/IBLbot/.env.bugbot.test` (example) and materializes every variable the bot reads into the PM2 `env:` block, where process-env precedence guarantees it wins. The config refuses to boot when the test token or channel equals the production values. **What this barrier does not do:** it does not stop a caller from addressing `127.0.0.1:50001` and reaching the production bot. Port separation is a distinct barrier, and the fail-closed guard in `bin/bug-pipeline-e2e` is what closes it (`BOT_BASE_URL` must equal the 50002 URL; any `50001` substring is refused).

2. **Uniquely-named scratch database.** `ibl5_bug_pipeline_test`, created and dropped by `bin/bug-pipeline-test-env`. `bin/bug-pipeline-tick:31` defaults `DB_NAME` to `iblhoops_ibl5` when unset, so the barrier is not "the tick knows better" — it is that the guard refuses to start unless `DB_NAME` is exactly the scratch name, and the teardown refuses to `DROP` anything else. The tick-probe run in `bin/bug-pipeline-test-env` (Phase 4) demonstrates the production default is real and that the isolation assertion has teeth.

3. **Stubbed `GH_BIN`.** `bin/bug-pipeline-tick:36` funnels every GitHub call through the single `GH_BIN` seam, so pointing it at a shell stub removes the network path entirely. The fake repo slugs (`ibl-test/*`) are the second layer, and the guard refuses any `a-jay85/*` value. The single-seam property is the invariant to preserve: if a future change adds a direct `gh` call bypassing `GH_BIN`, this barrier silently degrades.

**The guard is the mechanism, the ADR is the record.** All three barriers are asserted by `bin/bug-pipeline-e2e` before the first write (Step 5.2), and a refused run exits non-zero having changed nothing.

## Setup (one-time, per developer machine)

1. Create a **second** Discord application at the Discord developer portal.
2. Add a bot user and copy its token.
3. Invite the bot **only** to a personal testing guild with: View Channel, Send Messages, Send Messages in Threads, Create Public Threads, Read Message History, and **Add Reactions**.
4. Copy `ibl5/IBLbot/.env.bugbot.test.example` to `ibl5/IBLbot/.env.bugbot.test` (example) and fill in all `REPLACE_WITH_` values.
5. Run `bin/bug-pipeline-test-env` to bring up the environment.

**A missing Add Reactions permission fails silently** — `handlers.ts` swallows a failed reaction so backfill cannot abort, meaning the absence of the bit presents as the pipeline working but never acknowledging reports. This is why `ibl5/IBLbot/src/bug-bot/server.e2e.test.ts` exists: it is the only mechanical proof that the role actually holds the bit.

## Alternatives Considered

- **`--test-mode` branch inside `bin/bug-pipeline-tick`** — Rejected: puts test-only control flow on the production hot path, where a bug in the branch is a production incident. Makes isolation depend on a code path rather than the absence of one.
- **Shared `cwd` with a differently-populated `.env.bugbot`** — Rejected: breaks the relative `script:` path in the ecosystem config and hides which credentials are in play.
- **`pm2 restart --update-env`** for credential rotation — Rejected: re-reads the process environment, not the ecosystem file, so a rotated token in the untracked secrets file is silently ignored. Delete-then-start always re-evaluates the config.
- **HTTP enqueue path for E2E** — Rejected: requires `ibl_api_keys` + `ibl_team_info` and a PHP-FPM pinned to production. The direct `BugReportRepository` call covers the same pipeline logic at a fraction of the provisioning cost.

## Consequences

- Positive: the test environment runs concurrently with the live pipeline (separate port, DB, and Discord app). Teardown is idempotent.
- Positive: isolation is a runtime-enforced property (`bin/bug-pipeline-e2e` refuses before any write), not a documented convention.
- Negative: a second Discord application to keep in sync with the production bot's permission set.
- Negative: credentials live only on the operator's machine, so Phase 8's Discord smoke rows cannot run in CI — they are local, human-invoked only.
- Negative: `ibl5/IBLbot/.env.bugbot.test` (example) absence blocks bring-up by design.

## References

- `bin/bug-pipeline-test-env` — provision and tear down: scratch DB, migrations 153+158 via `bin/db-migrate`, stubs, passthrough probes, env block, PM2 lifecycle with liveness probe, guarded idempotent teardown.
- `bin/bug-pipeline-e2e` — default-SKIP (`BUG_PIPELINE_E2E=1`), production-contact guard before first write, stub-wiring verification including `claude` envelope check, bug-path and feature-path drives.
- `ibl5/IBLbot/ecosystem.bugbot-test.config.cjs` — PM2 app `ibl-bug-bot-test`; parses the untracked secrets file, fails closed on missing/empty/production-matching credential, masks every var `config.ts` reads in the `env:` block.
- `ibl5/IBLbot/src/bug-bot/server.e2e.test.ts` — opt-in Discord smoke (`BUG_BOT_E2E=1`); proves the role holds Add Reactions.
- `bin/bug-pipeline-tick` — lines 28-95 are the seam list the env block must match name-for-name; lines 31/37/38/40/84 are the production defaults Phase 4 demonstrates are real.
- `ibl5/IBLbot/src/bug-bot/config.ts` — line 8 fixes the dotenv filename (why PM2 env injection is the chosen mechanism); lines 18-42 are the authoritative variable-name list.
