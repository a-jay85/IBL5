---
description: Rationale for renaming reserved-word rating columns and fixing the r_to meaning-flip across ibl_plr and ibl_hist, enforced by a new PHPStan rule.
last_verified: 2026-04-28
---

# ADR-0008: Ban Reserved-Word Rating Columns

**Status:** Accepted
**Date:** 2026-04-21

## Context

Three long-standing column-naming issues on `ibl_plr`, `ibl_plr_snapshots`, `ibl_olympics_plr`, `ibl_draft_class`, `ibl_hist`, `ibl_olympics_hist`, and `ibl_sim_dates`:

1. **Reserved-word columns `to` and `do`** forced backtick-escaping in every SQL string that referenced them — at least 7 production PHP files carried `` `to` `` / `` `do` `` escapes. New code paths repeatedly re-introduced the pattern.
2. **A silent `r_to` meaning-flip between layers.** On `ibl_plr` / `ibl_plr_snapshots`, `r_to` meant turnover rating. On `ibl_hist` it meant transition-offense rating (populated from `snap.`to`` by `RefreshIblHistStep`). Any query that read `r_to` from the wrong layer got the wrong stat, no error.
3. **Space-containing identifiers `Start Date` / `End Date`** on `ibl_sim_dates` required backtick-quoting and were the only PascalCase+spaced columns in the schema.

Prior renames (PRs #323 `trade_from/trade_to`, #334 `dc_canPlayInGame`) set the precedent but did not tackle the rating block.

## Decision

Migration 113 renames the offending columns: `to` → `r_trans_off`, `do` → `r_drive_off`, live/snapshot `r_to` → `r_tvr`, hist `r_to` → `r_trans_off`, hist `r_do` → `r_drive_off`, `` `Start Date` `` → `start_date`, `` `End Date` `` → `end_date`. After the rename, `r_tvr` uniformly means turnover rating, `r_trans_off` uniformly means transition-offense rating, and `r_drive_off` uniformly means drive-offense rating across every layer. Enforcement: new PHPStan rule `BanReservedWordColumnsRule` (identifier `ibl.bannedReservedWordColumn`) flags any backtick-quoted reference to `` `to` ``, `` `do` ``, `` `r_to` ``, `` `Start Date` ``, or `` `End Date` `` in SQL string literals under `classes/` and `html/`. `SchemaValidator` asserts the new columns at boot via `ibl5/config/schema-assertions.php`. `RatingColumnSemanticParityTest` asserts cross-layer consistency forever in CI.

## Alternatives Considered

- **Keep backticks and leave `r_to` aliased by the view** — rejected: the meaning-flip silently returns the wrong stat when a caller reads from the wrong layer; no linter can detect "wrong stat."
- **Only rename `to` / `do` (reserved words), leave `r_to` alone** — rejected: the `r_to` meaning-flip is the higher-impact bug; fixing both together avoids two rounds of PHP sweep on the same files.
- **Rename `Clutch` / `Consistency` / `gameMIN` PascalCase columns in the same PR** — deferred to Tier 3 follow-up: blast radius is large (touches box-score tables and every Player view) and unrelated to the reserved-word problem.
- **Full Tier 2 cross-table unification (tov, 3pm, team-id) in one PR** — deferred to a follow-up Tier 2 PR: keeps this PR reviewable and the rollback surface tight.

## Consequences

- Positive: every query against player ratings is now backtick-free; new code cannot re-introduce the backticks without failing `ibl.bannedReservedWordColumn`.
- Positive: `r_tvr` and `r_trans_off` have single, unambiguous meanings across live, snapshot, archive, and hist layers; the `RefreshIblHistStep` alias-flip is gone.
- Positive: `bin/db-query` and ad-hoc reporting can use modern snake_case identifiers on `ibl_sim_dates`.
- Negative: one-time PHP sweep across 28 repositories/services/views plus test fixtures. Mitigated by `SchemaValidator` hard-failing at boot if any rename regressed, and the new `BanReservedWordColumnsRule` catching missed sites in review.
- Negative: legacy filter-form API on `PlayerDatabase` keeps `do` / `to` / `r_to` as form-field names, mapped via `COLUMN_MAP` — one extra translation layer, documented in the class.

## References

- `ibl5/migrations/113_rename_reserved_word_rating_columns.sql` — the DDL.
- `ibl5/phpstan-rules/BanReservedWordColumnsRule.php` — the enforcement rule.
- `ibl5/config/schema-assertions.php` — post-migration schema assertions.
- `ibl5/tests/DatabaseIntegration/RatingColumnSemanticParityTest.php` — permanent parity test.
- `ibl5/tests/DatabaseIntegration/IblHistStructuralTest.php` — `ibl_hist` structural invariants.
- `ibl5/classes/Updater/Steps/RefreshIblHistStep.php` — rewritten INSERT SELECT with no alias flip.
- PR #323 (precedent for `trade_from` / `trade_to` rename).
- PR #449 (`SchemaValidator`).
