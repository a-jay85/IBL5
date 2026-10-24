---
description: A required aggregator blocks auto-merge on any red check on the PR head; the 2026-10-21 addendum replaces the polling All checks green job with the All checks settled commit status. Condition (15) demotes to a warning only while protection lists the aggregator. A 2026-10-22 addendum retries the settle job's API reads and closes an empty-body fail-open. A second 2026-10-22 addendum lets a frozen run whose latest-attempt jobs all finished green settle after a 600 s grace, and a 15-minute cron sweep re-dispatches stale pending statuses.
last_verified: 2026-10-22
owner: ajaynicolas
---

# ADR-0149: Required CI Aggregator `All checks green`

**Status:** Accepted
**Date:** 2026-09-30
**Deciders:** ajaynicolas

## Context

Master requires five contexts: `Tests and Analysis`, `E2E Tests`, `human-signoff`, `Meta checks`, and `Infection PHP (per-PR diff)`. GitHub auto-merge ignores every other check, red or green. PR #2304 merged while the non-required `pytest (stdlib harness)` was red.

Condition (15) of `/post-plan` Phase 6.5 was the stopgap. It blocks arming when a check on the head is already red. It only sees failures that finish before arm time. A check that is still pending when auto-merge arms and fails later is invisible to it.

Requiring every check name was rejected. Path-filtered workflows do not run on every PR, and a required context with no check run leaves the PR pending forever.

## Decision

1. One aggregator workflow, `.github/workflows/all-checks-green.yml` (example), with one job named `All checks green`. It triggers on `pull_request` types `opened`, `reopened`, `synchronize`, `ready_for_review`, `edited`, `labeled`, and `unlabeled`. It has no `paths` or `branches` filter and no job-level `if:`. The label and edit types matter because sibling workflows start jobs on those events, and the aggregator must re-settle after them.
2. The job runs `bin/check-pr-checks-green --wait` against the PR head SHA. It passes when every check run on the head concludes `success`, `skipped`, or `neutral`. It fails at once on any other conclusion. The verdict logic is shared with `bin/check-master-ci-green` through `bin/lib/ci-checks-green.sh`.
3. It waits on anchors so it cannot pass before siblings register. The four other required contexts (`Tests and Analysis`, `E2E Tests`, `Meta checks`, `Infection PHP (per-PR diff)`) must be reported and completed. Every workflow run on the head SHA other than this one must be completed too.
4. It fails closed. The script gives up at `--timeout-seconds=2400` and exits 1. The job carries `timeout-minutes: 45`, and a killed job concludes `cancelled`, which the required context reads as red. An API error while waiting counts as pending and ends in the same timeout.
5. Legacy commit statuses are not read. Zero exist on this repo (checked 2026-09-30).
6. Condition (15) demotes from BLOCKED to a WARNING only while master's live protection lists `All checks green`. The shell predicate is `pr_aggregator_required` in `bin/lib/pr-armable.sh`. The harness predicate is `LiveGh.aggregator_required` in `tools/postplan-harness/harness/adapters/ghad.py`. Both fail closed: an unreadable protection keeps the BLOCK. Merging this ADR changes nothing until the activation below runs.
7. `bin/check-composite-contracts` pins these properties on every PR (A1 to A4). A2 checks that the literal `All checks green` matches across the workflow job, `bin/lib/pr-armable.sh`, `tools/postplan-harness/harness/armable.py`, and this ADR.
8. Never a merge queue, and no `merge_group` trigger.

### Excluded check names

The workflow's `--ignore=` list. A check-run name is the job's `name:`, or its job id when `name:` is absent.

| Name | Source | Why advisory |
|---|---|---|
| `All checks green` | this job | itself (`--self`) |
| `human-signoff` | `human-signoff.yml` | red by design on `feat:`; condition (8) owns it |
| `needs-a-human label` | `human-signoff.yml`, step `continue-on-error` | label mirror; a red here must never read as a CI failure |
| `auto-mergeable label` | `auto-mergeable-label.yml`, `continue-on-error` | label sync |
| `Enable auto-merge` | `dependabot-auto-merge.yml`, `if: user == dependabot[bot]` | skipped on human PRs; on bot PRs a failed arm is no code-health signal |
| `notify-merge-digest` | `merge-digest-notify.yml`, `types: [closed]` | webhook on close; present on a head only after close and reopen |
| `notify-fixed` | `pipeline-fixed-notify.yml`, `types: [closed]` | same |
| `Infection PHP` | `mutation.yml`, label or cron only | the full suite never blocks merge |
| `Notify Mutation Failure` | `mutation.yml` | webhook |

