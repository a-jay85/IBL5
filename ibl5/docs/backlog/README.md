---
description: Index of tracked backlogs under docs/backlog/ — one row per LIVE backlog plus the archive pointer, and the canonical status taxonomy shared by all backlogs; and the archive / dated-pointer / supersession housekeeping conventions.
last_verified: 2026-07-17
---

# Backlog index

Each LIVE backlog below lists only open work (⬜ Open / ◑ Partial / 📋 Planned) — a future `/plan` can read
one of these files straight through without wading through resolved history. Every entry is a candidate for
a `/plan`.

| Backlog | Purpose |
|---------|---------|
| [maintenance-backlog.md](maintenance-backlog.md) | Maintenance-cost reduction opportunities across the codebase, organized by axis. |
| [ci-backlog.md](ci-backlog.md) | CI/GitHub-Actions workflow simplification — duplicated boilerplate, job consolidation. |
| [e2e-backlog.md](e2e-backlog.md) | E2E (Playwright + api-e2e) test-quality — refactoring, weak assertions, flake-prone patterns. |
| [a11y-backlog.md](a11y-backlog.md) | WCAG 2.x full-rule (non-contrast) accessibility failures. |
| [a11y-contrast-backlog.md](a11y-contrast-backlog.md) | WCAG 2.1 AA color-contrast failures per page. |
| [token-spend-backlog.md](token-spend-backlog.md) | Token-economy of the Claude Code harness — resident context, caching, output spend. |
| [dev-efficiency-backlog.md](dev-efficiency-backlog.md) | Development-efficiency tooling — inner-loop speed, CI caching, worktree lifecycle. |
| [loop-engineering-backlog.md](loop-engineering-backlog.md) | Autonomous-loop robustness — automouse queue, self-healing, digests, autonomy contracts. |
| [jsb-native-backlog.md](jsb-native-backlog.md) | JSB native-engine cut-over — count-axis blocker chain, static RE pins, faithful ports; every entry model-tiered (incl. Fable-gated). |

Note: `token-spend-backlog.md` and `loop-engineering-backlog.md` include entries whose deliverable lives
**outside the repo** (`$HOME/.claude/` settings/hooks/memory — marked ⌂ in those files). Those are exempt
from the worktree rule and ship as direct harness edits, not PRs.

## Status taxonomy

The canonical five-glyph status set used by every backlog in this directory:

- ✅ **Implemented** — merged (or live, for harness-local entries); the named concern is resolved, verified on disk.
- ◑ **Partial** — partially addressed; residual work named in the entry's note.
- 📋 **Planned** — a plan file exists (queued or PR-open); not yet merged.
- ⬜ **Open** — no plan yet.
- 🚫 **Declined** — deliberately won't-do (rationale in the entry's Status line).

Domain-specific **automouse-readiness** legends (🟩/🟦/🟨/🟥) and **effort scales** (S/M/L) stay defined
per-file — their semantics vary by domain.

## Housekeeping conventions

- **Discovered-item provenance:** a backlog item surfaced while implementing another change is stamped
  `(discovered YYYY-MM-DD during <ref>)`, where `<ref>` is the PR number or plan slug — a greppable origin.
- **Cross-backlog supersession:** when a change makes an item in *another* backlog redundant, stamp that
  item `**Superseded by:** <ref> — <reason>` (verbatim, greppable). Do not delete it — the stamp is the audit trail.
- **Ship it with the work:** run `/backlog-housekeep` (the `.claude/rules/backlog-housekeep.md` rule surfaces
  this when you touch a backlog file); it applies the flips, stamps, archive moves, and README reconciliation,
  then self-verifies via `bin/check-docs`.

## Archive

[`archive/`](archive/) holds the read-only historical record of ✅ Implemented / 🚫 Declined findings,
extracted out of the LIVE backlogs so they stay short. Not governed by `bin/check-docs` (historical dead
refs are tolerated there).

- **Sibling pairing:** each LIVE `<x>-backlog.md` pairs with `archive/<x>-backlog-archive.md` (e.g.
  `token-spend-backlog.md` ↔ `archive/token-spend-backlog-archive.md`).
- **Archiving an item:** when an item reaches ✅ Implemented or 🚫 Declined in a **body-status** backlog,
  move its body into the sibling archive and replace the LIVE entry with a one-line **dated pointer**, e.g.
  `➜ <id> <title> — ✅ Implemented (YYYY-MM-DD): see [archive](archive/<x>-backlog-archive.md).`
- **Table-status backlogs** (`maintenance-backlog.md`) keep their glyphs in-place per row — no per-item
  pointer; bulk-resolved sections still extract to the archive.
- **Enforced by** `bin/check-docs --since=<base>`: a newly-added archive pointer must resolve to its
  sibling, and a body-status item flipped done must become a pointer (the diff-scoped backstop).

## Not part of this directory

`security-backlog.md` and `security-backlog-parts/` are gitignored (local-only) and are intentionally
excluded from this directory and from version control.
