---
description: Historical archive: completed development-efficiency backlog entries, extracted from dev-efficiency-backlog.md.
last_verified: 2026-08-16
---

# Development-Efficiency Backlog — Archive

Read-only historical record of ✅ Implemented / 🚫 Declined entries. For OPEN items see ../dev-efficiency-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### E2 Dependabot grouping
**Location:** `.github/dependabot.yml`.
**What shipped:** Added `groups:` blocks to all 5 ecosystems (github-actions, composer, bun, docker, npm@IBLbot) batching minor/patch bumps into one weekly PR per ecosystem; majors stay individual so a breaking bump is never entangled with routine ones. TypeScript `semver-major` ignore entry in IBLbot block preserved verbatim.
**Status (2026-08-14):** ✅ Implemented — shipped in PR #1873.

### E6 Diff-scoped PHPStan wrapper
**Location:** `ibl5/bin/analyse-diff`.
**What shipped:** `bin/analyse-diff` runs PHPStan only on `.php` files changed vs a base ref (default: `master`), routing them through `composer run analyse` / `composer run analyse:tests` so it inherits the exact `--memory-limit`/`--autoload-file` flags and honors baselines. Covers committed branch changes, staged/unstaged edits, and untracked new files. Full-project run remains the CI gate.
**Status (2026-07-14):** ✅ Implemented — shipped in PR #1362.

### E3 PHPStan result-cache in CI
**Location:** `.github/workflows/tests.yml` (the `phpstan` job).
**What shipped:** A `Cache PHPStan result cache` step (`actions/cache` v6.1.0) persists `ibl5/tmp` + `ibl5/tmp-tests` (the `phpstan.neon`/`phpstan-tests.neon` `tmpDir`s where PHPStan writes `resultCache.php`), keyed on the phpstan config files + `phpstan-rules/**`. PHPStan's own file-hash invalidation keeps results correct across restores. Only `tests.yml` runs `composer run analyse`/`analyse:tests`; `pr-meta-checks.yml`/`mutation.yml` don't invoke PHPStan.
**Status (2026-07-03):** ✅ Implemented — shipped in PR #1309 (predates the entry's own 2026-07-07 "verified" claim; the entry was stale).

### E9 Meta-tooling growth bar
**Location:** Plan: `$HOME/claude-plans/meta-tooling-bar.md` (queued) — extend-before-add rule + quarterly cull.
**Problem:** ~27 of 101 `bin/` scripts exist to test the other scripts; the gate layer itself has had bugs. Nothing pushes back on meta-tooling growth.
**Suggested direction:** Extend-before-add policy gate + quarterly cull process.
**Risk if untouched:** Unbounded meta-tooling growth; gate layer debt accumulates.
**Status (2026-07-09):** ✅ Implemented — PR #1387 (`meta-tooling-bar`): extend-before-add rule + quarterly cull automation shipped.

### E11 In-PR pre-baked image build
**Location:** Plan: `$HOME/claude-plans/in-pr-prebaked-image-build.md` (queued). Today only `.github/workflows/cache-dependencies.yml` builds the image, on schedule/push — never in-PR.
**Problem:** A PR changing the Dockerfile or composer deps is E2E-tested against the *previous* master image; the mismatch surfaces only after merge.
**Suggested direction:** Build the Docker image in-PR for PRs touching the Dockerfile or composer deps (paths-filtered so normal PRs are unaffected).
**Risk if untouched:** Dockerfile/dep changes are validated against a stale image; mismatch only surfaces post-merge.
**Status (2026-07-09):** ✅ Implemented — PR #1386 (`in-pr-prebaked-image-build`): in-PR image build wired via paths-filter; normal PRs unaffected.

### E12 `bin/wt-new` fails with misleading error when invoked from inside a worktree
**Location:** `bin/wt-new` (`REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"` and the base-branch sync block below it).
**Problem:** `bin/wt-new` computed `REPO_ROOT` from the script's own path and then ran `git -C "$REPO_ROOT" merge --ff-only "origin/$BASE_BRANCH"` to sync the base branch before branching. When the script was invoked by its worktree-relative path (e.g. `bin/wt-new foo` from `IBL5-worktrees/<slug>/`), `REPO_ROOT` resolved to that worktree rather than the main checkout. The `--ff-only` then targeted the worktree's feature branch, which had diverged from `origin/master`, and the script aborted with `fatal: Not possible to fast-forward, aborting.` before creating anything. Observed 2026-07-29 running `bin/wt-new matrix-fence-strip` from the `critical-files-parser-unification` worktree. Failure was fail-safe (aborted, created nothing) but the error message pointed at the fast-forward constraint and gave no hint that the real cause was the wrong `REPO_ROOT`. `.claude/rules/workflow-continuity.md` documents the bare `bin/wt-new <slug>` form as the standard invocation — exactly the shape that failed from a worktree.
**What shipped:** `bin/wt-new` resolves `MAIN_CHECKOUT="$(resolve_canonical_root "$REPO_ROOT")"` (existing helper in `bin/lib/git-helpers.sh`, already sourced) and runs the fetch, behind-count, `merge --ff-only`, and `worktree add` against it, so the bare `bin/wt-new <slug>` form works from inside a worktree. The same fix repoints `MAIN_IBL5` at the main checkout, so a new worktree's `.env`/`vendor`/`node_modules` symlinks no longer chain through the invoking worktree and dangle when it is torn down. `REPO_ROOT` stays as the script's own on-disk location (the `source` line needs it), and `git -C "$WT_ROOT" update-index` is deliberately untouched. Neither `git worktree list --porcelain` parsing nor `rev-parse --git-common-dir` (the two directions the entry originally suggested) was needed. Smoke-tested from the main checkout and from inside a worktree via relative path, absolute path, and the `--base` stacked form, plus an invalid-base negative path confirming the fail-safe abort still creates nothing.
**Status (2026-08-16):** ✅ Implemented — PR #1896 (`dev-e12-wt-new-main-worktree-resolution`). (discovered 2026-07-29 during matrix-fence-strip)
