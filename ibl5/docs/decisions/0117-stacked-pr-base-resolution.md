---
description: Layered base-ref resolution for the ADR gate so stacked-PR branches are not blocked by a hardcoded origin/master assumption.
last_verified: 2026-09-05
---

# ADR-0117: Stacked-PR-aware base resolution for the ADR gate

**Status:** Accepted
**Date:** 2026-09-05

## Context

`bin/pre-push-adr-hook` and `bin/adr-check` both hardcoded `origin/master` as the diff
base when checking whether a branch touched a decision-trigger surface. For branches
sitting on top of another PR (stacked PRs), `origin/master` is not an ancestor of HEAD,
so the hook incorrectly classified the entire parent branch's diff as new additions and
blocked the push. The workaround was `git push --no-verify`, which bypasses the hook
entirely and skips any legitimate ADR check. This was observed live on PR #1957
(`authz-verdict-refactor-1b-trading-reject-service`).

## Decision

Introduce `bin/lib/branch-base.sh`, a sourced helper that resolves a branch's real base
using a three-layer fail-closed strategy:

1. **`branch.<name>.iblBase` git config key** (offline; written by `bin/wt-new` at
   worktree creation time via `--base`). Validated: name allowlisted, `origin/<name>`
   must resolve, and must be an ancestor of HEAD.
2. **`gh pr view` with a 5-second hard timeout** (network; skipped when
   `BRANCH_BASE_OFFLINE=1`). Same validation applied to the returned name.
3. **BLOCK** — unresolvable base fails closed rather than falling back to
   `origin/master`, because CI is now also base-parameterized; a silent wrong base
   would skip both sides at once.

`bin/pre-push-adr-hook` sources `bin/lib/branch-base.sh` only on the slow path (when
`origin/master` is not already an ancestor of HEAD), then passes the resolved ref to
`adr-check` via `--base=<ref>`. The CI step passes `github.event.pull_request.base.sha`
(a SHA, not a ref name, to eliminate shell-metacharacter injection) to `adr-check
--base=`. `bin/wt-new` writes `branch.<name>.iblBase` immediately after worktree
creation. `bin/wt-rebase` reads it via `branch_base_recorded` (no ancestry check, since
the branch is intentionally behind its base when a rebase is needed).

Enforcement: `bin/pre-push-adr-hook`, `bin/adr-check --base=`,
`bin/lib/branch-base.sh`, `bin/wt-new`, `.github/workflows/pr-meta-checks.yml`.

## Alternatives Considered

- **Hardcode `origin/master` always** — the status quo. Rejected because stacked-PR
  branches must bypass with `--no-verify`, defeating the gate entirely.
- **`git merge-base --fork-point`** — attempts to find the point where the branch
  diverged from upstream. Rejected because this repo uses squash-merge linear history;
  fork-point is unreliable and returns nothing after the parent PR merges.
- **Degrade-open (fall back to `origin/master` when unresolvable)** — preserves the
  status quo failure mode for offline stacked branches instead of blocking. Rejected
  because CI is now also base-parameterized; a misconfigured branch would silently skip
  both the pre-push hook and the CI check with the wrong base, leaving the gate open on
  both sides simultaneously.
- **`BRANCH_BASE_OFFLINE=1` as an escape hatch** — an env var to force the fallback.
  Rejected as an escape-hatch role; the var is deliberately asymmetric: it can only
  turn a PASS into a BLOCK (removes the network layer), never open what would otherwise
  be closed.

## Consequences

- **Positive:** stacked PR branches with a recorded or discoverable base pass the
  pre-push hook without `--no-verify`; the ADR check runs against the correct range.
- **Positive:** CI `adr-check` receives a stable SHA as its base rather than a mutable
  ref name, eliminating a class of race conditions and ref-injection vectors.
- **Positive:** `bin/wt-new` always records the base at creation time, so the config
  layer is populated automatically for all new worktrees.
- **Negative:** a branch with no `iblBase` config, no network, and `BRANCH_BASE_OFFLINE=1`
  gets a hard block rather than a degraded pass. The correct fix is to set the config key,
  not to weaken the gate.

## References

- `bin/lib/branch-base.sh` — the layered resolver
- `bin/pre-push-adr-hook` — pre-push hook that sources the resolver
- `bin/adr-check` — decision-trigger gate; accepts `--base=<ref>` override
- `bin/wt-new` — writes `branch.<name>.iblBase` at worktree creation
- `bin/wt-rebase` — uses `branch_base_recorded` (no ancestry check) when rebasing
- `.github/workflows/pr-meta-checks.yml` — passes `github.event.pull_request.base.sha`
