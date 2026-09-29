---
description: The PHPStan baseline drift gate also compares against merge-base neon counts; the counts JSON is a growth allowance raised by PRs and lowered only by master.
last_verified: 2026-09-29
---

# ADR-0144: Baseline Drift Gate Compares Against the Merge-Base

**Status:** Accepted
**Date:** 2026-09-29

## Context

`ibl5/phpstan-baseline.neon`, `ibl5/phpstan-baseline-counts.json`, and `.claude/rules/codebase-map.md` were the top merge-conflict files across open PRs (measured 2026-09-28: 6, 5, and 5 of 31). The map was already rebuilt on master by `regen-codebase-map`, and only the pre-commit hook pulled it into PRs. The counts JSON was already rewritten on master by `update-baselines`, but `bin/regen-baselines` ran `check-baseline-drift --update` on every PR, and that rewrote the snapshot up and down. Every shrink PR therefore edited the JSON. Stopping shrink PRs from editing it would leave master's snapshot above master's real counts until the next sync. Under ADR-0018's snapshot-only rule, a PR based in that window could grow the baseline and still pass. The neon cannot move off the PR side: `ignore.unmatched` is on, so a fixing PR must shrink it to keep its own PHPStan green, and turning that reporting off would let stale entries absorb new errors.

## Decision

`ibl5/bin/check-baseline-drift` computes, per neon file and identifier, `ceiling = min(snap, baseNeon + max(0, snap - baseSnap))` and fails when the PR's count exceeds it. `snap` is the PR's counts JSON, `baseNeon` and `baseSnap` are the neon count and JSON value at the merge-base. Because `ceiling <= snap`, every input the ADR-0018 gate failed still fails: this change only tightens the gate. The counts JSON becomes a growth allowance. `--update` raises it only by what the PR's own growth needs and never lowers it, so shrink PRs leave it byte-identical. `--sync` (the old full rewrite) is called only by master's `update-baselines` job. The base comes from `--base=<ref>` in CI (with full history fetched), or locally from the branch's declared `iblBase` (else `origin/master`). An unresolvable base exits 2 in CI and warns loudly locally, where it falls back to the ADR-0018 snapshot rule. The pre-commit hook no longer regenerates or stages `codebase-map.md`. The two `[auto]` snapshot files are added to the `paths-ignore` lists of the behind-sweep and canary workflows.

## Alternatives Considered

- **Turn off `reportUnmatchedIgnoredErrors`.** Rejected: stale entries would stay in the neon and absorb new errors that share their message.
- **Regenerate the neon on master after merge.** Rejected: a fixing PR must shrink the neon itself to keep its own PHPStan green, so no conflicts are saved.
- **`.gitattributes` `merge=union` on the generated files.** Rejected: union merges interact badly with squash-rebase and leave duplicated or stale entries.
- **`fetch-depth: 2` and compare against `HEAD^1`.** Rejected: it silently assumes the checkout lands on the PR merge ref, and it compares against the wrong commit when it lands on the head SHA.

## Consequences

- Positive: shrink PRs stop conflicting on the counts JSON.
- Positive: two growth PRs that raise the same identifier can still conflict, which is correct, since each raise is a reviewed allowance.
- Negative: the `phpstan` job pays a full-history checkout.
- Negative: the neon stays a PR-side conflict source, and `bin/regen-baselines` remains the conflict resolver for it.

## References

- `ibl5/docs/decisions/0018-coverage-regression-and-baseline-drift-detection.md`
- `ibl5/bin/check-baseline-drift`
- `ibl5/classes/Maintenance/BaselineDriftChecker.php`
- `bin/regen-baselines`
- `.claude/rules/phpstan-baseline.md`
