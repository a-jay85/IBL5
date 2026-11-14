---
description: Automouse workflow detail. The plan autonomy contract (stop_condition and evidence frontmatter), the cost ledger columns, and the delegate effort trial. Companion to automouse-workflow.md; attaches on the same paths.
last_verified: 2026-11-13
paths: "bin/automouse/**"
---

# Automouse Workflow Detail

Companion to `.claude/rules/automouse-workflow.md`.

## Plan frontmatter: the autonomy contract

Two **optional** line-1 fields let a plan declare *when it is done*, so `/post-plan` can hold a PR whose deliverable never landed. They are a **unit**: **both or neither**. Exactly one is a `bin/check-plan` `[K]` violation.

**`stop_condition:`** is a closed enum. Any other value, **including empty**, is rejected.

- `tests-green`: holds unless Phase 5 status is `pass` (`fail`, `skipped` and *no status recorded* all fail).
- `evidence-present`: no executable track (docs / tooling-only). Done when the declared artifacts are in the diff. No run-outcome check.

**`evidence:`** is a **single line** (not a YAML block list) of comma-separated repo-relative path tokens. Surrounding whitespace is ignored. It needs **one token minimum**, and each token must appear in the PR's changed-file list at post-plan time. Name the **deliverable artifacts**, never Verification Matrix rows.

**Neither field grants merge authority.** An unmet contract only **adds** a hold; a satisfied one never arms a PR. `auto_merge:`, plan gate 14 and `feat:` human-signoff remain that surface, untouched.

**Malformed values fail at authoring time**, before a run is spent. `bin/check-plan` `[K]` names the specific defect. Unknown keys stay **silently ignored**. This adds two *recognised* keys and no reject-unknown-key rule.

```yaml
stop_condition: tests-green
evidence: bin/lib/plan-autonomy-contract, bin/test-check-plan
```

Every parser is **line-1-anchored** (frontmatter only, to the closing `---`), so the example above never self-selects. Enforced by `bin/lib/plan-autonomy-contract`, `bin/check-plan` `[K]`, `/post-plan` Phase 5.0.

## Cost ledger columns

**Recomputed vs. harness cost.** The harness `result` event undercounts: it sums only the top-level `usage` of the main transcript, missing `usage.iterations[]` entries and all subagent transcripts. `bin/lib/automouse-pricer` recomputes from transcripts after the phase exits, because subagent transcripts are still flushing when `result` fires.

**Prov column:** `recomputed` (no anomaly flagged), `recomputed-anomalous` (>$0.01 below harness or duration mismatch), `unknown` (no transcript, e.g. aged out after ~30 days; harness figure kept), `harness-ledger` (harness's `result.json` usage ledger; harness-only runs exiting 0 or 3).

**`peak_ctx`:** main transcript occupancy over `usage.iterations[]` (top-level `usage` sums them), excluding `advisor_message` iterations and sub-agents; pre-2026-08-26 rows read high. Reported cost is a floor: compaction surcharge is separate, a `low–high` range in "Surcharge est ($)" for recomputed(-anomalous) rows with a compaction, else an em-dash (—).

## Post-plan exit 76: GitHub unreachable

When the post-plan harness cannot reach GitHub, it stops with kind `remote-unreachable` and exit 76. A failed `git fetch`, `push`, `ls-remote` or `pull` raises it once netretry gives up on an outage shape (publickey, DNS, timeout, HTTP 5xx). `bin/lib/branch-base.sh` raises it by returning 2 when a base lookup could not ask GitHub. `bin/post-plan-now` passes 76 through and skips the skill fallback. `bin/automouse/run` treats 76 as environmental at any duration. It refunds the attempt, writes the env-stop report and stops the loop with the plan still queued, so the next run claims it first. A base that origin reports absent, a rebase conflict and a local gate denial still exit 3 and move the plan to skipped/.

## Orphan watcher reaping

A heartbeat watcher exits on its next tick once its run's pid is gone. Older orphans are reaped only when user-owned, PPID 1, running `bin/automouse/run` under bash, with a direct `sleep 60` child and no live `.heartbeat-<pid>-*` file naming their pid or pgid. `SINGLE_PLAN` runs skip self-heal. Tests: `bin/test-automouse-env-breaker`, `bin/test-automouse-self-heal`.

## Effort trial for delegates

`AUTOMOUSE_DELEGATE_EFFORT_TRIAL` turns on an opt-in trial for `### Delegate` packets. It is off by default. Unset or empty leaves the impl prompt byte-identical to a run without the knob, and `bin/test-automouse-escalation` pins that.

| Value | What the impl session does |
|---|---|
| `low-first` | First attempt at effort low. A packet whose check fails or cannot run gets one fresh attempt at effort high. |
| `control` | First attempt at effort high, set explicitly. Checks run and are logged. No retry. |
| `ab` | `bin/automouse/run` picks `low-first` or `control` per plan from the `cksum` parity of the plan name. |
| any other value | Logged as ignored in the run log. The trial stays off. |

The check is the packet's own `**Self-verify:**` command. The impl session runs it in the foreground after every delegate in the batch has reported, and its exit code decides the retry. A failed first attempt's diff stays in the tree and the retry rewrites it.

**Starting it.** Set the variable on the job that launches `bin/automouse/run`. For launchd: add `AUTOMOUSE_DELEGATE_EFFORT_TRIAL` with value `ab` to the `EnvironmentVariables` dict of `~/Library/LaunchAgents/com.ibl5.automouse.plist`, then reload it with `launchctl bootout` and `launchctl bootstrap`. For a T3 scheduled task or a one-off run, prefix the command: `AUTOMOUSE_DELEGATE_EFFORT_TRIAL=ab bin/automouse/run`. `run` hands the arm to the impl session on a trailing `DELEGATE_EFFORT_TRIAL=<arm>` prompt line.

**Inherited effort.** Today's delegates pass no `effort`. `effortLevel` in `~/.claude/settings.json` reads `medium`, and a surface with no flag takes that value, so today's delegates may run at medium. No delegate transcript records an effort field, so the inherited level could not be confirmed. The `control` arm passes high explicitly, so its baseline means all-high whatever inheritance does.

**Log.** Each attempt is one row in `<nightly>/logs/<plan>.delegate-attempts.jsonl`, written by `bin/measure-delegate-cost --record-attempt`. The fields are `plan`, `phase`, `arm`, `attempt`, `effort`, `check_exit`, `check_result` and `ts`. The nightly dir sits outside the repo. `archive_stale logs` moves old files to `logs.archive/` with the cost ledger, and the reader reads both.

**Keep or revert.** Run `bin/measure-delegate-cost --trial`, adding `--json` for machine output. It prints `INSUFFICIENT` until each arm has at least 30 plans and 100 packets. A plan with any `inherited` attempt is left out of its arm. Past that floor it prints `KEEP` only when the `low-first` final packet pass rate is at least the `control` rate and the `low-first` median cost per completed plan is lower. Otherwise it prints `REVERT`. On `REVERT`, unset the variable. On `KEEP`, flipping the default is a separate plan.
