---
description: A required-context job may carry a job-level if: only when the skipped events provably cannot change its verdict; human-signoff and auto-mergeable-label skip irrelevant label and edit events, Meta checks stays unfiltered.
last_verified: 2026-10-22
owner: ajaynicolas
---

# ADR-0210: Payload-provable job-level skips on PR label and edit events

**Status:** Accepted
**Date:** 2026-10-22
**Deciders:** a-jay85

## Context

`.claude/rules/ci-gotchas.md` says a required context must report on every PR with no `paths`, `branches`, or job-level `if:`, because a run that never starts leaves the check pending forever. ADR-0145 recorded that a label-triggered run sharing a `cancel-in-progress` group cancelled the real run, and that GitHub does not document which of two same-name check runs on one SHA decides a required context.

Measured on 2026-10-22 on this free public repo: `Human Sign-off` ran about 1650 times in seven days, `Auto-mergeable Label` about 1820, and `PR meta checks` about 1340. During 05:00 to 07:20 UTC every job waited about 45 minutes for a runner. Each of these jobs runs for about 0.1 minutes, so a single label flip spends four or five runner slots on runs whose verdict cannot have changed. A job skipped by a job-level `if:` still produces a check run (conclusion `skipped`, which GitHub treats as success for a required context) and uses no runner.

The check-run history on PR #3388 head `c6030db2d9a6f04984e7b103b7ea6074cfa3fd43` shows a job skipped by `if:` recorded with conclusion `skipped`, and shows `human-signoff` failure, failure, success sequences that did unblock the merge. A scan of 60 recent head SHAs found no failure followed by a skipped run on one name. History therefore cannot settle skip-versus-failure precedence, and the design below does not depend on it.

## Decision

