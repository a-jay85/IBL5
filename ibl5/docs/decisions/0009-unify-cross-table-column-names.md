---
description: Rationale for unifying turnover, 3-pointer rating, and team-id column names across the schema (Tier 2 of the sql-column-naming audit), enforced by a new PHPStan rule.
last_verified: 2026-04-28
---

# ADR-0009: Unify Cross-Table Column Names

**Status:** Accepted
**Date:** 2026-04-21

## Context

ADR-0008 (migration 113) was Tier 1 of the sql-column-naming audit: it eliminated reserved-word columns (`to`, `do`, `r_to`) and space-containing identifiers (`Start Date`, `End Date`). ADR-0008 §Alternatives Considered explicitly deferred a Tier 2 PR for "cross-table concept unification (turnovers, 3-pointers, team-id, etc.)" to keep the rollback surface tight.

After PR 1, three concepts still had divergent names across the schema:

1. **Turnovers (live layer).** `stats_to` on `ibl_plr` / `ibl_plr_snapshots` / `ibl_olympics_plr` did not match the canonical `tvr` on hist/aggregate tables. Every `stats_to` reference required hand-translation when joining to `ibl_hist` or aggregating into history.
2. **3-pointer ratings.** `r_tga` / `r_tgp` on the live + snapshot layer (and bare `tga` / `tgp` on `ibl_draft_class`) versus `r_3ga` / `r_3gp` on `ibl_hist` / `ibl_olympics_hist`. Two unrelated naming conventions for the same rating, requiring per-table aliasing in views and per-call-site translation in PHP.
3. **Team identifier.** Five surface spellings — `tid`, `teamID`, `TeamID`, `team_id` — plus four compound variants (`homeTID`, `visitorTID`, `homeTeamID`, `visitorTeamID`, `owner_tid`, `teampick_tid`). Every JOIN bridging a player table to a team table or a box score had to translate. `ibl_power.TeamID` was the only PascalCase PK in the schema.

Tier 1 set the precedent: `SchemaValidator` boot assertions + a focused PHPStan rule (`BanReservedWordColumnsRule`) prevent silent regression after a one-time PHP sweep. Tier 2 follows the same playbook for the same class of pain.

## Decision

Migration 114 unifies all three concepts:

- **Turnovers (live):** `stats_to` → `stats_tvr` on `ibl_plr`, `ibl_plr_snapshots`, `ibl_olympics_plr`. Box-score `gameTOV` is intentionally untouched — it belongs to the internally-consistent `game*` PascalCase family (`gameFGM`, `gameREB`, `gameAST`, …) which is a Tier 3 follow-up if ever.
- **3-pointer ratings:** Canonical is `r_3ga` / `r_3gp` (matching `ibl_hist`). Renames `r_tga` / `r_tgp` on `ibl_plr` / `ibl_plr_snapshots` / `ibl_olympics_plr` and bare `tga` / `tgp` on `ibl_draft_class`. After this migration, `r_3ga` and `r_3gp` uniformly mean "3P attempts/percentage rating" across every layer. (Note: `ibl_hist.tga` — the 3PA *counting stat*, distinct from the rating — is intentionally untouched; it coexists with `r_3ga` on the same table without collision.)
- **Team-id:** All five spellings unified to lowercase `teamid` (matching `ibl_team_info` PK) or `{prefix}_teamid` for compounds. FK-bearing renames execute as drop-FK → CHANGE COLUMN → re-add-FK; PK renames on `ibl_power` / `ibl_olympics_power` use simple CHANGE COLUMN (verified no incoming FK dependents).

**Enforcement:**
- New PHPStan rule `BanInconsistentColumnNamesRule` (identifier `ibl.bannedInconsistentColumnName`) flags any backtick-quoted reference to the old names (`stats_to`, `r_tga`, `r_tgp`, `tid`, `teamID`, `TeamID`, `team_id`, `homeTID`, `visitorTID`, `homeTeamID`, `visitorTeamID`, `owner_tid`, `teampick_tid`) in SQL string literals under `classes/` and `html/`.
- `SchemaValidator` asserts every renamed column at boot via `ibl5/config/schema-assertions.php`. A failed rename surfaces immediately on app start.
- Integration test `CrossTableColumnNamingTest` asserts cross-layer parity for the renamed columns.

## Alternatives Considered

- **Box-score PascalCase rename (`gameTOV` → `stats_tvr`, `gameMIN` → `stats_min`, etc.) in the same PR** — deferred to Tier 3. The `game*` family is internally consistent within box-score tables; renaming one breaks the family without a clean replacement.
- **Pick `r_tga` / `r_tgp` as canonical (rename `ibl_hist`'s `r_3ga`/`r_3gp` to match)** — rejected. Hist is the read-mostly archive layer; the live layer is rewritten more often. Renaming the smaller side has lower risk.
- **Outliers-only team-id rename (only `TeamID` and `team_id`, leave `tid` alone)** — rejected. Maintains the dual-convention pain (`tid` on player-side, `teamid` on team-side) that forces every join to translate.
- **Include `ibl_hist.year` → `season_year`** — deferred. Already aliased in views; not a backtick problem; not a meaning-flip; out of ADR scope.

## Consequences

- Positive: every JOIN crossing the player ↔ team boundary uses one column name, no aliases. Every read of a 3-point rating uses `r_3ga` / `r_3gp` regardless of layer. Every read of season turnovers (live or hist) uses the same `tvr` family.
- Positive: new code cannot re-introduce divergent names without failing `ibl.bannedInconsistentColumnName`.
- Positive: `ibl_power.TeamID` is no longer the schema's only PascalCase PK.
- Negative: large one-time PHP sweep across ~80–150 production files + ~30–50 test files. Mitigated by `SchemaValidator` hard-failing at boot if any rename regressed and by the PHPStan rule catching missed sites in review.
- Negative: legacy filter-form API on `PlayerDatabase` keeps `tid` / `stats_to` / `r_tga` as input-side filter keys mapped via `COLUMN_MAP` (same pattern PR 1 used for `to` / `do` / `r_to`). One extra translation layer, documented in the class.

## References

- `ibl5/migrations/114_unify_cross_table_column_names.sql` — the DDL.
- `ibl5/phpstan-rules/BanInconsistentColumnNamesRule.php` — the enforcement rule.
- `ibl5/config/schema-assertions.php` — post-migration schema assertions.
- `ibl5/tests/DatabaseIntegration/CrossTableColumnNamingTest.php` — cross-layer parity test.
- `ibl5/docs/decisions/0008-ban-reserved-word-rating-columns.md` — Tier 1 precedent.
- PR #632 (Tier 1 implementation).
