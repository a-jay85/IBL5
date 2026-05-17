---
description: Retrospective ADR documenting the complete XSS burndown across Plans A, B, and C — 186+ instances eliminated, 17 view files now in zero-floor.
last_verified: 2026-05-17
---

# ADR-0032: XSS Burndown Retrospective

## Status

Accepted

## Context

Across three XSS cleanup plans (A: Navigation 58 entries, B: SeasonLeaderboards 71 entries, C: CareerLeaderboards + remainder 186 entries), the project burned down all `ibl.unescapedOutput` baseline suppressions in View files. The zero-floor list (ADR-0031) now covers 17 files:

- 5 Navigation views (Plan A)
- 1 SeasonLeaderboards view (Plan B)
- 11 remaining views (Plan C): Boxscore, CareerLeaderboards, DepthChartEntry, FranchiseRecordBook, LeagueControlPanel, NextSim, RookieOption, Standings, Team, TransactionHistory, YourAccount

The `phpstan-baseline.neon` file now contains zero `ibl.unescapedOutput` entries.

## Decision

All view files actively rendering user-controlled data are in `ZERO_FLOOR_FILES`. New views must be authored with `HtmlSanitizer::e()` from line one. Baseline suppression is no longer an option for the listed files.

## Consequences

- **Positive:** View-layer XSS risk is mechanically enforced to zero. No `ibl.unescapedOutput` entries remain in the baseline.
- **Positive:** Each cleaned file has a corresponding XSS regression test that injects `<script>` payloads and asserts HTML-entity escaping.
- **Negative:** View authors must use `HtmlSanitizer::e()` or safe-expression patterns (casts, literals, whitelisted helpers) for every echoed expression. This cost is intentional.
- **Maintenance:** New views default outside zero-floor; PRs introducing them should add the file to the list with no baseline entries.
- **Remaining scope:** Controller-level `ibl.unescapedOutput` entries (if any surface when the rule extends to controllers) and `HtmlSanitizer::trusted()` audit (backlog 10.10) are separate from this burndown.
