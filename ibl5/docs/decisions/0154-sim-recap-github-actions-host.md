---
description: Sim recaps run on a GitHub Actions runner, triggered by a repository_dispatch from prod with an hourly schedule as fallback, over a dedicated forced-command SSH key; the Mac poller stays as a backup.
last_verified: 2026-10-06
---

# ADR-0154: Run the sim-recap pipeline on GitHub Actions

**Status:** Accepted
**Date:** 2026-09-30
**Deciders:** a-jay85

## Context

Recaps ran only while one Mac was awake and online. `launchd` polled prod every 300 seconds through `bin/sim-recap-tick`. The repo is public, so an Actions host must never print the agent transcript, the prompt, DB output, roster context or the recap payload to the job log. It must also hold credentials the agent cannot reach. The existing deploy key `PRIVATE_KEY` is unrestricted and must not be reused for this.

## Decision

Run `bin/sim-recap-tick` on `ubuntu-latest` from `.github/workflows/sim-recap.yml`, driven by events. Prod posts a `repository_dispatch` of type `sim-recap` from `QueueSimSummaryStep` through `SimRecap\GitHubDispatchClient`, using a fine-grained PAT held in the untracked `ibl5/config/github-dispatch.config.php`. An hourly `schedule` covers a lost dispatch and stale-lease reclaims. A failed dispatch changes only the step message and never fails queuing.

The tick gains two seams. `SIM_RECAP_HOST_MODE=actions` turns the launchd bootout and the local db-sync into logged no-ops. `SIM_RECAP_OPS_WEBHOOK_URL` sends ops alerts through a Discord webhook, with the URL passed to `curl -K -` on stdin so it never appears in argv.

Prod access uses a dedicated ed25519 key restricted to `restrict,port-forwarding,permitopen="127.0.0.1:3306",command="<root>/bin/sim-recap-ssh-gate"`. The forced command in `bin/sim-recap-ssh-gate` admits only the queue, context and store script invocations the tick issues, with digit-only `--sim` values, and builds argv from matched pieces with no `eval` and no shell. `permitopen` bounds the tunnel to the MySQL loopback port. The ADR-0093 credential split holds: the agent subshell receives only `MYSQL_PWD`, and the SSH key stays with the tick process.

Ops-alert edge state lives in the Actions cache, saved under a unique run key and restored by prefix. It holds a timestamp and a count. Cache eviction reproduces the documented fresh-machine behaviour of re-alerting on the next failure. The Mac poller stays installed as a backup because the queue claim is an atomic conditional UPDATE, so two hosts can run at once.

Workflow hygiene is asserted by `bin/test-sim-recap-tick`. Secrets reach the job only through `env:` and `with:`. The tick's stdout goes to `/dev/null`. The agent's output is captured to a temp file removed by the EXIT trap. No artifact is uploaded. `pull_request` runs stop at the secrets gate.

## Alternatives Considered

- **`anthropics/claude-code-action`.** A GitHub-event-shaped agent loop that posts to the triggering PR or issue. Rejected because the tick needs a headless `claude -p` with its own prompt, JSON output and tool allow-list.
- **A second Actions-specific driver.** Rejected because it would fork the cost guards and the fail-closed queue handling that took several incidents to get right.
- **A `permitopen`-only authorized_keys line.** Rejected because the key would be a full shell.
- **A `command=` line without `permitopen`.** Rejected because any loopback port on prod would be forwardable.
- **Prod-side edge state.** Storing the ops-alert state behind the queue script was rejected because it would add a privileged write verb to `ibl5/scripts/simRecapQueue.php` and widen what the restricted key can invoke, for a value whose loss is harmless.
- **Reusing `bin/discord-dm` for the dispatch call.** Rejected because it runs on the Mac and pushes toward prod. The direction is wrong and it carries no GitHub auth.

## Consequences

- Positive: recaps no longer depend on one Mac being awake. Prod triggers a run within seconds of queuing a sim.
- Positive: the prod key can run four scripts and reach one loopback port. A leaked runner secret cannot open a shell.
- Positive: with no secrets the workflow is inert and green, so merging ahead of the human setup carries no runtime risk.
- Negative: while both hosts run, an outage can produce one onset ping and one recovery ping per host. The runbook documents uninstalling the Mac poller.
- Negative: `CLAUDE_CODE_OAUTH_TOKEN` is tied to one subscription and lives only as a single-repo Actions secret.
- Negative: rotating any secret is a GitHub-secret update. Rotating the SSH key also needs a `--print-key-line` reinstall on prod.
- Negative: the prod box trusts that the `restrict` line is installed exactly as emitted. A hand-edited line without `command=` grants the key a shell. The runbook's post-install check (`id` must print `denied`) catches that at install time.

## Lineage

Extends the sim-recap poller design recorded in ADR-0093 with a second host. ADR-0093 is not superseded: the Mac poller stays as a backup, and its credential split is unchanged and restated above as a constraint.

## References

- `.github/workflows/sim-recap.yml`
- `bin/sim-recap-tick`
- `bin/sim-recap-ssh-gate`
- `bin/test-sim-recap-tick`
- `bin/test-sim-recap-ssh-gate`
- `ibl5/classes/SimRecap/GitHubDispatchClient.php`
- `ibl5/classes/Updater/Steps/QueueSimSummaryStep.php`
- `ibl5/config/github-dispatch.config.example.php`
- `ibl5/docs/OPERATIONS_RUNBOOK.md`

## Addendum: pull_request event guard moved to a step-level if (2026-10-06)

The Decision section says `pull_request` runs stop at the secrets gate. When this ADR was written the gate was a shell test inside the `Check secrets` step, and the step's `env:` block had already mapped the nine secrets before that test ran, so a same-repo PR run carried the configured secrets in its step environment (run 37445899413 shows `HOST`, `PORT`, and `USERNAME` masked-present). Backlog issue 1267.

The step now carries `if: github.event_name != 'pull_request'` and the `EVENT` mapping is gone. A PR run skips the step, no secret is mapped, and every later step skips because `steps.secrets.outputs.configured` is empty. The run stays green. `bin/test-sim-recap-tick` asserts that every step mapping a secret is gated by that `if:` or by the `configured` output, and fails on a copy where the guard is deleted, points at another event, or a stray step maps a secret with no `if:`.

This guard is defense in depth against a hasty edit. GitHub passes no secrets to fork PRs, and a same-repo author already has write access, so an Environment with deployment-branch rules remains the access boundary; that is a repository setting outside this ADR.
