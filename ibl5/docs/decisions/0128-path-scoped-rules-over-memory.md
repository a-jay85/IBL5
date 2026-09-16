---
description: Directory-specific operational knowledge lives in path-scoped .claude/rules/*.md files, not in per-project memory; the always-loaded rule set is capped at 16000 B.
last_verified: 2026-09-16
---

# ADR-0128: Path-scoped rules over memory for directory-specific knowledge

**Status:** Accepted
**Date:** 2026-09-16

## Context

Per-project agent memory (`~/.claude/projects/.../memory/`) had accumulated roughly 40 files of
directory-specific know-how — Playwright fill gotchas, PHPStan baseline-count drift, migration
pitfalls, CSS extraction traps, CI workflow wiring, shell portability — that were never indexed in
`MEMORY.md`. Unindexed memory is only recalled when a keyword happens to match, so the same
lesson was re-learned in later sessions. Meanwhile `.claude/rules/` already supports `paths:`
frontmatter, which attaches a rule exactly when a matching file is touched, and the always-loaded
(resident) rule set sat at its 20000 B cap with zero headroom, with two PR-body rules resident
even though only `/post-plan` and `/ship` ever act on them.

## Decision

Operational knowledge that is specific to a directory or file pattern goes into a path-scoped
rule under `.claude/rules/` (with a `paths:` list), not into memory. Memory is reserved for
user preferences, session-crossing status, and lessons that have no file-pattern trigger.
Rules that only one skill consumes live in that skill's directory
(`.claude/skills/post-plan/_pr-body-claims.md`), not in the resident set. The resident set is
the four files named in `ALLOWLIST` in `bin/check-rules-byte-budget`, with the aggregate cap
ratcheted from 20000 B to 16000 B (`RULES_TOTAL_BUDGET`); the gate enforces both.

When folding memory into rule text, every claim must trace to a source sentence or a repo grep:
a Sonnet condensation pass invented mechanisms in 7 of 30 sections on first attempt and needed a
per-claim verification pass before this ADR's PR could ship.

## Alternatives Considered

- **Index the memory files in `MEMORY.md`** — makes them recallable but costs index bytes every
  session and still relies on keyword recall. Rejected because: the trigger is a file path, and
  `paths:` matches that exactly at zero resident cost.
- **One large lazy rule per area** — fewer files. Rejected because: the lazy per-file cap is
  16000 B and one file would attach far more text than the touched file needs.
- **Leave the cap at 20000 B** — keeps headroom for future resident rules. Rejected because: an
  unbound cap invites drift back to always-loaded prose; the ratchet forces the path-scoped default.

## Consequences

- Positive: resident rule bytes fall from 19991 B to 15267 B per session (per
  `bin/check-rules-byte-budget`), and the folded lessons now attach deterministically.
- Positive: memory shrinks by 41 files and its index stays preventive-only.
- Negative: a lesson that belongs to no file pattern still has to go to memory, and the rule
  author has to pick the right `paths:` glob; a wrong glob means the rule never fires.
- Negative: a future resident rule needs a matching byte cut elsewhere (~733 B headroom).

## References

- `bin/check-rules-byte-budget` — enforces the resident allowlist and the 16000 B aggregate
- `.claude/rules/ci-gotchas.md`, `.claude/rules/shell-portability.md`,
  `.claude/rules/adr-append-only.md` — new path-scoped rules created by this decision
- `.claude/skills/post-plan/_pr-body-claims.md` — the PR-body rules moved out of the resident set
- `ibl5/docs/decisions/0079-stale-docs-auto-remediation.md` — prior use of the byte-budget gate
