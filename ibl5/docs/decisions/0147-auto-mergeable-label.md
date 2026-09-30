---
description: A workflow mirrors "armed and signoff-cleared" onto each open PR as the advisory `auto-mergeable` label, reusing the human-signoff classifier lib.
last_verified: 2026-09-29
owner: ajaynicolas
---

# ADR-0147: Advisory `auto-mergeable` PR Label

**Status:** Accepted
**Date:** 2026-09-29
**Deciders:** ajaynicolas

## Context

A PR merges on its own only when GitHub auto-merge is armed and the human-signoff gate (ADR-0062) is cleared. The PR list shows neither fact. An armed `feat:` PR without `human-approved` looks the same as one that will merge once CI is green, but it will wait forever. Telling them apart meant opening each PR and reading its title and labels.

## Decision

`.github/workflows/auto-mergeable-label.yml` keeps the `auto-mergeable` label in sync on every PR to `master`. The label is present when auto-merge is armed and `hs_pr_cleared` from `bin/lib/human-signoff-classifier.sh` passes. It is removed when either stops holding. The workflow sources the same lib the human-signoff gate uses, so the two cannot drift. The label is advisory: no gate, hook, or script reads it. The sync step carries `continue-on-error: true`, so a label API error cannot add a red check that `bin/lib/pr-armable.sh` would read as a CI failure. Labels applied with `GITHUB_TOKEN` start no new workflow runs, so the toggle cannot loop.

## Alternatives Considered

- Compute it in `bin/pr-triage` only. Rejected because it helps only the local CLI and leaves the GitHub PR list blind.
- Copy the feat/approved test into the workflow. Rejected because a second copy of the classifier can drift from the gate.
- Make the label a gate input. Rejected because any collaborator can toggle a label, so it is not a trustworthy signal.

## Consequences

- Positive: the PR list shows which PRs will merge on their own.
- Negative: one short workflow run on each PR event, including label edits.
- Negative: a merged PR keeps whatever label it had at merge time.

## References

- `.github/workflows/auto-mergeable-label.yml`
- `bin/lib/human-signoff-classifier.sh`
- `bin/lib/pr-armable.sh`
- ADR-0062 (human-signoff gate)
