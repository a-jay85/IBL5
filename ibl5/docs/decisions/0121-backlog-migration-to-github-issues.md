---
description: Project work tracking moves from 21 markdown backlog files to GitHub Issues in the private repo a-jay85/IBL5-backlog, retiring the union-merge and duplicate-ID machinery that file-shaped tracking required.
last_verified: 2026-09-08
---

# ADR-0121: Backlog migration to GitHub Issues

**Status:** Accepted
**Date:** 2026-09-07

## Context

IBL5 tracks ~690 open work items across 21 markdown backlog files under `ibl5/docs/backlog/` (example), `engine/docs/backlog/` (example), and one gitignored security file. Because several agents and PRs append to the same file concurrently, those files carry `merge=union` in `.gitattributes` — which never conflicts but silently resurrects deleted rows, drops shared boilerplate lines, and splices one entry's body into another. Three separate gates exist only to make this file shape survive concurrency: `bin/check-numbering` check 4 (duplicate item IDs), and `bin/check-docs`'s `checkBacklogTransitions()` and `checkMaintenanceResolved()`. PRs #1967 and #1950 each cost a full hand-repair session to a union-merge splice that every one of those gates passed.

## Decision

Project work tracking moves to GitHub Issues in the existing private repo `a-jay85/IBL5-backlog`: one Issue per backlog item, the source area as a label (`dev-efficiency`, `ci`, `maintenance`, `e2e`, `loop-engineering`, `token-spend`, `a11y`, `a11y-contrast`, `security`, `jsb-native`, plus `archived`), and the legacy item ID as a title prefix so existing cross-references stay resolvable. Migration is performed once by `bin/migrate-backlog-to-issues`, an idempotent PHP CLI keyed on (label, leading ID token) read from the live remote rather than a local state file; it authenticates through a developer's local `gh` session, so no cross-repo PAT and no CI secret is introduced. Implementation lands in **two** PRs: this one ships the tool, its `ibl5/tests/Cli/MigrateBacklogToIssuesCliTest.php` coverage, the destination labels, and the real migration run; a follow-up PR deletes the corpus and retires `bin/backlog-open` (example), `bin/check-numbering` check 4, `checkBacklogTransitions()`, and `checkMaintenanceResolved()`, once the open PRs that still append to the backlog files have drained.

## Alternatives Considered

- **Keep the files and harden the gates** — add a structural splice detector to `bin/check-numbering`. Rejected because: it adds a fourth gate whose only job is to compensate for a merge strategy we chose, when the concurrency problem disappears entirely with per-item records.
- **A sync bridge (Issues plus a generated markdown mirror)** — keep a read-only backlog file regenerated from Issues. Rejected because: a bridge running in CI is exactly what forces the cross-repo PAT this decision refuses, and a stale mirror re-creates the ambiguity it was meant to remove.
- **GitHub Projects without Issues** — track items as Project draft cards. Rejected because: draft cards have no API-stable identity, no labels, and cannot be referenced from a commit message or a PR body.

## Consequences

- Positive: concurrent filing stops touching shared files, so `merge=union` splices, ghost rows, and duplicate-ID collisions become structurally impossible rather than gate-detected.
- Positive: four enforcement mechanisms and one skill are retired rather than maintained; `.claude/rules/` sheds a resident rule in favour of a smaller pointer.
- Negative: work items leave the repo, so they are no longer greppable from a checkout, no longer visible offline, and no longer versioned alongside the code that motivated them. `bin/check-docs` does not validate Issue URLs, so a dead Issue link is undetected.
- Consequence of the key choice: a live entry and its archived twin share an area label and an ID, so they collapse to a single Issue. The migration extracts 685 items and maps them onto 638 Issues; each of the 47 collapses was confirmed to be such a twin pair by enumerating every multiply-claimed Issue and comparing its claimants' headings, rather than inferring the figure from the arithmetic. Item counts and Issue counts are therefore not interchangeable figures.
- The key is only as unique as the heading it reads, and a generic heading is not unique. `ibl5/docs/backlog/draft-selection-pick-ownership.md` (example) nests `###` sub-headings (`Problem`, `Suggested fix`) *under* each `## Finding`, so those sub-headings repeated across findings collided onto one idempotency key and only the first body of each survived — a silent loss the created/skipped counters cannot reveal, because a collision looks exactly like a legitimate twin collapse. Sources shaped that way carry `whole_file` in the tool's source map and become one Issue per document. The enumeration that found this is now enforced rather than remembered: `assertNoKeyCollisions()` aborts extraction — before any Issue is created — when two items in the same file produce the same key, naming the file, the key, and every body that would have been dropped. A twin collapse is always cross-file, so the same-file scope raises no false positives; the real corpus extracts 685 items with the guard silent. One source is outside that proof: `ibl5/docs/security-backlog.md` (example) is gitignored and absent from the migration machine (`skip_missing`), so its heading shape is unverified and should be checked for the nested-subheading pattern before the corpus is deleted.
- Idempotency covers **close state**, not only creation. A create that succeeds but whose follow-up close fails would otherwise never self-correct, because every later run skips the item on its idempotency key and never revisits its state; the tool re-closes any resolved item whose Issue is still open. The first migration run left 45 such Issues open and the second healed all 45.

## References

- `bin/migrate-backlog-to-issues` — the one-shot idempotent migration CLI.
- `ibl5/tests/Cli/MigrateBacklogToIssuesCliTest.php` — dry-run coverage of extraction, labelling, state, and idempotency.
- `.gitattributes` — carries the `merge=union` attribute this decision retires for backlog paths.
- `.claude/rules/backlog-housekeep.md` — the resident rule replaced by a pointer once the corpus is deleted.