Every other check stays in scope on purpose, including `pytest (stdlib harness)`. A path-filtered job that does not run has no check run on the head and cannot hold the verdict. To make a new job advisory, add its name to `--ignore=` and to this table.

## Activation (post-merge, manual, admin token)

1. Precondition. The workflow has reported success at least once:

   ```bash
   gh run list --repo a-jay85/IBL5 --workflow all-checks-green.yml \
     --status success --limit 5
   ```

2. Straggler audit. Open PRs whose head SHA has no completed, non-cancelled run of the context. Must print nothing:

   ```bash
   gh pr list --repo a-jay85/IBL5 --state open --base master \
     --json number,headRefOid --jq '.[] | "\(.number) \(.headRefOid)"' |
   while read -r n sha; do
     c=$(gh api "repos/a-jay85/IBL5/commits/$sha/check-runs" \
       -f check_name='All checks green' -X GET \
       --jq '[.check_runs[] | select(.status == "completed"
         and .conclusion != "cancelled")] | length')
     [ "$c" -gt 0 ] || echo "STRAGGLER #$n $sha"
   done
   ```

   For each straggler, run `gh pr update-branch <n>`. Do not close and reopen a PR, because closing clears its auto-merge. Re-run the audit until it prints nothing.

3. Backup:

   ```bash
   PRE=~/claude-plans/_backups/master-protection-pre-$(date +%F).json
   gh api repos/a-jay85/IBL5/branches/master/protection > "$PRE"
   ```

4. Apply, append-only, with a bare JSON array body:

   ```bash
   echo '["All checks green"]' | gh api -X POST \
     repos/a-jay85/IBL5/branches/master/protection/required_status_checks/contexts \
     --input -
   ```

   Never use the protection `PUT`, which replaces the whole object. Never use `-f 'contexts[]=...'`, which returned an empty HTTP 500 in ADR-0145.

## Read-back

```bash
POST=/tmp/master-protection-post.json
gh api repos/a-jay85/IBL5/branches/master/protection > "$POST"
bin/check-composite-contracts --protection-readback="$POST" --pre-image="$PRE" \
  --context='All checks green'
```

Expected output: `protection-readback: OK — 6 contexts (pre 5 + "All checks green"), strict=true, nothing else changed`. Any other result means roll back now.

Then run `bin/pr-triage` on an open PR with a red non-required check. Condition (15) must print its WARNING wording.

## Rollback

```bash
echo '["All checks green"]' | gh api -X DELETE \
  repos/a-jay85/IBL5/branches/master/protection/required_status_checks/contexts \
  --input -
diff <(jq -S . "$PRE") \
  <(gh api repos/a-jay85/IBL5/branches/master/protection | jq -S .)
```

The `diff` prints nothing when protection matches the saved pre-image. Condition (15) goes back to blocking on the next arm with no code change.

## Re-run recovery (stale aggregator)

Re-running one flaky sibling job with `gh run rerun` makes a new green check run. It does not re-trigger the aggregator, so the PR stays blocked on the aggregator's earlier red verdict. Re-run the aggregator after the sibling goes green:

```bash
gh run list --repo a-jay85/IBL5 --workflow all-checks-green.yml \
  --branch <head-branch> --limit 1 --json databaseId --jq '.[0].databaseId' |
  xargs gh run rerun
```

The `gh run rerun` payload caveat in `.claude/rules/ci-gotchas.md` does not bite here. The aggregator reads only the head SHA, and a re-run does not change it. Fallback: `gh pr update-branch <n>` or an empty commit.

## Consequences

- Every PR push holds one runner for about 15 minutes while the aggregator waits. The repo is public, so there are no paid minutes. The concurrency limit is 20 jobs, and the aggregator adds one per active PR.
- A red check in the excluded table never blocks a merge.
- A stale red after a manual re-run needs the recovery above.
- The anchors are a hardcoded list in the workflow. Renaming a required context without updating `--anchor=` makes every PR time out red. That failure is loud and closed.

