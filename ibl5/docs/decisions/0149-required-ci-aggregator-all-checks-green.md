---
description: A required aggregator check, All checks green, blocks auto-merge on any red check on the PR head; condition (15) demotes to a warning only while protection lists it.
last_verified: 2026-09-30
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

1. One aggregator workflow, `.github/workflows/all-checks-green.yml`, with one job named `All checks green`. It triggers on `pull_request` types `opened`, `reopened`, `synchronize`, `ready_for_review`, `edited`, `labeled`, and `unlabeled`. It has no `paths` or `branches` filter and no job-level `if:`. The label and edit types matter because sibling workflows start jobs on those events, and the aggregator must re-settle after them.
2. The job runs `bin/check-pr-checks-green --wait` against the PR head SHA. It passes when every check run on the head concludes `success`, `skipped`, or `neutral`. It fails at once on any other conclusion. The verdict logic is shared with `bin/check-master-ci-green` through `bin/lib/ci-checks-green.sh`.
3. It waits on anchors so it cannot pass before siblings register. The four other required contexts (`Tests and Analysis`, `E2E Tests`, `Meta checks`, `Infection PHP (per-PR diff)`) must be reported and completed. Every workflow run on the head SHA other than this one must be completed too.
4. It fails closed. The script gives up at `--timeout-seconds=2400` and exits 1. The job carries `timeout-minutes: 45`, and a killed job concludes `cancelled`, which the required context reads as red. An API error while waiting counts as pending and ends in the same timeout.
5. Legacy commit statuses are not read. Zero exist on this repo (checked 2026-09-30).
6. Condition (15) demotes from BLOCKED to a WARNING only while master's live protection lists `All checks green`. The shell predicate is `pr_aggregator_required` in `bin/lib/pr-armable.sh`. The harness predicate is `LiveGh.aggregator_required` in `tools/postplan-harness/harness/ghad.py`. Both fail closed: an unreadable protection keeps the BLOCK. Merging this ADR changes nothing until the activation below runs.
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
