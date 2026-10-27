---
description: One shared, offline resolver (bin/lib/default-branch) decides the remote default branch name from origin/HEAD, then main, then master; every bash, Python and PHP caller uses it, with no env override and fail-closed on nonzero exit.
last_verified: 2026-10-27
owner: ajaynicolas
---

# ADR-0219: One shared resolver for the remote default branch name

**Status:** Accepted
**Date:** 2026-10-27
**Deciders:** a-jay85

## Context

The repository plans to rename its default branch from `master` to `main`. Hooks, gates, worktree scripts, the post-plan harness and a few PHP classes hard-code `master` or `origin/master` in roughly 400 files. Each site that resolves the name on its own would become a second implementation to keep in step, and an env-var override would hand every gate an escape hatch (the same reasoning as the `BRANCH_BASE_OFFLINE` comment in `bin/lib/branch-base.sh`). The rename must be safe to perform at any time after the agnostic program merges, so the resolver must give the right answer before and after the rename with no code change.

## Decision

- `bin/lib/default-branch` is the only implementation. It resolves, in order: `git symbolic-ref --short refs/remotes/origin/HEAD` with the `origin/` prefix stripped; else the first of `main`, `master` that exists as `refs/remotes/origin/<name>`; else exit 1 with one line on stderr. A dangling `origin/HEAD` (its target passes the allowlist but no longer exists, the state right after a rename and prune before `git remote set-head origin -a`) falls through to the probe. It never fetches and never reads the network.
- Bash callers source the file and call `default_branch_name` / `default_branch_ref`. Python and PHP callers exec the same file as a CLI through a subprocess, cache the answer per process, and treat a nonzero exit as a hard failure. No second implementation exists in any language.
- There is no environment-variable override. Tests build scratch repositories instead.
- Branch names reach git only after an allowlist check copied from `_branch_base_valid_name`, so a hostile `origin/HEAD` target or repo-dir value is never passed to git as an option.
- A gate that cannot resolve the default branch fails closed (deny or exit nonzero). It never falls back to a literal name.

## Consequences

- One file to change when the resolution policy changes (for example dropping `master` from the fallback list in Phase 3 of the rename).
- Callers pay one `git symbolic-ref` per process (bash callers that source it pay one fork per call of the function; non-shell callers pay one subprocess per process).
- Out-of-repo hooks that may run in a checkout without this file keep a literal `{master, main}` union for deny sites only, as recorded in the program's shared context.

## Alternatives rejected

- Extending `bin/lib/branch-base.sh` with the lookup: that file answers "what is this stacked branch's recorded base", a different question, and it is sourced-only, so Python and PHP could not reuse it.
- An env var (`IBL5_DEFAULT_BRANCH`) for tests and for the rename window: a reviewer would have to prove it is never set in CI or in a hook; scratch repos cost nothing and prove the real path.
- Per-language implementations (a Python helper in the harness, a PHP helper in `ibl5/classes`): three places to drift during the rename window.
