---
description: The Infection per-PR diff job (`Infection PHP (per-PR diff)` / `mutation-pr`) becomes a required, green-skip-safe merge gate; the weekly full mutation run gains a Discord failure alert mirroring db-backup's notify job; the branch-protection activation is a post-merge admin API POST recorded here (not performed by this PR).
last_verified: 2026-06-18
---

# ADR-0065: Mutation Testing Becomes a Required Merge Gate

**Status:** Accepted
**Date:** 2026-06-18

## Context

The `Infection PHP (per-PR diff)` job (`mutation-pr` in `.github/workflows/mutation.yml`)
ran on every unlabeled PR, scanning the diff's `classes/**/*.php` changes at
`--min-msi=100`. But it was **not** in `required_status_checks`, so a red result did
not block merge — the gate was advisory, not enforced. The broad mutation coverage
unlocked by ADR-0019 (repositories) and ADR-0020 (controllers + handlers) was therefore
not actually protecting `master`: a PR could escape a mutant and still merge.

Current branch protection on `master` requires exactly two contexts —
`Tests and Analysis` and `E2E Tests` (verified via
`gh api repos/:owner/:repo/branches/master/protection`; no rulesets exist —
`gh api .../rulesets` returns empty). Making mutation testing enforceable means adding
a third required context.

A status check is only safe to make *required* if it always reports a conclusion on
every PR — a check that reports `skipped` (rather than `success`) can block merge
indefinitely under GitHub's required-check semantics. So before requiring it, the job
must be proven skip-proof.

## Decision 1 — `mutation-pr` becomes a required, mergeable-safe gate

`mutation-pr` becomes a required status check. Two skip scenarios had to be ruled out:

- **No-`classes/`-change PRs.** The job's first seven steps (Checkout → Detect covered
  changes) have no `if` guard and always run; only steps from "Detect covered changes"
  onward gate on `steps.changes.outputs.skip == 'false'`. So on a PR that touches no
  `classes/**/*.php`, the analysis *steps* skip but the **job** still concludes
  `success` (green). No fix needed — the required-check contract already holds here.
- **`mutation-test`-labeled PRs.** The job previously carried a job-level
  `if: github.event_name == 'pull_request' && github.event.action != 'labeled'`. On a
  labeled PR that clause rendered the **whole job `skipped`** (the full `mutation` job
  takes over for labeled PRs). A job-`if`-skipped required check is *not* treated like
  a steps-skipped-but-job-`success` one. This PR removes the
  `&& github.event.action != 'labeled'` clause, so `mutation-pr` always runs on PR
  events and always reports a conclusion. Running it on a labeled PR alongside the full
  `mutation` job is harmless duplicate diff-scan work; the win is that the required
  check **never reports `skipped`.**

The escaped-mutant failure path is enforced by the pre-existing `--min-msi=100` flag on
the diff infection call (`infection --min-msi=100` exits non-zero when MSI < 100) — this
PR cites it, it does not add it.

## Decision 2 — post-merge branch-protection activation (manual admin POST)

Adding the `Infection PHP (per-PR diff)` context to `required_status_checks` is an admin
API POST that **cannot** be performed from inside the repo — same pattern as the
human-signoff gate (ADR-0062). **This ADR and its PR do NOT perform the POST.** It is a
post-merge manual step (see Activation below). The PUT *replaces* the contexts list, so
all three contexts must be passed (the two current + the new one).

## Decision 3 — weekly-full-run failure alert

A new `notify-mutation-failure` job mirrors db-backup's `notify-backup-failure`
(`.github/workflows/db-backup.yml`), firing
`if: ${{ always() && needs.mutation.result == 'failure' }}`. It delivers a Discord DM to
the owner via the existing SSH → `http://localhost:50000/discordDM` IBLbot endpoint on
the prod host, reusing the already-vetted `secrets.PRIVATE_KEY` / `secrets.HOST` /
`secrets.OWNER_DISCORD_ID` pattern — no new secret, no new endpoint.

`needs: [mutation]` scopes the alert to the **scheduled / dispatch / labeled full
`mutation` job** and deliberately **excludes the per-PR `mutation-pr` job**: a red PR
check is its own visible signal on the PR, so a DM there would be noise. The mirror
keeps `workflow_dispatch` un-filtered (no `&& github.event_name == 'schedule'`) exactly
as db-backup does, preserving the manual alert-test vector. The `ssh-keyscan` line
retains its trailing `|| true` so a transient keyscan failure cannot kill the alert
before the DM step.

### Fail-loud delivery (divergence from the original db-backup pattern)

The db-backup mirror originally ended its DM step with `... || echo "IBLbot
notification failed"`, swallowing any SSH/curl error so the **notify** job stayed
green even when the alert never reached Discord — a silent failure of the alerter
itself, with no alert-on-the-alert. This PR drops that swallow in **both** the new
`notify-mutation-failure` job and the pre-existing `notify-backup-failure` job
(`db-backup.yml`), and adds `-f` to the `curl` (`-sf`) so a non-2xx IBLbot response
(not just a transport error) also fails. A delivery failure now turns the notify job
**RED**, which on a scheduled run triggers GitHub's native workflow-failure email to
the workflow's last committer — converting a silent miss into a visible one. The
trade-off is that transient IBLbot downtime produces a red run + email; for an alert
path that noise is the correct bias (a dropped failure alert is worse than a false
one). This intentionally diverges from the verbatim db-backup mirror; the other
`|| echo` notify sites (`main.yml`, `smoke-prod.yml`, the advisory `::warning::`
sites) are left untouched and remain a candidate for a separate fail-loud sweep.

> Residual gap (not closed here): IBLbot returning HTTP 200 with an error body would
> still pass. Closing that needs a body-level health assertion or a dead-man's-switch
> heartbeat on the alert path — tracked separately, not a blocker for this gate.

## Activation (post-merge, manual)

```bash
gh api -X PUT repos/:owner/:repo/branches/master/protection/required_status_checks \
  -f 'contexts[]=Tests and Analysis' \
  -f 'contexts[]=E2E Tests' \
  -f 'contexts[]=Infection PHP (per-PR diff)'
```

The PUT replaces the contexts list, so all three must be passed (the two current + the
new one). After activation, on the **first `mutation-test`-labeled PR**, confirm that
`mutation-pr` (now always running) reports pass/fail and does not block — this is the one
runtime behavior the local plan cannot pre-verify against GitHub's required-check
semantics. The `notify-mutation-failure` alert can be smoke-tested via a
`workflow_dispatch` of the Mutation Testing workflow.

## Lineage

This ADR completes the mutation-coverage chain without superseding either prior decision:

- **ADR-0019** (Mutation Testing Unlock — Repositories) — unlocked broad mutation
  coverage for the repository layer.
- **ADR-0020** (Mutation Testing Unlock — Controllers + Handlers) — extended that
  coverage to controllers and handlers.

Those two ADRs made mutation coverage broad; this one turns that coverage into an
**enforced merge gate** plus a drift alert. Neither prior ADR is superseded.
