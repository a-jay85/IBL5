---
description: "Meta checks" is promoted to a required status check on master, and the adr paths-filter gains an else-branch so a filter skip is legible in the job log.
last_verified: 2026-09-06
owner: ajaynicolas
---

# ADR-0120: "Meta checks" Required, and adr-filter Skips Made Observable

**Status:** Accepted
**Date:** 2026-09-06
**Deciders:** ajaynicolas

## Context

`bin/adr-check` emitted a correct FAIL on two PRs that landed without an ADR, and neither FAIL blocked the merge. The root cause was not the gate logic — the gate is correct — but its wiring: "Meta checks" was configured as an advisory status check on `master` rather than a required one, so its FAIL was visible in the UI but not a merge blocker (E57 in the dev-efficiency backlog).

The second structural problem is skip observability. When a GitHub Actions step is filtered out by its `if:` condition, the step's entry in `statusCheckRollup` is reported identically to a step that ran and passed. A `bin/adr-check` invocation gated behind a paths-filter that does not match the PR's diff therefore produces a silent green: the job log carries no evidence that anything was skipped, and a maintainer reading the job summary cannot distinguish "adr-check ran and passed" from "adr-check was skipped because no trigger paths changed". The two incidents in E57 confirm that a silent skip is the failure mode to close.

## Decision

1. **"Meta checks" is promoted to a required status check on `master`**, applied via `gh api` after this PR merges. Branch protection for this repository is API-only — there is no `.github/rulesets/*.json` and `bin/sync-branches` does not manage `required_status_checks` — so this ADR is the in-repo record of the change. The exact `gh api` recipe and its round-trip verification are documented in Phase 5 of the plan that drove this change; they must be executed by an operator after merge.

2. **An else-branch step is added to the `adr` paths-filter in `.github/workflows/pr-meta-checks.yml`** so that when the paths-filter does not match the PR's diff, the step explicitly echoes a message stating that adr-check was skipped and why. The step's `if:` guard is the complement of the run step's guard, restricted to `pull_request` events (where a PR context exists), and conditioned on the job not being cancelled. A skipped run now produces a named, visible log line instead of a blank.

3. **`auto_merge: false` for this PR.** This PR changes the enforcement mechanism that governs its own merge: once "Meta checks" is required, a failing `bin/adr-check` is a merge blocker, and this PR installs the ADR that makes its own gate pass. A self-merging bootstrap change cannot be reviewed by the gate it installs, and any error in the wiring would block every open PR in the repository. A human reads and merges this PR manually.

## Rationale

Promoting "Meta checks" to required is the minimal fix: the gate's logic is correct, and the two incidents confirm the advisory-vs-required distinction is the only gap. No change to `bin/adr-check`'s acceptance logic, trigger set, or bypass semantics is needed or made.

An else-branch step is the narrowest mechanism that produces an observable log line on a skip without changing what runs on a non-skip. Running the gate unconditionally is not an equivalent substitute: on `push` events there is no PR context, so an unconditional run either fails spuriously or needs its own event guard — the same guard the else-branch carries, with none of the benefit. The observability problem and the coverage problem are independent: a broader paths-filter glob changes what is caught; it cannot make a miss visible. Both are worth solving, but they are not the same fix.

The branch-protection flip is sequenced after merge to avoid a chicken-and-egg. Promoting "Meta checks" to required before this PR's own CI run completes would make the gate a merge blocker for this PR itself — and for every other open PR in the repository — before the workflow change that gives the else-branch its meaning has landed.

## Alternatives Considered

- **Always-run `adr-check` (no paths-filter guard)** — runs unconditionally on every event including `push`, where there is no PR number or body to read. Rejected: fails spuriously on push events or requires a duplicate event guard, adding fragility for no observability gain on the filter-skip case.

- **A job-summary step** — appends a summary entry to the GitHub Actions job log only on skip. Rejected: pays matrix overhead and summary rendering cost on every run for information that is interesting only on a filter skip; the else-branch step is zero-cost on non-skip runs.

- **Broadening the `adr` paths-filter globs** — catches more trigger surfaces. Rejected: explicitly out of scope, and not a substitute for observability. A broader glob changes what is caught; it does not make a skip visible. The two problems are independent; solving coverage alone leaves the next misplaced path as undetectable as the E57 incidents.

- **An unattended post-merge watcher that flips branch protection automatically** — eliminates the manual operator step. Rejected: a `PUT` to the branch-protection endpoint is a full-object replace; an unattended run with a malformed or incomplete payload silently drops existing required contexts and resets `required_linear_history` with no error and no reviewer. That is the same "an unattended enforcement-surface change went unnoticed" shape this plan exists to close. What is mechanized instead is the safety of the flip: the payload is derived from a prior GET, the pre-image is saved to disk, and a round-trip diff asserts that nothing but the one added context moved.