## Alternatives Considered

- **Two PRs.** Activation first and demotion second was rejected. The demotion reads live protection, so one PR is safe: condition (15) keeps blocking until the context is listed.
- **A `workflow_run` aggregator.** Rejected. It fires once per completed sibling, reports in the default-branch context, and its check-run attachment to the PR head is unclear.
- **Requiring every check name.** Rejected. Path-filtered workflows leave required contexts pending forever.

## Lineage

ADR-0120 (manual protection flip with a checked read-back) and ADR-0145 (append-only context POST, `--protection-readback`).

## Addendum 2026-10-11: stacked-base fail-fast

A PR stacked on another open PR has a base other than master. `tests.yml` and `pr-meta-checks.yml` filter on `branches: [ master, develop ]` and `e2e-tests.yml` on `[ master ]`, so the Tests and Analysis, E2E Tests and Meta checks anchors never report on such a PR. The aggregator waited out its 2400 s deadline and failed red on every push, about 45 runner minutes each time.

The workflow now passes `BASE_REF` (the PR's base branch, through a step `env:` line) to `bin/check-pr-checks-green --base-ref=`. When the base is not in the script's `MASTER_LIKE_BASES` list (`master develop`), the script exits 1 before any API call with a `FAIL: stacked on <base>` message. The message tells the author to push after the parent merges and GitHub retargets the PR. The gate stays closed: a stacked PR was red before and is red now, only faster. Master-based PRs keep the same anchors, the same 60 s startup grace (moved from an inline `sleep 60` into `--startup-grace-seconds=60`, still outside the deadline), and the same 2400 s deadline.

A retarget alone starts no CI, because `tests.yml` and `e2e-tests.yml` do not list `edited`. The child PR must push anyway to absorb the parent's squash commit (`.claude/rules/linear-history-squash-merge.md`), and that push fires `synchronize` and a fresh aggregator run.

Correction to `## Re-run recovery (stale aggregator)`: the aggregator now reads `base.ref` from the event payload as well as the head SHA. A `gh run rerun` replays the frozen payload, so re-running a stacked-base failure after the retarget still sees the old base and fails again. Push instead. The re-run recipe above stays correct for a master-based PR.

`bin/check-composite-contracts` A5 pins the wiring: the step passes `--base-ref="$BASE_REF"`, and the base-ref expression appears only on the `BASE_REF:` env line, never inline in `run:`.

## Addendum 2026-10-21: event-driven commit-status aggregator

**Context.** The repo is public, so Actions runs under the 20-concurrent-job cap. On
2026-10-21 17 of the 20 running jobs were `All checks green` waiters, some 40 minutes old,
with 109 runs queued across 55 open PRs. The waiters held the slots the checks they were
waiting on needed; several hit the 45-minute deadline, went red, and the re-push added load.

**Decision.** The required aggregator becomes a commit status named `All checks settled`,
written by `.github/workflows/all-checks-settled.yml` through `bin/settle-pr-status`.
The workflow runs from master on `workflow_run: completed` of every PR-triggered workflow
and on `workflow_dispatch` with a PR number, takes seconds, and never waits. Each run
re-reads the live check-runs and workflow runs for the PR head SHA through
`bin/check-pr-checks-green` in one-shot mode (exit 0 success, 1 failure, 3 pending) and
posts the mapped state. A commit status is keyed to one SHA, which answers the
"check-run attachment unclear" objection in the original Alternatives section.

**What stays.** Pass/fail rule V1-V5; the four anchors; the `--ignore=` list; the
stacked-base fast-fail and its retarget-and-push message; fail-closed on API error
(nothing is posted and the run goes red). A new SHA starts with no status, so a push
can never inherit `success`. A late event for a superseded SHA is refused by comparing
the event head with the PR's current head from the API. Fork PRs are settled from
master code only; their head is resolved through `commits/{sha}/pulls`.

**What changes.** Decision items 1 (triggers) and 4 (bounded wait) are superseded.
Recovery for a stuck status is `gh workflow run all-checks-settled.yml -f pr_number=N`;
`gh run rerun` on a sibling also re-settles, since its completion fires the event.
Branch protection keeps one aggregator entry; the status needs an any-source entry
(`app_id` null), which the append-only `POST .../required_status_checks/contexts` creates.
The protection entry for `All checks green` is pinned to the Actions app and cannot be
satisfied by a status, so the two aggregators coexist until the runbook below completes.

**Cutover runbook.** A human runs these steps after this PR merges under the legacy gate.
1. `gh run list --workflow all-checks-settled.yml --limit 10` shows completed runs
   once any open PR's sibling finishes.
2. Backfill every open PR:
   `gh pr list --state open --limit 100 --json number -q '.[].number' | xargs -n1 -I{} gh workflow run all-checks-settled.yml -f pr_number={}`
3. Throwaway PR: a docs-only change goes pending then success; a push with a failing
   PHPUnit assertion goes failure; the fix push shows a fresh pending then success.
4. `gh api repos/a-jay85/IBL5/branches/master/protection > pre.json`, then
   `gh api -X POST repos/a-jay85/IBL5/branches/master/protection/required_status_checks/contexts -f 'contexts[]=All checks settled'`,
   `gh api repos/a-jay85/IBL5/branches/master/protection > post.json`, and
   `bin/check-composite-contracts --protection-readback=post.json --pre-image=pre.json --context='All checks settled'`.
5. `gh api -X DELETE repos/a-jay85/IBL5/branches/master/protection/required_status_checks/contexts -f 'contexts[]=All checks green'`; the readback in step 4 against a fresh dump shows only that entry gone.
6. Open the retirement PR (delete `all-checks-green.yml`, drop its three remaining
   tokens; see the plan's Out of Scope). It merges under the new gate.

**Escape hatch.** If `All checks settled` stops settling repo-wide, undo steps 5 and 4.
A POST re-adds `All checks green` as an any-source entry, which the legacy check-run
satisfies. A DELETE then removes `All checks settled`. Both are single API calls.

**Retired 2026-10-22.** Cutover runbook steps 1 to 5 are done and the retirement PR deleted the legacy waiter. `.github/workflows/all-checks-green.yml` (example) no longer exists, and nothing ignores `All checks green`. The escape hatch above no longer applies. The step 4 POST pinned the new entry to the GitHub Actions app (`app_id` 15368). The workflow posts its status as `github-actions[bot]`, so the pinned entry is satisfied.

**Interim window.** Condition (15) reads `All checks settled` from live protection. From
this merge until runbook step 4 adds that entry, condition (15) blocks arming on any red
check, as it did before ADR-0149. That is the safe direction. The legacy waiter still
gates merge in that window.

**Contract renumbering.** `bin/check-composite-contracts` A1-A6 now pin
`all-checks-settled.yml`: the triggers, the context literal shared with `bin/lib/pr-armable.sh`
and the harness, the no-wait job shape, least-privilege wiring, the four anchors with the
legacy ignore shim, and the listed-versus-derived PR workflow names. The base-ref pin from
the 2026-10-11 addendum went with the old A-block. The `branches: [master]` filter on
`promote-to-production.yml` is pinned as P9, since P1 already names the force-push guard.

**Residual risks.** `ccg_dedupe` in `bin/lib/ci-checks-green.sh` keeps the last run per
name after sorting on `completed_at // started_at`. A cancelled run often completes in the
same second its replacement starts, and on that tie the API order decides. On PR 3421 the
cancelled `Meta checks` run won. The cancellation itself fires `workflow_run: completed`,
so the status can read `failure` while `gh pr checks` shows pending. The replacement's
completion re-settles it. The lib is unchanged here, and the fix is a follow-up. A workflow added
later with a `pull_request` trigger but left out of the `workflows:` list would never
fire a settle; A6 fails the PR that adds it.

## Addendum 2026-10-22: API read retry in the settle job

On 2026-10-22, 25 of about 220 `all-checks-settled.yml` runs exited 2 with `API read error: ...: unexpected end of JSON input` during a GitHub incident. A failed run posts no status, so the PR kept a stale one until a sibling completion re-settled it.

`bin/settle-pr-status` and `bin/check-pr-checks-green` now send their GitHub API reads through `gh_retry_json` in `bin/lib/gh-retry.sh` (ADR-0195 addendum 2026-10-22). Each read gets 3 attempts, with 2s and then 5s between them. A read retries on a non-zero exit, an empty body, a body that does not parse as JSON, HTTP 5xx and HTTP 429. Any other HTTP 4xx fails at once. Both scripts set the budget with `: "${IBL5_GH_RETRY_DELAYS:=2 5}"`, so the workflow file and the A-block of `bin/check-composite-contracts` are unchanged. The added sleep is at most 7s per read and under 30s for the whole job, well inside `timeout-minutes: 5`.

The job stays fail-closed. An exhausted read still exits 2 and writes no status. The `--head-sha` path now also rejects a `commits/<sha>/pulls` body that is not a JSON array. Before this, an empty body reached the `NOTHING: no open PR has head` exit 0 and silently skipped the PR. The status POST is not retried, because its payload arrives on stdin and a rerun would send an empty body. Cases S11-S16 and P25-P29 in `bin/test-ci-checks-green` pin this.

## Addendum 2026-10-22: ghost workflow runs and the stale-pending sweep

**Observed.** On 2026-10-22 run 39824169608 of `Dependabot Auto-Merge` on PR 3446 (head `2c506a9248fe16c496adaa9b33fb43a116bd3f94`) had its only job concluded `skipped` at 17:36:22Z, while `GET /actions/runs/39824169608` kept `status: in_progress`, `conclusion: null` and a frozen `updated_at` for over 20 minutes. GitHub had an open incident. V4 held the status at `pending: WAIT: workflow runs still active: Dependabot Auto-Merge`. The run never completed, so no further `workflow_run` event arrived to re-settle it, and the merge stayed blocked with every check green.

**Decision 1.** A settled ghost no longer holds V4. `bin/check-pr-checks-green` reads the jobs of each still-active run (`GET /actions/runs/{id}/jobs?filter=latest`) and treats the run as settled only when all of these hold: the run has a numeric id, a `run_attempt` and a parseable `updated_at`; at least one job belongs to the latest attempt; every such job is `completed` with a conclusion in {success, skipped, neutral}; and `now - updated_at` is strictly greater than `GHOST_GRACE` (600 s, `--ghost-grace-seconds`). Every other state holds as before, and a jobs read error is the existing `apierr` verdict. The decision is one pure jq function, `ccg_ghost_reason` in `bin/lib/ci-checks-green.sh`. A ghost pass is loud: the PASS line, and so the status description, opens with `ghost-settled by jobs: <name>#<run id>`, and the job log carries one `GHOST:` line per run.

**Why 600 s.** Sampled 2026-10-22 over 80 recent completed `pull_request` runs: run closure lagged the last job by at most 10 s, and the longest gap between one job finishing and the next job of the same run starting was 109 s. Ten minutes is about 5x the worst gap and well under the 20-minute freeze observed. The grace covers only a job the API has not created yet. A created job that has not started is already `queued` and holds on its own.

**Decision 2.** A stateless sweep re-settles stale pending statuses. `.github/workflows/all-checks-settled-sweep.yml` runs every 15 minutes. Its one step, `bin/settle-pr-status --sweep-stale-minutes=15`, dispatches `all-checks-settled.yml` (ref master) for every open PR whose `All checks settled` status is `pending` and older than 15 minutes. The workflow holds `actions: write` as its only write scope and runs on no PR event. `bin/check-composite-contracts` A7 pins that shape and requires the window to be at least `GHOST_GRACE`, so the sweep cannot re-settle inside the grace window. A1 is unchanged, and `all-checks-settled.yml` gains no `schedule:` trigger.

**Rejected.** Widening the aggregator job `if:` or adding `--wait` (A3 forbids both, and a waiter is the design this ADR replaced). A delayed self-redispatch from the aggregator (it would put `actions: write` on the workflow that holds `statuses: write`, and a run cannot know whether it is the last event). Dispatching only when the description reads `WAIT: workflow runs still active:` (a settle that exited 2 on an API error leaves the previous pending status in place with any description, so the sweep covers every stale pending status).

**Bounds.** V1-V3 still apply on top: a red job's check-run fails the verdict before any jobs call. A run with zero jobs on the latest attempt, a pending job, a cancelled or null-conclusion job, or an age of 600 s or less keeps holding. The sweep may re-dispatch an honest long run; the aggregator then re-posts `pending` in seconds. The jobs read goes through `gh_retry_json` like the two reads beside it (addendum above).
