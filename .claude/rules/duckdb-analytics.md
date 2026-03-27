# DuckDB Analytics Layer

## Why DuckDB

MariaDB is the OLTP database — optimized for row-level reads/writes that power the web app. But cross-season analytical queries (TSI progression across 20 seasons, draft class trajectory comparison, roster composition vs win correlation) are slow or impractical in a row-oriented engine.

DuckDB is a columnar OLAP engine that runs on the developer workstation. It imports a snapshot of production data and enables fast aggregations across the full 19-season IBL history (~662K rows). It is **not** a replacement for MariaDB — it's a read-only analytical mirror rebuilt from scratch on each run.

### Origin: JSB Bulk Import Pipeline

The analytics layer is Phase 5 of a 5-phase buildout that imported 19 seasons of historical simulation data from archived JSB backups:

1. **Bulk import infrastructure** — archive extraction, CLI orchestrator (#536)
2. **Per-season PLR snapshots** — player rating snapshots per season (#541)
3. **Draft/Retired/HoF parsers** — `.dra`/`.ret`/`.hof` file parsers (#544)
4. **PLB depth chart history** — `.plb` file parser for 32 teams (#545)
5. **DuckDB analytics layer** — columnar OLAP over all imported data (#546, #549)

## Running Queries

The DuckDB database file lives at `ibl5/analytics/data/ibl_analytics.duckdb` (gitignored). To query it:

```bash
cd ibl5

# Interactive shell
./analytics/duckdb analytics/data/ibl_analytics.duckdb

# Run a named query
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/tsi_progression.sql

# Run an ad-hoc query
./analytics/duckdb analytics/data/ibl_analytics.duckdb -c "SELECT name, career_ppg FROM agg_player_career ORDER BY career_ppg DESC LIMIT 10"
```

**Ad-hoc queries from Claude:** Use the `-c` flag for one-off analysis. For multi-statement or complex queries, write a temp SQL file and pipe it in.

## Rebuilding the Database

```bash
bin/analytics-build              # Export from production MariaDB → CSV → DuckDB (~18s)
bin/analytics-build --local      # Use local Docker MariaDB instead of production
bin/analytics-build --skip-export  # Rebuild DuckDB from existing CSVs (skip MariaDB export)
bin/analytics-build --writeback  # Also push computed data back to MariaDB
```

Rebuild is idempotent — deletes and recreates from scratch. Production export requires `.env` with `REMOTE_HOST` credentials. The `--local` flag forces Docker connection (useful for testing schema changes).

## Available Tables

### Dimensions (reference/lookup)
| Table | Rows | Description |
|-------|------|-------------|
| `dim_player` | 1,605 | Player master with computed TSI bands, current salary |
| `dim_team` | 33 | Team master (city, name, colors) |
| `dim_season` | 20 | Season years with labels ("06-07" format) |
| `dim_franchise_seasons` | 512 | Historical team city/name per season |
| `dim_player_snapshot` | 0* | Per-season TSI snapshots (Phase 2 dependent) |

*Production doesn't have `ibl_plr_snapshots` yet. TSI data falls back to current `dim_player` values.

### Facts (event-level data)
| Table | Rows | Description |
|-------|------|-------------|
| `fact_player_season` | 11,890 | Season stats with computed PPG/RPG/APG/QA + TSI |
| `fact_player_game` | 589,639 | Individual game box scores |
| `fact_team_season` | 512 | Team records, playoff results per season |
| `fact_team_game` | 50,224 | Team-level game box scores |
| `fact_player_awards` | 3,344 | Player awards by season |
| `fact_team_awards` | 132 | Team awards by season |
| `fact_transactions` | 5,561 | Trades, injuries, waivers |
| `fact_allstar_rosters` | 1,140 | All-Star selections |

### Aggregates (pre-computed analytics)
| Table | Rows | Description |
|-------|------|-------------|
| `agg_player_career` | 1,248 | Career totals, averages, peak season |
| `agg_tsi_progression` | 5,520 | Year-over-year rating deltas by development phase |
| `agg_draft_cohort` | 20 | Draft class trajectory comparison |
| `agg_team_season_roster` | 562 | Roster composition metrics per team-season |
| `agg_playoff_predictor` | 562 | Playoff prediction correlation data |

## Named Queries (7 pre-built)

| File | Analysis |
|------|----------|
| `tsi_progression.sql` | Rating deltas by TSI band and development phase |
| `player_development_curves.sql` | Career progression patterns |
| `draft_class_analysis.sql` | Draft cohort trajectories and bust rates |
| `team_build_efficiency.sql` | Roster composition vs winning |
| `salary_efficiency.sql` | Points-per-dollar, salary vs production |
| `playoff_predictors.sql` | What roster metrics predict playoff success |
| `cross_validation.sql` | Data quality cross-checks |

## Key Columns for Analysis

**`fact_player_season`** is the workhorse table. Key computed columns:
- `ppg`, `rpg`, `apg`, `spg`, `bpg`, `topg`, `mpg` — per-game averages
- `fg_pct`, `ft_pct`, `three_pct` — shooting percentages
- `qa` — quality-adjusted metric (rewards production, penalizes inefficiency)
- `tsi_sum`, `tsi_band` — talent+skill+intangibles composite and band (low/mid/high/elite)
- `estimated_age`, `peak`, `age_relative_to_peak` — age relative to peak year
- `salary` — contract salary that season
- Rating columns: `r_2ga`, `r_2gp`, `r_fta`, `r_ftp`, `r_3ga`, `r_3gp`, `r_orb`, `r_drb`, `r_ast`, `r_stl`, `r_blk`, `r_tvr`, `r_oo`, `r_do`, `r_po`, `r_to`, `r_od`, `r_dd`, `r_pd`, `r_td`

**`agg_tsi_progression`** adds year-over-year deltas for all the above, plus `development_phase` (far_from_peak / near_peak / post_peak).

## When to Use DuckDB vs MariaDB

| Question | Use |
|----------|-----|
| "What's the average career PPG for elite TSI players?" | DuckDB |
| "Show me the current roster for the Spurs" | MariaDB (`bin/db-query`) |
| "How do draft classes from the 90s compare to the 2000s?" | DuckDB |
| "Update a player's contract" | MariaDB (write operation) |
| "What roster composition predicts playoff success?" | DuckDB |
| "Which players are on waivers right now?" | MariaDB (current state) |

**Rule of thumb:** Current state and writes → MariaDB. Historical analysis across seasons → DuckDB.
