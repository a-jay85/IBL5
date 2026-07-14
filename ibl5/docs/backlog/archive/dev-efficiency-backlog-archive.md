---
description: Historical archive: completed development-efficiency backlog entries, extracted from dev-efficiency-backlog.md.
last_verified: 2026-07-14
---

# Development-Efficiency Backlog — Archive

Read-only historical record of ✅ Implemented / 🚫 Declined entries. For OPEN items see ../dev-efficiency-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### E6 Diff-scoped PHPStan wrapper
**Location:** `ibl5/bin/analyse-diff`.
**What shipped:** `bin/analyse-diff` runs PHPStan only on `.php` files changed vs a base ref (default: `master`), routing them through `composer run analyse` / `composer run analyse:tests` so it inherits the exact `--memory-limit`/`--autoload-file` flags and honors baselines. Covers committed branch changes, staged/unstaged edits, and untracked new files. Full-project run remains the CI gate.
**Status (2026-07-14):** ✅ Implemented — shipped in PR #1362.

### E3 PHPStan result-cache in CI
**Location:** `.github/workflows/tests.yml` (the `phpstan` job).
**What shipped:** A `Cache PHPStan result cache` step (`actions/cache` v6.1.0) persists `ibl5/tmp` + `ibl5/tmp-tests` (the `phpstan.neon`/`phpstan-tests.neon` `tmpDir`s where PHPStan writes `resultCache.php`), keyed on the phpstan config files + `phpstan-rules/**`. PHPStan's own file-hash invalidation keeps results correct across restores. Only `tests.yml` runs `composer run analyse`/`analyse:tests`; `pr-meta-checks.yml`/`mutation.yml` don't invoke PHPStan.
**Status (2026-07-03):** ✅ Implemented — shipped in PR #1309 (predates the entry's own 2026-07-07 "verified" claim; the entry was stale).
