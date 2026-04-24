---
description: Rationale for snake-casing PascalCase/camelCase player, depth-chart, team-info, and box-score columns across the schema (Tier 3 of the sql-column-naming audit), enforced by a new PHPStan rule. Covers the four-PR roadmap; PR 1 is the immediate scope.
last_verified: 2026-04-24
---

# ADR-0010: Cosmetic Case-Consistency Renames (Tier 3)

**Status:** Accepted
**Date:** 2026-04-24

## Context

ADR-0008 / migration 113 (Tier 1) eliminated reserved-word and space-containing columns. ADR-0009 / migration 114 (Tier 2) unified three cross-table concepts — turnovers, 3-pointer ratings, and team-id spellings. Both ADRs explicitly deferred "cosmetic case-consistency renames (`Clutch`, `gameMIN`, `ibl_schedule` PascalCase, etc.)" to a Tier 3 follow-up to keep the rollback surface tight.

After Tiers 1–2 there are **185 non-snake_case columns across 11 tables**. They fall into four affinity groups that map cleanly to four PRs:

| PR | Scope | Tables affected | Rough blast |
|----|-------|-----------------|-------------|
| 1 (this PR) | Player ratings + depth-chart dc_* + cache reserved word | `ibl_plr`, `ibl_plr_snapshots`, `ibl_olympics_plr`, `ibl_saved_depth_chart_players`, `ibl_olympics_saved_depth_chart_players`, `ibl_draft_class`, `cache`, `cache_locks` | ~180 prod + ~250 test |
| 2 (PR #638) | Team-info columns (`discordID`, `Contract_*`, `HasMLE`, etc.) | `ibl_team_info`, `ibl_olympics_team_info` | ~148 prod + ~224 test |
| 3 | Standings columns (`homeWins`, `leagueRecord`, etc.) | `ibl_standings`, `ibl_olympics_standings` | ~169 prod + ~278 test |
| 4 | Box-score `game*` PascalCase family + schedule + quarter-points | `ibl_box_scores`, `ibl_box_scores_teams`, `ibl_schedule`, olympics equivalents | ~540 prod + ~388 test |

Splitting by table affinity (not purely by blast size) keeps each PR's failure mode confined to one family of call sites.

## Decision

Tier 3 proceeds as a four-PR sequence, each following the Tier 1/2 playbook: focused migration, `SchemaValidator` boot assertions, extend the same PHPStan rule (`BanNonSnakeCaseColumnsRule`, `ibl.bannedNonSnakeCaseColumn`), one-time PHP sweep, production parity spot-check.

**PR 1 — this PR — migration 116:**

- **Player rating columns (snake_case).** `Clutch` → `clutch`, `Consistency` → `consistency` on `ibl_plr` and `ibl_olympics_plr` (already lowercase on `ibl_plr_snapshots` and `ibl_hist`).
- **Position-depth columns.** `PGDepth`/`SGDepth`/`SFDepth`/`PFDepth`/`CDepth` → `pg_depth`/`sg_depth`/`sf_depth`/`pf_depth`/`c_depth` on `ibl_plr`, `ibl_plr_snapshots`, `ibl_olympics_plr`.
- **Depth-chart columns.** `dc_PGDepth`/…/`dc_CDepth` → `dc_pg_depth`/…/`dc_c_depth` and `dc_canPlayInGame` → `dc_can_play_in_game` on `ibl_plr`, `ibl_olympics_plr`, `ibl_saved_depth_chart_players`, `ibl_olympics_saved_depth_chart_players` (plus `dc_canPlayInGame` on `ibl_plr_snapshots`).
- **Free-agency preference.** `playingTime` → `playing_time` on `ibl_plr`, `ibl_plr_snapshots`, `ibl_olympics_plr`.
- **Self-documenting.** `sta` → `stamina` on `ibl_plr`, `ibl_olympics_plr`, `ibl_draft_class`.
- **Cache reserved-word fix.** `cache.key` / `cache_locks.key` → `cache_key` (the Laravel-style cache tables shipped a bare SQL reserved word).
- **View regeneration.** `vw_player_current` references `p.dc_canPlayInGame`; recreated at the bottom of migration 116 with the new column name.

**Enforcement (PR 1):**

- New PHPStan rule `BanNonSnakeCaseColumnsRule` (identifier `ibl.bannedNonSnakeCaseColumn`) flags any backtick-quoted reference to the PR-1 old names in SQL string literals under `classes/` (and `html/` if it exists). PRs 2–4 extend this same rule's `BANNED_TOKENS` list with their scope.
- `BanReservedWordColumnsRule` is extended with `` `key` `` (same identifier `ibl.bannedReservedWordColumn`, consistent with Tier 1's precedent).
- `SchemaValidator` asserts every renamed destination column at boot via `ibl5/config/schema-assertions.php`.

## Alternatives Considered

- **One big PR for all 185 columns.** Rejected. A sweep touching ~1,000 prod + ~1,100 test files is unreviewable and the rollback surface is catastrophic. Table-affinity split bounds each PR's blast radius.
- **Drop the cache rename.** Rejected. `` `key` `` is a SQL reserved word and violates the Tier 1 precedent from ADR-0008. Including it here (rather than a standalone Tier 1.5 PR) piggybacks the PHPStan rule extension and schema-assertion update already on this PR's critical path.
- **Rename `ibl_olympics_plr.dc_active`** (the only column that diverges from the `canPlayInGame` family). Out of scope for Tier 3a — it is the reverse direction (the name-unification concern, not the case-consistency concern) and would need its own rationale. Defer to a later audit pass.
- **Include `cy1`–`cy6` → `salary_yr1`–`salary_yr6` in PR 1.** Rejected. That rename has 288 prod + 46-file hits and warrants its own PR (Tier 4) — the self-documenting win is large but the blast radius is disproportionate to PR 1's player-ratings focus.
- **Drop `sta` → `stamina`.** Rejected on the same rationale as earlier tiers: self-documenting column names are a cumulative ergonomics win; piggybacking onto a PR that already touches `ibl_plr` / `ibl_olympics_plr` / `ibl_draft_class` minimizes follow-up churn.

## Consequences

- Positive: the player + depth-chart cluster reads uniformly in snake_case after PR 1. Grep for `playingTime` or `PGDepth` returns zero hits in `classes/`.
- Positive: `cache.key` no longer requires backtick-escaping every read/write. Four production call sites (`DatabaseCache`, `NegotiationRepository`, `RecordHoldersRepository`) simplify.
- Positive: `ibl.bannedNonSnakeCaseColumn` prevents regression; PRs 2–4 extend the same rule instead of each adding a new one.
- Negative: one-time PHP sweep touches ~180 prod files and ~250 test files. Mitigated by (a) `SchemaValidator` hard-failing at boot if any rename regressed, (b) PHPStan rule catching missed sites in review, (c) split into 5 parallel Sonnet agents per cluster (player parser, depth chart, FA/negotiation/team, cache + PlayerDatabase, test fixtures).
- Negative: `PlayerDatabase::COLUMN_MAP` keeps `Clutch` / `Consistency` as form-field input-filter keys mapped to the new `clutch` / `consistency` column names — same pattern used in PR #632 for `to` / `do` / `r_to`. Documented in the class.
- Negative: DuckDB analytics schema (`analytics/schema/*.sql`) reads the renamed MariaDB columns. `AS r_to` / `AS "do"` / `AS "to"` shims left by Tiers 1–2 are removed in this PR along with downstream consumer query updates (same incremental-cleanup approach ADR-0008 used).

**PR 2 — migration 117:**

- **Team-info columns (snake_case).** 9 columns on `ibl_team_info` and 5 on `ibl_olympics_team_info`: `discordID` → `discord_id`, `Contract_Wins` → `contract_wins`, `Contract_Losses` → `contract_losses`, `Contract_AvgW` → `contract_avg_w`, `Contract_AvgL` → `contract_avg_l`, `Used_Extension_This_Chunk` → `used_extension_this_chunk`, `Used_Extension_This_Season` → `used_extension_this_season`, `HasMLE` → `has_mle`, `HasLLE` → `has_lle`.
- `BanNonSnakeCaseColumnsRule` extended with 9 additional banned tokens.
- `Team` class properties `$discordID` → `$discord_id`, `$hasMLE` → `$has_mle`, `$hasLLE` → `$has_lle` renamed for consistency (direct column mirrors with ≤14 access sites).

## References

- `ibl5/migrations/116_snake_case_player_columns.sql` — PR 1 DDL.
- `ibl5/migrations/117_snake_case_team_info_columns.sql` — PR 2 DDL.
- `ibl5/phpstan-rules/BanNonSnakeCaseColumnsRule.php` — the enforcement rule (extended per PR).
- `ibl5/phpstan-rules/BanReservedWordColumnsRule.php` — extended with `` `key` `` (PR 1).
- `ibl5/config/schema-assertions.php` — post-migration schema assertions.
- `ibl5/docs/decisions/0008-ban-reserved-word-rating-columns.md` — Tier 1 precedent.
- `ibl5/docs/decisions/0009-unify-cross-table-column-names.md` — Tier 2 precedent.
