---
description: Index of tracked backlogs under docs/backlog/ — one row per LIVE backlog plus the archive pointer.
last_verified: 2026-07-03
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

## Archive

[`archive/`](archive/) holds the read-only historical record of ✅ Implemented / 🚫 Declined findings,
extracted out of `maintenance-backlog.md` so the LIVE file stays short. Not governed by `bin/check-docs`
(historical dead refs are tolerated there).

## Not part of this directory

`security-backlog.md` and `security-backlog-parts/` are gitignored (local-only) and are intentionally
excluded from this directory and from version control.
