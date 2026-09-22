---
description: Replace SeriesRecords with a Teams/Franchises/GMs head-to-head matrix, a slim retired-era branding table, and a 24-key cache.
last_verified: 2026-09-22
---

# ADR-0136: Head-to-Head Records Module

**Status:** Accepted
**Date:** 2026-09-20
**Deciders:** A-Jay

## Context

`SeriesRecords` rendered a single franchise-level win/loss table built from the `vw_series_records` view. It answered one question: how two current franchises have fared against each other. It had no way to express the two other groupings league members ask about: which GM has owned which other GM, and how a retired identity such as the Seattle Supersonics fared before the franchise moved.

A working replacement was built on the `h2h-records-polish` branch and merged as PR #626 in April 2026, but that work is not an ancestor of today's `master`: the repository history was rewritten and the column-rename sweep landed afterwards, so the branch's SQL references pre-rename column names and cannot be merged, rebased, or cherry-picked. It survives only as read-only reference text.

Standings also depended on the `SeriesRecords` namespace. `StandingsView` took a `SeriesRecordsServiceInterface` purely to call `buildSeriesMatrix()`, and `StandingsRepository::getSeriesRecords()` delegated to `SeriesRecordsRepository`. That coupling blocked deleting the module.

## Decision

### Dimensions and axis keys

The module renders one matrix per selected dimension:

| Dimension | Axis key format | Axis order |
|-----------|-----------------|------------|
| `franchises` | `"{teamid}"` | `ibl_team_info.team_name` |
| `teams` | `"{franchise_id}\|{team_city}\|{team_name}"` | franchise id, then first season |
| `gms` | `gm_display_name` | alphabetical |

`records[selfKey][oppKey]` holds the self perspective. Missing pairs are absent rather than zero-filled; the view renders an absent pair as `0-0`.

Every matrix is built from a de-duplicated game base: `MIN(id)` grouped by `(game_date, visitor_teamid, home_teamid, game_of_that_day)`. A box-score table carries one row per team per game, so the `UNION ALL` of the two perspectives yields exactly `2 × games` decided outcomes. That identity is the module's reconciliation test.

### `ibl_franchise_era_branding`

Retired identities need colors that `ibl_team_info` no longer carries. Migration 182 adds a six-column table (`id`, `franchise_id`, `team_city`, `team_name`, `color1`, `color2`) with a UNIQUE era key and an FK to `ibl_team_info.teamid`, seeded with six retired eras.

A 25-column clone of `ibl_team_info` was rejected. The matrix needs colors and nothing else; a clone would duplicate arena, owner, and finance columns that have no per-era meaning and would drift from `ibl_team_info` on every rebrand. Logos stay on disk and are found by `LogoResolver`, so the table holds no file paths.

### GM attribution

A game is attributed to the GM whose tenure covers its season:

```
p.season_year BETWEEN (t.start_season_year + t.is_mid_season_start)
                  AND COALESCE(t.end_season_year, 9999)
```

Adding `is_mid_season_start` means a GM who took over mid-season does not get credit for that season's full slate. When tenures overlap, the earliest `start_season_year` wins, then the lowest `id`. That is a total order, so the result is deterministic. Games where both sides resolve to the same GM are excluded; a GM cannot have a record against themselves.

The lookup is a correlated subquery inside the same statement as the de-duplicated game base. A PHP-side tie-break would need every perspective row streamed into PHP and the season-window logic re-implemented once per side.

### GM row branding

A GM's row takes its colors and logo from the tenure with `end_season_year IS NULL` (lowest `id` if several), reading `ibl_team_info` for the current identity. For a GM with no open tenure, the tenure with the greatest `end_season_year` supplies the franchise, and the `ibl_franchise_seasons` row at `(franchise_id, end_season_year)` supplies the era, which then follows the `teams` branding rule. A GM with no era row at that season gets empty colors and `new{id}.png`.

### Standings decoupling

`buildSeriesMatrix()` is inlined as a private method on `StandingsView` and the `vw_series_records` read is inlined on `StandingsRepository`. The view keeps `vw_series_records` as its source. The alternative was keeping `SeriesRecordsService` alive as the Standings H2H provider. That leaves two modules computing overlapping records from two different sources and keeps a nav entry with no page behind it. The inlined method is a pure array fold already pinned by the `renderRegion-h2h-tiebreak.html` snapshot.

### Security surface

`modules.php?name=HeadToHeadRecords` is a public POST endpoint whose three parameters select SQL fragments. The defense is two layers:

1. `resolveFilters()` accepts a value only when `is_string($v) && in_array($v, self::VALID_*, true)`. Strict comparison blocks `'0'`/`0` coercion. Anything else falls back to the default; unknown keys are ignored.
2. `gameTypeFilter()` and `scopeFilter()` are `match` expressions with **no `default` arm** over the same closed sets. An unlisted string throws `\UnhandledMatchError` before any SQL string is built. The only interpolated values are the match outputs, `League::MAX_REAL_TEAMID`, and an `int` season year.

Team joins are bounded by `BETWEEN 1 AND League::MAX_REAL_TEAMID`, so placeholder and Olympics ids cannot reach an axis. Every label, sublabel, and title passes `HtmlSanitizer::e`; colors pass `TableStyles::sanitizeColor`, which collapses a non-hex value to `000000`.

No CSRF token is applied. The endpoint is read-only, idempotent, and public. An anonymous visitor has no session to bind a token to, so adding `CsrfGuard` would break anonymous filtering without protecting anything. `CsrfGuard` remains the rule for state-changing actions. The logged-in highlight reads `$user->teamid` only to choose which rows get a CSS class.

### Cache

A `CachedHeadToHeadRecordsRepository` decorator holds one `DatabaseCache` key per filter combination: `h2h_records:{dimension}:{phase}:{scope}`, 24 keys (2 scopes × 3 dimensions × 4 phases), TTL 86400s. A page view reads exactly one key. Keys are warmed by `ibl5/bin/warm-cache`, rebuilt on demand by `ibl5/bin/rebuild-h2h-records-cache`, and refreshed after every import by `RefreshHeadToHeadRecordsStep`.

One blob was rejected because every page load would deserialize all 24 matrices. On-demand computation was rejected because it runs several CTE-joined queries against the full box-score table per public page view. The `current`-scope keys carry no season year: the Updater step rebuilds them after each import, so a stale key at season rollover self-heals within one import.

## Consequences

- Era colors are hand-seeded. A future rebrand needs a new migration row in `ibl_franchise_era_branding`.
- The `current`-scope cache keys are season-agnostic by design, so correctness at rollover depends on `RefreshHeadToHeadRecordsStep` staying in the Updater pipeline.
- The whole `SeriesRecords` namespace is deleted: its classes, its module entry point, and its test directory. The `vw_series_records` view stays, because Standings still reads it.

## Addendum (2026-09-22): SQL sites outside the repository

The `### Security surface` section above omits two SQL sites discovered during the Phase 6 plan-intent fidelity review.

- `modules/HeadToHeadRecords/index.php` decodes the auth cookie via `NukeCompat::cookieDecode($user)` and runs `SELECT teamid FROM \`ibl_team_info\` WHERE gm_username = ? LIMIT 1` with the username bound as `s`. An empty username short-circuits before the prepare call.
- `HeadToHeadRecordsController::lookupOwnerName()` runs `SELECT owner_name FROM \`ibl_team_info\` WHERE teamid = ?` with the teamid bound as `i`. The caller validates `teamid > 0` before dispatching.

Both use `$this->db->prepare()` with `bind_param`. No user-controlled value is interpolated.
