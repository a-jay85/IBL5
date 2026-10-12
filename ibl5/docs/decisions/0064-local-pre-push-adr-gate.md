---
description: Local pre-push hook that front-runs the CI ADR decision-trigger gate, catching missing-ADR triggers before the round-trip to CI.
last_verified: 2026-10-05
---

# ADR-0064: Local pre-push ADR decision-trigger gate

**Status:** Accepted
**Date:** 2026-06-18

## Context

The `bin/adr-check` decision-trigger gate runs only at CI (the `adr-check` step in `.github/workflows/pr-meta-checks.yml`, consolidated from the former `adr-required.yml`, on `pull_request`). Ad-hoc work that skips `/plan` — where `bin/check-plan` gate `[8]` would catch a missing ADR at plan-write time — reaches CI before anyone learns an ADR is required. PR #1117 added two ~70-line `bin/` scripts, skipped `/plan`, and only hit the missing-ADR failure after the push and a full CI run. We want a local surface that catches the same trigger at push time, before the round-trip to CI.

## Decision

Add a tracked pre-push hook (`bin/pre-push-adr-hook`) and an idempotent installer (`bin/install-git-hooks`) that copies a thin shim into the common `.git/hooks/pre-push`. The shim chains git-lfs (preserving LFS pushes) then runs the ADR gate, which pipes `git log origin/master..HEAD --format=%B` into `bin/adr-check --pr --bypass-from-stdin`. The gate hard-blocks (exit 1) on a trigger without a resolution; the user clears it with an ADR, a `<!-- no-adr: reason -->` commit-message marker, or `git push --no-verify`. It degrades-open (exit 0) when `origin/master` is absent. Enforced by `bin/pre-push-adr-hook` + `bin/install-git-hooks`; tested by `bin/test-adr-check`.

## Addendum — stacked base resolution and missing-origin/master block (2026-10-05) <!-- slop-ok -->

The decision above stands. The hook is still a tracked script behind an installer shim, opt-in per machine. It still hard-blocks an unresolved trigger, the same three escapes still clear it (an ADR, a `<!-- no-adr: reason -->` commit marker, `git push --no-verify`), and the CI `adr-check` step is still the backstop. The hook grew after 2026-06-18 in the ways below. The original sentences stay in place as history, and this section records what is true today.

**Commit range.** Original: the gate pipes `git log origin/master..HEAD --format=%B` into `bin/adr-check --pr --bypass-from-stdin`. Today: `bin/pre-push-adr-hook` pipes `git log "$BASE_REF..HEAD" --format=%B` into `bin/adr-check --pr --bypass-from-stdin --base="$BASE_REF"`. `BASE_REF` defaults to `origin/master` and changes only when the branch is stacked on another branch. The degrade-open sentence above is still accurate. When `origin/master` does not resolve, the hook exits 0 before any base resolution runs.

**Missing-origin/master block.** Added by PR #1460. When `origin/master` is not an ancestor of `HEAD` and no stacked parent resolves, the hook blocks the push with exit 1. The hook does no fetch of its own, since `bin/wt-new` syncs the branch when it creates the worktree. The block message offers three fixes: `git fetch origin master && git merge origin/master`, `bin/wt-rebase`, or `git config branch.<name>.iblBase <parent-branch>`.

**Stacked base resolution.** Added by PR #2111 under ADR-0117 (`ibl5/docs/decisions/0117-stacked-pr-base-resolution.md`). Only on that slow path, the hook sources `bin/lib/branch-base.sh` and calls `branch_base_resolve`. Layer 1 reads the `branch.<name>.iblBase` git config that `bin/wt-new --base` writes. Layer 2 asks `gh pr view` with a 5-second timeout and is skipped when `BRANCH_BASE_OFFLINE=1`. Layer 3 blocks with exit 1. The hook fails closed here because CI is also base-parameterized, so a wrong base would skip the check on both sides. When a parent resolves, `BASE_REF` becomes that parent. The hook checks only the immediate base, so freshness against `origin/master` becomes the stack root's obligation, enforced when the root itself is pushed.

**`bin/adr-check` changed.** The Consequences bullet that calls `bin/adr-check` untouched, and the References line that says it is reused unchanged, describe the June 2026 state. `bin/adr-check` gained a `--base=<ref>` option with the stacked-base work, and `.github/workflows/pr-meta-checks.yml` passes `github.event.pull_request.base.sha` to it. The hook passes `--base="$BASE_REF"` so the local and CI runs diff against the same base. The rejected `--no-pr-body` mode was never added, and the hook still relies on the `--pr --bypass-from-stdin` composition.

**Exit codes.** Exit 0 means pass: no trigger, an ADR present, a bypass marker, or `origin/master` absent. Exit 1 means a trigger without a resolution, or a branch that lacks `origin/master` with no resolvable stacked base. PR #2490 added `--help` (and `-h`), which prints the usage and this exit-code contract.

**Downstream consumer.** The post-plan harness drafts an ADR when this hook denies a push (ADR-0135, `ibl5/docs/decisions/0135-harness-adr-draft-on-denial.md`). It reads the exit-1 contract and changes nothing in the hook.

## Alternatives Considered

- **Warning-level pre-push hook** — Rejected because: pre-push output competes with git push spam and is scrolled past, reproducing the #1117 miss.
- **`git config core.hooksPath` to a tracked `.githooks/` dir** — Rejected because: it is all-or-nothing across the shared common git dir and would orphan the four working untracked hooks (git-lfs pre-push, pre-commit's codebase-map + check-docs, post-merge wt-cleanup, post-checkout) — turning "add one check" into "migrate the whole hook system".
- **A new `bin/adr-check --no-pr-body` mode** — Rejected because: `--pr --bypass-from-stdin` already composes (`fetchPrBody` reads STDIN before the mode check and already tolerates a missing PR), so no `bin/adr-check` change is needed and its CI behavior cannot regress.

## Consequences

- Positive: missing-ADR triggers are caught locally, before CI, for work that skips `/plan`.
- Positive: `bin/adr-check` is untouched (zero CI-behavior regression risk); the gate logic is tracked and tested.
- Negative: the hook is opt-in per machine (`bin/install-git-hooks` must be run), and `git push --no-verify` can bypass it — accepted, because the CI `adr-check` gate (in `pr-meta-checks.yml`) remains the hard backstop.

## References

- `bin/pre-push-adr-hook` — the tracked hook logic (the `git log | adr-check --pr --bypass-from-stdin` composition + degrade-open).
- `bin/install-git-hooks` — the idempotent installer (common-dir target, git-lfs chaining, backup-once).
- `bin/adr-check` — the decision-trigger gate, reused unchanged.
- `.github/workflows/pr-meta-checks.yml` — the CI backstop this hook front-runs (the `adr-check` step, consolidated from the former `adr-required.yml`).
- `bin/check-plan` — gate `[8]`, the plan-write-time surface for the same triggers.
- `bin/test-adr-check` — the regression harness.
- `ibl5/docs/decisions/README.md` — the "When an ADR is Required" policy.
