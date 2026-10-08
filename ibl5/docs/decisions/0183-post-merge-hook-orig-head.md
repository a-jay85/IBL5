---
description: Moves the main checkout's post-merge automation into the version-controlled bin/post-merge-hook, installed by a shim, and measures changes from ORIG_HEAD to HEAD so multi-commit pulls trigger migrate, warm-cache, and IBL6 rebuilds.
last_verified: 2026-10-07
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0183: Version-controlled post-merge hook with ORIG_HEAD change detection

**Status:** Accepted
**Date:** 2026-10-07
**Deciders:** post-plan harness (auto-draft)

## Context

After a pull into master, the main checkout runs housekeeping: worktree cleanup, a prod seed refresh, database migrations, cache warming, and an IBL6 container rebuild. That logic lived in a hand-written post-merge hook inside the common git hooks dir. It had no tests and no review, and it decided what changed by diffing `HEAD~1` against `HEAD`. A pull that brought in several commits only saw the newest one. When a migration or PHP change sat in an older commit of the pull, migrate and warm-cache were skipped and the local stack drifted from master.

`bin/adr-check` flagged two new developer tools in this PR: `bin/post-merge-hook` and `bin/test-post-merge-hook`.

## Decision

The post-merge body lives at `bin/post-merge-hook`. `bin/install-git-hooks` writes a small shim into the common hooks dir. The shim runs the CSS auto-heal, chains `git lfs post-merge`, and then execs the body. This matches the existing pre-commit and pre-push shims. A pre-existing hook without the shim sentinel is backed up once to `post-merge.pre-shim.bak`.

The body exits early off master or when `SKIP_WT_CLEANUP` is set. It reads the `ORIG_HEAD` ref, which git sets to the pre-merge tip on every pull and merge, fast-forwards included. It diffs `ORIG_HEAD` against `HEAD` to set three flags: migrations changed, PHP changed, IBL6 changed. When `ORIG_HEAD` is missing or equals `HEAD`, all three flags stay false. Each expensive step runs only when its flag is true. The prod seed refresh has no git input, so it is throttled to once per 5 minutes. Logs go to the XDG cache dir with size-based rotation.

`IBL5_POST_MERGE_DRY_RUN=1` prints the three flags and exits before any side effect. `bin/test-post-merge-hook` uses it to pin the change detection against scratch repos, and it also checks the installer's shim, backup, and idempotence. The test runs in CI from `.github/workflows/tests.yml`.

## Alternatives Considered

- Fix the diff range in the existing local hook. Rejected because the hook would stay untracked. Each machine would need a manual edit, and CI could not test it.
- Diff from `HEAD@{1}` in the reflog. Rejected because reflog entries depend on local config and history. `ORIG_HEAD` is the ref git defines for exactly this pre-merge tip.
- Run every step on every pull. Rejected because migrate, warm-cache, and an IBL6 image build are slow. Gating on real input changes keeps a docs-only pull cheap.

## Consequences

- Positive: a pull of many commits now triggers migrate, warm-cache, and the IBL6 rebuild whenever any commit in the range touched their inputs.
- Positive: the hook body gets code review and a CI regression test, and every machine picks up changes on the next pull.
- Negative: each checkout must rerun `bin/install-git-hooks` once to get the new shim. Until then the old local hook keeps running.
- Negative: the dry-run switch is an extra code path that exists only for tests.

## References

- `bin/post-merge-hook`
- `bin/test-post-merge-hook`
- `bin/install-git-hooks`
- `bin/pre-commit-hook`
- `bin/pre-push-adr-hook`
- `bin/rebuild-css-if-source-changed`
- `bin/cleanup`
- `ibl5/bin/migrate`
- `ibl5/bin/warm-cache`
- `.github/workflows/tests.yml`
