---
description: Historical archive: completed development-efficiency backlog entries, extracted from dev-efficiency-backlog.md.
last_verified: 2026-08-15
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
