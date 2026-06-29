---
description: Adopt GitHub native merge queue on master; required checks gate PR entry and the merge group; human-signoff preserved at entry and re-checked on merge_group; eager rebase retired in a follow-up.
last_verified: 2026-06-28
---

# ADR-0072: Adopt GitHub native merge queue

**Status:** Accepted
**Date:** 2026-06-28
**Deciders:** a-jay85

## Context

`.github/workflows/rebase-prs.yml` eagerly rebases every open PR on each `master` push, causing CI storms and serialized human re-merges whenever several PRs are in flight. GitHub's native merge queue instead does just-in-time rebase and re-runs the required checks **at merge time** on a temporary branch (base + PRs ahead + this PR), batching merges and validating the exact tree that will land. The PR-1 fast-canary already supplies cheap early conflict/semantic-breakage signal, so the queue's heavier merge-time validation is not the only feedback a contributor sees.

## Decision

Adopt the merge queue on `master`. The three required contexts (`Tests and Analysis`, `E2E Tests`, `human-signoff`) gate **both** PR entry **and** the `merge_group`; each required workflow triggers on `pull_request` **and** `merge_group` (non-required workflows are excluded to avoid needless merge-group runs). The heavy suites **run fully** on `merge_group` — skip-as-pass is rejected because GitHub's handling of a skipped required check on the merge group is undocumented/inconsistent. `human-signoff` is preserved **at entry** (a red PR cannot be added to the queue) and **re-checked** on `merge_group` per-PR via `bin/lib/human-signoff-classifier.sh` (sourced by the workflow, regression-guarded by `bin/test-human-signoff-classifier`), so the ADR-0062 backstop stays intact with zero security regression. Enablement is a settings change performed via `ibl5/docs/runbooks/merge-queue-enablement.md` **after** this PR merges (the workflow edits are inert while the queue is off). `.github/workflows/rebase-prs.yml` is retired in a follow-up PR once the queue is proven live.

## Alternatives Considered

- **Keep eager rebase-all (`rebase-prs.yml`)** — rebase every open PR on each master push. Rejected because: CI waste + serialized human re-merges, the exact pain this ADR removes.
- **Skip-as-pass on `merge_group`** — let required checks report skipped/neutral on the merge group. Rejected because: GitHub's skipped-required-check handling on the merge group is undocumented/inconsistent, so the suites must run fully.
- **Enable the queue and retire `rebase-prs.yml` in one PR** — Rejected because: rollback needs `rebase-prs.yml` as the fallback until the queue is proven, so it is retired only after live confirmation (D5).
- **`head_ref` / commit-SHA enumeration for the merge_group verdict** — Rejected because: `head_ref` names only the last batched PR and the queue rebases PR commits to new SHAs, so both miss PRs; the verdict instead parses `(#N)` from the merge-group commit subjects (D2).
- **Fail-closed on an empty merge_group enumeration** — Rejected because: a wrong `(#N)` format assumption would stall every merge; entry remains the authoritative gate, so an empty parse is a vacuous pass (D3), with the runbook turning the assumption into a checked one.

## Consequences

- Positive: no eager-rebase CI storms; just-in-time rebase only at merge time; batched merges.
- Positive: the human-signoff floor is preserved at entry and re-checked on the merge group — zero security regression versus the current required-check floor.
- Negative: added merge-time validation latency, and a flaky required check can stall the queue (mitigated by the PR-1 canary for early signal and the rollback runbook).
- Negative: the empty-enumeration vacuous-pass (D3) trades a narrow mid-queue label-removal window for not bricking the queue — `ibl5/docs/runbooks/merge-queue-enablement.md` Step 3 converts the `(#N)` assumption into a checked one.
- Negative: the queue/required-check settings live outside the repo (drift risk); the runbook is the source of truth for that configuration.

## References

- `.github/workflows/human-signoff.yml` — entry + merge_group sign-off gate, sources the classifier lib.
- `bin/lib/human-signoff-classifier.sh` — shared sourced classifier + merge-group PR resolver.
- `bin/test-human-signoff-classifier` — regression harness exercising both resolution paths under production `set -euo pipefail`.
- `.github/workflows/tests.yml` — `merge_group` trigger + dorny outputs forced true + the new harness step.
- `.github/workflows/e2e-tests.yml` — `merge_group` trigger + `src` forced true.
- `ibl5/docs/runbooks/merge-queue-enablement.md` — operator enable/verify/rollback sequence.
- `.github/workflows/rebase-prs.yml` — eager-rebase fallback, retired in the follow-up PR after live queue confirmation.
- Relates to (does not supersede) `ibl5/docs/decisions/0062-human-signoff-gate-for-feature-prs.md` and `ibl5/docs/decisions/0067-unified-deployment-funnel.md`.
