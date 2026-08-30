---
description: Prevent duplicate ADR and migration numbers at allocation time and detect them at PR time, rather than renumbering the legacy duplicates.
last_verified: 2026-08-29
---

# ADR-0110: ADR and migration numbering collision prevention

**Status:** Accepted
**Date:** 2026-08-29

## Context

ADR and migration numbers were allocated by scanning the local filesystem only — `bin/next-adr` looked at the current worktree plus the canonical checkout, `bin/next-migration` did the same over `ibl5/migrations/`. Neither consulted branches that had already claimed a number. When two branches claimed the same one, git merged both files cleanly: no conflict, no CI failure, nothing to notice. The corpus already carries seven duplicated ADR numbers (`0032`, `0062`, `0065`, `0069`, `0079`, `0081`, `0085`) and two duplicated migration numbers (`007`, `046`) from exactly this. The failure was live when this was written: migration `169` was claimed three times across unmerged branches while `master`'s max was `168`. Compounding it, every new ADR appends a row to the same spot in `ibl5/docs/decisions/README.md`, so stacked ADR branches conflicted on rebase as a matter of routine — and hand-resolving that conflict is itself a path to dropping or reusing a number.

## Decision

Defend in three places. **Prevent at allocation:** `scan_origin_refs` and `scan_worktrees` in `bin/lib/git-helpers.sh` enumerate filenames on every `refs/remotes/origin/*` ref and in every registered worktree, and both allocators fold those maxima in. Both helpers are strictly offline — `git for-each-ref` and `git ls-tree` read objects already in `.git`. **Remove the friction that causes hand-editing:** `.gitattributes` gives `ibl5/docs/decisions/README.md` the `merge=union` driver, so concurrent index appends rebase cleanly. **Detect at PR time:** `bin/check-numbering` fails a PR that introduces a duplicate number — within its own diff, or against the base tree — and fails when the index table itself carries a duplicated row. It is a hard gate in `pr-meta-checks.yml` with no bypass marker.

Two predicates that are easy to conflate stay distinct. The **gate's collision key keeps the letter suffix** (`044b` is an amendment to `044`, not a collision with it); the **allocators' max extraction strips it** (`044b` bumps the next number to `045`). Keying the gate on the stripped number would report five duplicate migration keys instead of two and fail on `master` today.

The gate compares against the **base tree only** and never calls the two scan helpers. A CI runner has no sibling worktrees, and letting the verdict depend on other people's in-flight branches would make a green run turn red with no change to the PR. Allocation is advisory and should be maximally informed; a gate must be deterministic.

## Alternatives Considered

- **Renumber the nine legacy duplicates** — make the corpus clean so no allowlist is needed. Rejected because: roughly 289 citations across ADRs, `.claude/rules`, docs and PR bodies reference these numbers, so renumbering breaks every one of them. They are grandfathered instead, and the allowlist is scoped to the `--all` whole-tree audit only, so a PR adding a *third* `0062-*.md` still fails.
- **Skip `merge=union` to keep the rebase conflict visible** — a conflict is at least something a human notices. Rejected because: the conflict fires on the common case (every concurrent ADR append, several a week) while union's proven corruption — a status edit racing an append yields a rebase-clean file with a duplicated row — fires only on the rare case, and is now mechanically detected by the gate's third check. Trading a frequent visible cost for a rare *detected* one is a net reduction.
- **Generate the ADR index table from the ADR files** — remove the append conflict entirely. Rejected because: the table is curated (23 rows chosen from 114 ADRs) and generation would destroy that curation.
- **Fold the check into `bin/check-docs`, or split it into two gates** — reuse an existing host, or one script per directory. Rejected because: `bin/check-docs` is PHP, whole-tree, and owns ADR *content* integrity, while this gate is bash, diff-scoped, and spans `ibl5/migrations/` — a directory the docs gate has no business in. Two scripts would mean two path filters, two workflow steps and two harnesses for a shared 90% of logic.
- **Rely on the CI gate alone, leaving the allocators unchanged** — detect rather than prevent. Rejected because: the gate reports the problem *after* the migration is written, named, referenced in a PR body and possibly already run against a dev database. The allocator is the only place the collision can be prevented rather than detected.
- **A pre-push numbering hook** — a third enforcement point. Rejected because: the widened allocator prevents at source and CI backstops; `bin/pre-push-adr-hook` is scoped to the ADR *decision trigger* and chaining an unrelated check into it strains its single responsibility. Additive follow-up if field evidence shows hand-created numbers.

## Consequences

- Positive: a number claimed on a pushed-but-unmerged branch, or in a sibling worktree that has not committed yet, is never handed out twice. The allocator stays offline, so it still works with no network.
- Positive: concurrent ADR branches no longer conflict on the index table during local rebase, removing the friction that pushed authors toward hand-resolution.
- Positive: the one corruption shape `merge=union` can produce is a duplicate index row, which is exactly what the gate's third check detects — so the failure mode it introduces is loud rather than silent.
- Negative: `merge=union` is genuinely unsafe on prose, so the attribute must stay scoped to the single `ibl5/docs/decisions/README.md` path. Broadening it to a directory or `*.md` glob would silently duplicate paragraphs.
- Negative: the grandfather allowlists are a standing exception that a future reader must not mistake for permission. They forgive history; checks 1 and 2 ignore them entirely.
- Negative: the allocators now shell out to `git for-each-ref` and `git ls-tree` across every origin ref, measured at 0.49s over 50 refs. Acceptable for an interactive allocator, but it grows with ref count.

## References

- `bin/check-numbering` — the gate: duplicate-in-diff, collision-with-base, and duplicate index row.
- `bin/lib/git-helpers.sh` — `scan_origin_refs` and `scan_worktrees`, the offline enumeration primitives.
- `bin/next-adr`, `bin/next-migration` — the widened allocators.
- `.gitattributes` — the `merge=union` driver scoped to the ADR index.
- `bin/check-destructive-migrations` — the sibling gate whose skeleton, `--since=<sha>` semantics and exit-code contract this gate copies.
- `ibl5/bin/run-migrations-ci` — deliberately unchanged: it globs `*.sql` because it decides what to *execute*, while the allocator decides what number is *free*.