1. A job-level `if:` on a workflow that emits a required or advisory PR context is permitted only when the skip is payload-provable. The event's own payload shows that none of the job's verdict inputs changed. For a required context, the verdict on the live inputs is also provably success, so a skipped run equals the verdict under either same-name check-run precedence. The predicate appears twice, in the job `if:` and in the workflow `concurrency.group`. A skip-destined run gets a per-run group (`<name>-<pr>-skip-<run_id>`) and can never cancel a real queued run. `bin/test-human-signoff-classifier` asserts the two copies are identical and that the guard names the labels the job reads. It also evaluates the predicate over event fixtures with `bin/lib/gha-if-eval.py` and runs permanent mutation checks.
2. `human-signoff` (required) skips `labeled`/`unlabeled` for any label except `human-approved`, and `edited` without `changes.title`, only when the title contains no `feat` substring. Its verdict reads the title and the `human-approved` label only. A title with no `feat` cannot match the classifier regex `^feat(\([^)]*\))?!?:`, and `contains()` is case-insensitive, so every title that could fail runs. A PR held today cannot become mergeable: held means the regex matched, which forces a run.
3. `auto-mergeable label` (advisory, in `--ignore=` of `.github/workflows/all-checks-settled.yml`, the required aggregator) skips `labeled`/`unlabeled` for any label except `human-approved` and `auto-mergeable`, and `edited` without `changes.title` and without `changes.base`. Its four inputs (auto_merge, title, and the two labels) are unchanged by those events, so no title guard is needed. A base edit still runs. A PR retargeted onto master never ran this workflow before, so no earlier run set its label.
4. `Meta checks` (required) stays unfiltered. Its verdict depends on the live PR body (`bin/check-pr-manual-testing`, `bin/check-post-merge-recipe`, `bin/adr-check --pr`, `bin/refactor-flag` bypass markers) and on the diff, so no `edited` payload proves irrelevance. The `changes` object carries only `title`, `body`, and `base`, and a title-only edit still cannot prove the body-driven verdict was success. A skip there could replace a prior body-driven failure with a success-equivalent run on the same SHA. A step-level early exit was rejected: it still consumes a runner, so it saves nothing, and it would report success over that prior failure. The workflow has no `labeled`/`unlabeled` trigger, and `edited` stays because a body edit is the only remediation the manual-testing check offers.
5. `All checks green` is not changed. Its verdict depends on sibling checks, so no payload proves irrelevance, and `bin/check-composite-contracts` A1/A3 forbid a job-level `if:` there. PR #3425 removes its `pull_request` trigger in favour of an event-driven settle, which is the right fix for that workflow.
6. The rule in `.claude/rules/ci-gotchas.md` gains this carve-out. `bin/check-composite-contracts` is not extended here (PR #3425 edits it). The drift guard lives in `bin/test-human-signoff-classifier`.

### Run versus skip, per workflow and event type

`run` means the job executes. `skip` means the job-level `if:` is false, GitHub records a `skipped` check run, and no runner is used. `feat*` means the title contains the substring `feat` (case-insensitive); `nofeat` means it does not.

| Event | `human-signoff` | `sync-label` | `meta-checks` | `All checks green` |
|---|---|---|---|---|
| `opened`, `reopened`, `synchronize`, `ready_for_review` | run | run | run | run (unchanged) |
| `labeled`/`unlabeled` `human-approved` | run | run | no trigger | run (unchanged) |
| `labeled`/`unlabeled` `auto-mergeable` | `nofeat`: skip; `feat*`: run | run | no trigger | run (unchanged) |
| `labeled`/`unlabeled` any other label | `nofeat`: skip; `feat*`: run | skip | no trigger | run (unchanged) |
| `edited` with `changes.title` | run | run | run | run (unchanged) |
| `edited` body-only | `nofeat`: skip; `feat*`: run | skip | run (by decision) | run (unchanged) |
| `edited` with `changes.base` | `nofeat`: skip; `feat*`: run | run | run (by decision) | run (unchanged) |
| `auto_merge_enabled`/`auto_merge_disabled` | no trigger | run | no trigger | no trigger |

### Race orderings for `human-signoff` on one head SHA

1. A title edit is queued, then an unrelated label event arrives. The `edited` run carries `changes.title` and runs in group `human-signoff-<pr>`. The label run evaluates the predicate against the title as of the label event. If that title contains `feat` it runs. Otherwise it skips in its own group and cannot cancel the queued `edited` run.
2. A label event, then a title edit. The title edit carries `changes.title`, so it always runs and recomputes.
3. A failure, then a skip. The failure means the title matched the feat regex. For a later event to skip, the title must have lost `feat`, so a title `edited` run happened in between and reported success. Under latest-run-wins the skip equals that success. Under most-recent-non-skipped-wins the deciding run is the success. Under any-failure-wins the old failure blocks with or without this change.

## Alternatives Considered

- **Step-level early exit.** Rejected because the job still waits for a runner, and on a required context it writes a fresh success over the prior real conclusion.
- **Narrowing the `types:` lists.** Rejected because `human-approved` flips and title edits must still trigger the workflow.
- **A shared predicate file.** Rejected because Actions cannot include an expression from a file. The duplication is pinned by test.
- **`startsWith(title, 'feat')` as the title guard.** Rejected because a future classifier tweak (a `feature:` prefix or a leading emoji) could silently invalidate it. `contains` survives those.

## Consequences

- Positive: a label flip on an unrelated label uses runners only for the workflows whose verdict can move.
- Positive: skip-destined runs no longer share the real run's `cancel-in-progress` group, which closes the ADR-0145 cancellation path for these two workflows.
- Negative: the predicate must stay identical in two places per workflow, enforced by `bin/test-human-signoff-classifier`.
- Negative: a `feat` substring anywhere in a title (for example `chore: refactor feature flags`) still runs the gate. That costs one extra cheap run and never misses one.

## References

- `.github/workflows/human-signoff.yml`
- `.github/workflows/auto-mergeable-label.yml`
- `.github/workflows/pr-meta-checks.yml`
- `bin/lib/human-signoff-classifier.sh`
- `bin/lib/gha-if-eval.py`
- `bin/test-human-signoff-classifier`
- `.claude/rules/ci-gotchas.md`
- `ibl5/docs/decisions/0145-mutation-pr-required-own-workflow.md`
