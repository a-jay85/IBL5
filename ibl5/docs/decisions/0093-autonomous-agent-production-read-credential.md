---
description: An unattended agent is given a production-reaching credential safely by starving it to one SELECT-only MySQL user over an SSH tunnel while every privileged write happens prod-side behind a CLI guard.
last_verified: 2026-07-23
---

# ADR-0093: An autonomous agent holding a production read-only credential

**Status:** Accepted
**Date:** 2026-07-23

## Context

An autonomous `claude` agent, spawned unattended by a launchd poll on a personal Mac, must generate sim recaps from live league data — which means it must reach the **production** database. That is a materially new trust case: the Mac-local pipeline of ADR-0080 and the starved-environment sandbox of ADR-0081 both let their agent reach only a *local Docker* database, where a compromised or prompt-injected agent can do nothing that matters. Here the same topology points at production, so the question the pipeline could previously ignore — what, exactly, can this agent do with the credential it holds? — has to be answered structurally rather than by trust. The measurement of that answer (a live spike against the production grant surface) is recorded below.

## Decision

The agent is made **incapable by construction**, not trusted to behave. Four structural properties, each enforced by a named mechanism rather than a convention:

1. **The agent is credential-starved.** It receives exactly one credential — a `SELECT`-only MySQL user reached over an SSH tunnel, passed only through `MYSQL_PWD` in the agent's environment — and `--allowedTools` is limited to `Bash(mysql:*)` and `Write`. It has no Discord, no `ssh`, and no write path of any kind. Reading game data is the *whole* of what the credential can do.
2. **Every privileged action happens prod-side**, performed by CLI-guarded scripts the agent cannot invoke. `ibl5/scripts/simRecapQueue.php` drives the queue (claiming a row is an `UPDATE`, which the read-only user cannot perform at all) and `ibl5/scripts/storeSimRecap.php` writes the recap; each carries a `PHP_SAPI !== 'cli'` guard as its first executable statement and an `ibl5/scripts/.htaccess` file-scoped deny behind it. The Mac-side tick (`bin/sim-recap-tick`) reaches them only over `ssh`, composing no SQL of its own.
3. **The Mac holds exactly one credential**, injected through launchd `EnvironmentVariables` by `bin/sim-recap-cron-setup` and **never written into the repository tree** — the repo ships the generator, never a `.plist`.
4. **The agent's working directory is a per-run temp dir**, created and `trap`-cleaned each tick, so it cannot touch the checkout or persist state between runs.

## Alternatives Considered

- **A second, write-capable MySQL user on the Mac** — removes the `ssh` round-trip for queue writes. Rejected because it puts a production-writing credential on the untrusted end of the boundary, collapsing properties 1 and 2 at once.
- **Extending `ibl5/scripts/storeSimRecap.php` with queue subcommands** instead of a second script — one fewer file. Rejected: its CLI contract is frozen and pinned assertion-by-assertion by `ibl5/tests/WideUnit/Scripts/StoreSimRecapGuardTest.php`, and keeping the single privileged *writer* single-purpose is worth a second guarded entry point.
- **Sanitizing the database text (player/team/transaction strings) before it reaches the prompt** — the intuitive prompt-injection defense. Rejected because the real mitigation is structural (see Consequences): sanitizing would degrade the recap and misrepresent where the safety comes from.

## Consequences

- **Positive — the boundary was measured, not assumed.** A live spike on 2026-07-23 took the tunnel branch and recorded five probes against the production grant surface:
  - A positive read succeeded over the tunnel (`SELECT sim,start_date,end_date FROM ibl_sim_dates ORDER BY sim DESC LIMIT 1` → `720 / 2008-02-26 / 2008-03-04`).
  - The exact claim statement was **refused**: `ERROR 1142 (42000): UPDATE command denied to user 'iblhoops_select'@'localhost' for table iblhoops_ibl5.ibl_sim_summaries`.
  - A throwaway DDL was **refused**: `ERROR 1142 (42000): CREATE command denied to user 'iblhoops_select'@'localhost' ...`.
  - The granted scope over the tunnel is `GRANT SELECT ON iblhoops_ibl5.* TO iblhoops_select@localhost` (plus `USAGE`) — no write of any kind.
  - An off-tunnel direct connect to the production host on 3306 **succeeded at spike time**, as `iblhoops_select` scoped to a specific IP (a cPanel Remote-MySQL host entry), not `%`.
- **Positive / accepted boundary — the reachability path is "SSH tunnel + the operator's single management host," not "tunnel-only."** The spike's off-tunnel probe surfaced two Remote-MySQL entries. The wildcard `%` entry — open-internet reachability, the exact misconfiguration the probe guards against — was **removed** by the operator on 2026-07-23. The operator's own management-host IP entry is **retained deliberately and is non-negotiable**: the operator needs direct DB access to administer the website, and that access grants nothing beyond `SELECT` on `iblhoops_ibl5`. So the accepted boundary is honestly *the tunnel plus one operator-owned host*, not a claim that the tunnel is the only path. The agent still reaches production only via the tunnel as the SELECT-only user; the retained entry is the *operator's* path, not the agent's. **Caveat to prune:** if the operator's host IP is dynamic, churn can strand a stale `SELECT` grant on a now-unowned IP — the grant list should be pruned periodically.
- **Negative — a sleeping or offline Mac silently delays the recap.** There is no alerting, and nothing distinguishes "late" from "broken": the queue row simply stays `pending`. Accepted, because the consumer is a non-time-critical human review step and the admin viewer shows queue state on demand.
- **Negative — the prompt-injection surface is real and is not sanitized.** Player names, team names, and transaction text reach the prompt straight from the database. The mitigation is *structural*: a prompt-injected agent's maximum achievable outcome is a bad recap in a temp file, because it holds no credential able to do anything else. That is stronger than input filtering and does not degrade the output.
- **Negative — prose quality is not testable.** Every automated check in this unit verifies plumbing, format, and permissions; whether a recap is *good* is a human reading it — which is exactly why the pipeline posts recaps for review rather than publishing them.

## References

- `bin/sim-recap-tick` — the credential-starved poll driver; claim loop, agent run, `--dry-run`.
- `bin/sim-recap-prompt`, `bin/lib/sim-recap-exemplar.txt` — the prompt builder and pinned exemplar (no DB access).
- `bin/sim-recap-cron-setup` — the launchd generator that injects the one credential and ships no `.plist`.
- `bin/test-sim-recap-tick` — the harness carrying the zero-`claude`-on-empty-tick invariant.
- `ibl5/scripts/simRecapQueue.php`, `ibl5/scripts/storeSimRecap.php`, `ibl5/scripts/.htaccess` — the prod-side privileged entry points and their web deny.
- `ibl5/tests/WideUnit/Scripts/SimRecapQueueGuardTest.php` — source-content guard for the CLI guard, `.htaccess` scoping, and the string-cast-on-snowflakes property.
- `ibl5/classes/SimRecap/SimSummaryRepository.php` — the frozen queue primitives the CLI exposes.
- `ibl5/docs/decisions/0080-mac-local-discord-bug-pipeline-cron-topology.md`, `ibl5/docs/decisions/0081-hunter-trust-split-starved-env-sandbox.md` — the prior-art topology and trust split this ADR extends to a production-reaching agent.
- `ibl5/docs/decisions/0062-all-work-in-worktrees.md`, `ibl5/docs/decisions/0046-worktrees-outside-repo.md` — why property 4's temp working dir enforces the read-only checkout, and why the generated plist points at the main checkout.
