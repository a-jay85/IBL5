---
description: Local pre-push hook that front-runs the CI ADR decision-trigger gate, catching missing-ADR triggers before the round-trip to CI.
last_verified: 2026-06-18
---

# ADR-0064: Local pre-push ADR decision-trigger gate

**Status:** Accepted
**Date:** 2026-06-18

## Context

The `bin/adr-check` decision-trigger gate runs only at CI (`.github/workflows/adr-required.yml`, on `pull_request`). Ad-hoc work that skips `/plan` — where `bin/check-plan` gate `[8]` would catch a missing ADR at plan-write time — reaches CI before anyone learns an ADR is required. PR #1117 added two ~70-line `bin/` scripts, skipped `/plan`, and only hit the missing-ADR failure after the push and a full CI run. We want a local surface that catches the same trigger at push time, before the round-trip to CI.

## Decision

Add a tracked pre-push hook (`bin/pre-push-adr-hook`) and an idempotent installer (`bin/install-git-hooks`) that copies a thin shim into the common `.git/hooks/pre-push`. The shim chains git-lfs (preserving LFS pushes) then runs the ADR gate, which pipes `git log origin/master..HEAD --format=%B` into `bin/adr-check --pr --bypass-from-stdin`. The gate hard-blocks (exit 1) on a trigger without a resolution; the user clears it with an ADR, a `<!-- no-adr: reason -->` commit-message marker, or `git push --no-verify`. It degrades-open (exit 0) when `origin/master` is absent. Enforced by `bin/pre-push-adr-hook` + `bin/install-git-hooks`; tested by `bin/test-adr-check`.

## Alternatives Considered

- **Warning-level pre-push hook** (mirror the `auto-commit-reminder` Stop hook) — Rejected because: a Stop hook surfaces in Claude's turn-end UI where it is read; pre-push output competes with git push spam and is scrolled past, reproducing the #1117 miss.
- **`git config core.hooksPath` to a tracked `.githooks/` dir** — Rejected because: it is all-or-nothing across the shared common git dir and would orphan the four working untracked hooks (git-lfs pre-push, pre-commit's codebase-map + check-docs, post-merge wt-cleanup, post-checkout) — turning "add one check" into "migrate the whole hook system".
- **A new `bin/adr-check --no-pr-body` mode** — Rejected because: `--pr --bypass-from-stdin` already composes (`fetchPrBody` reads STDIN before the mode check and already tolerates a missing PR), so no `bin/adr-check` change is needed and its CI behavior cannot regress.

## Consequences

- Positive: missing-ADR triggers are caught locally, before CI, for work that skips `/plan`.
- Positive: `bin/adr-check` is untouched (zero CI-behavior regression risk); the gate logic is tracked and tested.
- Negative: the hook is opt-in per machine (`bin/install-git-hooks` must be run), and `git push --no-verify` can bypass it — accepted, because the CI `adr-required.yml` gate remains the hard backstop.

## References

- `bin/pre-push-adr-hook` — the tracked hook logic (the `git log | adr-check --pr --bypass-from-stdin` composition + degrade-open).
- `bin/install-git-hooks` — the idempotent installer (common-dir target, git-lfs chaining, backup-once).
- `bin/adr-check` — the decision-trigger gate, reused unchanged.
- `.github/workflows/adr-required.yml` — the CI backstop this hook front-runs.
- `bin/check-plan` — gate `[8]`, the plan-write-time surface for the same triggers.
- `bin/test-adr-check` — the regression harness.
- `ibl5/docs/decisions/README.md` — the "When an ADR is Required" policy.
