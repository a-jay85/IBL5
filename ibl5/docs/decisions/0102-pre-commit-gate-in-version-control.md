---
description: Moves the git pre-commit gate body out of the untracked common hooks dir into bin/pre-commit-hook, installed via a fail-closed shim; amends the untracked-hook enumerations in ADR-0064 and ADR-0075.
last_verified: 2026-08-14
---

# ADR-0102: Version-control the pre-commit gate body

**Status:** Accepted
**Date:** 2026-08-14

## Context

Four commit-time gates lived in an untracked `pre-commit` hook in the shared common git dir: codebase-map regeneration, `gofmt` on staged Go files, `bin/check-docs`, and `bin/check-rules-byte-budget`. Being untracked made the hook invisible to review, absent from a fresh clone, and awkward to edit from an agent session confined to a worktree — the hook has no per-worktree copy, so every edit targets a path outside the tree being worked in.

That last property turned a real fix into stranded local state. PR #1879 taught `bin/check-docs --since` to see uncommitted edits and added a matching on-touch check to the pre-commit hook, so a missing `last_verified` bump is caught at commit time rather than at CI (the failure mode that let PR #1878 merge a stale doc). The `bin/check-docs` half shipped; the hook half existed on exactly one machine and would never have reached anyone else.

## Decision

Move the hook body to `bin/pre-commit-hook`, tracked and executable, with the four gate blocks unchanged. `bin/install-git-hooks` writes a thin `pre-commit` shim into the common hooks dir using the pattern it already uses for pre-push: a sentinel comment (`# IBL5-PRECOMMIT-HOOK`), a backup-once of any pre-existing non-shim hook to `pre-commit.pre-shim.bak`, and a heredoc write plus `chmod 0755`.

The shim resolves `REPO_ROOT` per invocation via `git rev-parse --show-toplevel`, so one install serves every worktree and each gets its own branch's copy of the gate. It **fails closed**: if `$REPO_ROOT/bin/pre-commit-hook` is missing or non-executable it prints why and exits 1 rather than committing ungated.

## Alternatives Considered

- **Fail open when `bin/pre-commit-hook` is absent** (warn and exit 0) — Rejected because: a silently skipped gate is the precise failure class this change exists to close, and it is invisible by construction. A blocked commit is loud and has an obvious escape (`--no-verify`); a skipped one is discovered at CI or not at all. The pre-push shim already fails closed the same way — `exec` of a missing `bin/pre-push-adr-hook` exits non-zero — so this is the established behavior, not a new stance.
- **`git config core.hooksPath` to a tracked hooks dir** — Rejected for the same reason ADR-0064 and ADR-0075 rejected it: it is all-or-nothing across the shared common git dir and would orphan the untracked hooks not being migrated (the git-lfs `pre-push` chain, `post-merge` wt-cleanup, `post-checkout`). Shimming one hook at a time stays surgical.
- **Leave the hook untracked and document it** — Rejected because: a documented norm that every developer must hand-copy is the "manual discipline" non-answer; the gate would still be absent from a fresh clone.

## Consequences

- Positive: the four gates are reviewable, testable, travel with a clone, and are editable from any worktree.
- Positive: the PR #1879 on-touch commit-time check stops being machine-local.
- Negative (bootstrap window): once the shim is installed, any checkout whose tree lacks `bin/pre-commit-hook` — a branch based on master from before this merges, or a `git checkout` of an older commit — fail-closes every commit until it rebases. The escape is `git commit --no-verify`. Accepted: the window is transient, the message names the fix, and the alternative is a gate that silently disappears.
- Negative: `bin/install-git-hooks` must be re-run once after this merges, on each machine, for the shim to replace the untracked hook. Still opt-in per machine, unchanged from ADR-0064.
- **Amends ADR-0064 and ADR-0075.** Both reject `core.hooksPath` partly on the grounds that it would orphan a set of untracked hooks that they enumerate as including "pre-commit's codebase-map + check-docs". That enumeration is now wrong — pre-commit is tracked. Both decisions stand: the remaining untracked hooks (`post-merge` wt-cleanup, `post-checkout`, the git-lfs `pre-push` chain) still carry the argument.

## References

- `bin/pre-commit-hook` — the tracked gate body (codebase-map regen, gofmt, check-docs, rules byte budget).
- `bin/install-git-hooks` — the idempotent installer (common-dir target, sentinel, backup-once, fail-closed shim).
- `bin/check-docs` — the doc gate the hook runs in both `--no-staleness` and `--since` modes.
- `bin/check-rules-byte-budget` — the always-loaded rules byte cap.
- `.claude/rules/doc-freshness.md` — the on-touch bump rule the `--since` pass enforces.
- `ibl5/docs/decisions/0064-local-pre-push-adr-gate.md` — the pre-push shim pattern this copies; its untracked-hook enumeration is amended here.
- `ibl5/docs/decisions/0075-css-auto-heal-via-git-hooks.md` — same enumeration, same amendment.
