---
description: ADR-0277. A CI gate, bin/check-gitignore-negated-data-files, fails when a JSB runtime data file at the ibl5/ root is git-tracked without an allowlist reason, so a deploy reset cannot restore a stale committed copy over live data
last_verified: 2026-11-21
---

# ADR-0277: Gate git-tracked JSB data files at the ibl5/ root

**Status:** Accepted
**Date:** 2026-11-21

## Context

The deploy job in `.github/workflows/main.yml` runs `git reset --hard origin/production` on the server. That command restores every tracked file to its committed copy. Ignored files survive it.

The runtime and manual uploads replace JSB data files (`.plr`, `.sch`, `.lge`, and 11 other extensions) at the `ibl5/` root. When one of those files is tracked, the next deploy puts the stale committed copy back. A `.gitignore` negation (`!`) or a `git add -f` can track one without anyone noticing. The retrospective class registry first recorded this class on 2026-08-10, from PR #1834. Its only defense was a prose row in `.claude/review-shared/_plan-verification.md`, and that row only fires for work that goes through `/plan`.

Three such files stay tracked on purpose. `ibl5/IBL5.lge` and `ibl5/IBL5.sch` are test fixtures. `ibl5/Olympics.sch` is the disk fallback that `ibl5/classes/Updater/JsbSourceResolver.php` reads when an uploaded archive has no `.sch` entry.

## Decision

`bin/check-gitignore-negated-data-files` lists the git index with one `git ls-files` call. Its `:(glob,icase)` pathspecs cover the 14 JSB extensions at the `ibl5/` root. The gate fails on any listed path missing from the `ALLOWLIST` inside the script. Every allowlist entry must carry a reason, and the gate exits 2 on an entry without one. Files under `ibl5/tests/` are out of scope.

CI runs the gate as a step in the `static-guards` job of `.github/workflows/tests.yml`, on every PR with no path filter. Its fixture harness `bin/test-check-gitignore-negated-data-files` runs in leg 0 of `.github/workflows/harness-legs.txt`.

## Alternatives Considered

- **Parse `.gitignore` for `!` negations.** Rejected because `git add -f` tracks a file with no negation line. The index is what a deploy reset restores.
- **Loop over every tracked `ibl5/` path in bash.** Rejected because a per-file loop is slow under bash 3.2, and `bin/test-check-phpunit-hygiene` records a bash 3.2 heap crash from one.
- **Run the gate from `.github/workflows/pr-meta-checks.yml`.** Rejected because that workflow's hygiene step sits behind a path filter. A forced add can land in any PR.
- **Extend `bin/check-phpunit-hygiene`.** Rejected because that gate reads test-file contents, and an index scan is a separate job.
- **Keep the prose row only.** Rejected because prose cannot stop a forced add made outside `/plan`.

## Consequences

- Positive: a newly tracked JSB data file at the `ibl5/` root fails CI before it can reach a deploy.
- Positive: each tracked exception carries its reason next to the path, in the script.
- Negative: the three allowlisted files still revert on deploy. A hand-uploaded newer `ibl5/Olympics.sch` would go back to the committed copy without a warning.
- Negative: the gate does not fail on an allowlist entry whose file is no longer tracked.
- Negative: one more `bin/check-*` gate and one more `bin/test-*` harness to maintain.

## References

- `bin/check-gitignore-negated-data-files`
- `bin/test-check-gitignore-negated-data-files`
- `.github/workflows/tests.yml`
- `.github/workflows/harness-legs.txt`
- `.github/workflows/main.yml`
- `ibl5/classes/Updater/JsbSourceResolver.php`
- `ibl5/docs/retrospective-class-registry.md`
- `.claude/review-shared/_plan-verification.md`
