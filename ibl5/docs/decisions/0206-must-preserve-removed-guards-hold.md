---
description: New plans list the guards a change must keep, a stdlib detector flags removed guards in a diff, automouse impl self-reviews before handoff, and arming condition (20) holds a PR whose removed guards lack a reason.
last_verified: 2026-10-21
---

# ADR-0206: Must preserve plans and the removed-guards arming hold

**Status:** Accepted
**Date:** 2026-10-21
**Deciders:** post-plan harness maintainers

## Context

PR #3370 dropped `class_exists(Discord::class)` guards and a cache-stampede lock in files outside its scope. PR #3371 removed a `try`/`catch (\RuntimeException)` around `getLastRegularSeasonGameDate()` in `Season.php`. Post-plan review caught both, but late, after the implementing session had lost the context to restore them cheaply. PR #3366 shipped regex near-misses (`None of these…`, `checks 3 and 4`) that a planned edge-case matrix row would have caught.

## Decision

Move these findings earlier, in four layers that feed each other.

1. Plan time. New plans carry a `## Must preserve` section that lists the guards the change keeps, plus an optional `### Intended removals` list. `bin/check-plan` gate `[MP]` lints the section whenever it is present. Only the authoring paths (`bin/plan-now` and plan `SKILL.md` Step 5) pass `--require-must-preserve`, so queued and legacy plans keep passing unchanged. The architect contract also asks for one near-miss matrix row per regex or parser boundary.
2. Detection. `bin/lib/deleted-guards` reads a unified diff and reports each removed guard line (PHP, shell, Python) that does not reappear in the same file and is not an intended removal. It is the single owner of the guard taxonomy and of the `## Must preserve` grammar (`--lint-plan`). `bin/check-plan` and the Python harness call it and never re-implement it.
3. Implementation time. Automouse impl Step 6.8 runs the shared review rubric and the detector before the handoff. Each flagged guard is restored, or justified with a one-line reason in `$(git rev-parse --absolute-git-dir)/removed-guards-reasons.tsv`.
4. Ship time. Post-plan renders a `## Removed guards` PR-body block from the detector output and the reasons file. Arming condition (20) holds the PR when a flagged guard lacks a substantive reason. The shared predicate is `pr_removed_guards_hold` in `bin/lib/pr-armable.sh`, mirrored in `harness/removed_guards.py` and kept in parity by `bin/fixtures/removed-guards/cases.json`. The run-local arming path re-runs the detector, so deleting the block holds the PR as `UNLISTED`. A detector error fails closed.

## Consequences

- A PR that removes a guard without a reason is held for a human.
- A guard moved to another file needs a one-line "moved to X" reason.
- The detector is line-regex based. A guard rewritten into a new shape, such as `?->` replacing `if ($x === null) return`, is not detected. The Step 6.8 self-review rubric is the backstop for that blind spot.
- `bin/pr-triage` sees only the PR body, so its view of condition (20) is advisory. The run-local check stays authoritative.

## Alternatives rejected

- A prose hold in `_pr-body-claims.md`: prose holds are what let PR #3370 and PR #3371 reach review with the guards gone. Only a predicate that arming reads enforces "no reason, no merge".
- A body-only predicate: it passes when the block is missing, so deleting the block would bypass the hold.
- Repo-wide moved-guard cancellation: PR #3371 adds unrelated `try {` lines in other files, which would hide the real `Season.php` removal.
- A date or plan-version cutoff for the new section: a date breaks plans queued across the boundary, and a version key adds a field the orchestrator must remember. The authoring-path flag keeps the queue and drain paths byte-identical.
- AST parsers for PHP, shell and Python: a diff gives fragments, not parseable files, and three parsers add dependencies to a stdlib-only tool.
- A second `## Must preserve` parser inside `bin/check-plan`: it would drift from the detector's parser, so a plan that lints clean could suppress nothing, or the reverse.
