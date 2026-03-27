# IBL5 Analytics (DuckDB)

Columnar analytics layer over IBL5's MariaDB data. Uses DuckDB for fast cross-season aggregations, TSI progression research, and roster composition analysis.

## Setup

```bash
bin/analytics-setup        # Download DuckDB CLI binary (~25MB)
bin/analytics-build        # Export MariaDB → CSV → DuckDB (full rebuild)
```

Requires Docker MariaDB running (`docker compose up -d`).

## Usage

### Interactive

```bash
./analytics/duckdb analytics/data/ibl_analytics.duckdb
```

### Named Queries

```bash
cd ibl5
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/tsi_progression.sql
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/player_development_curves.sql
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/draft_class_analysis.sql
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/team_build_efficiency.sql
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/salary_efficiency.sql
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/playoff_predictors.sql
./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/cross_validation.sql
```

### Write-Back to MariaDB

```bash
bin/analytics-build --writeback
```

Exports computed TSI progression bands and player peak data back to MariaDB for use by the PHP web app.

## Directory Structure

```
analytics/
  schema/
    01_dimensions.sql    # dim_player, dim_team, dim_season
    02_facts.sql         # fact_player_season, fact_player_game, fact_team_season
    03_aggregates.sql    # agg_tsi_progression, agg_player_career, agg_draft_cohort
    04_validation.sql    # Data quality assertions
  queries/               # Named analytical queries (one per use case)
  data/                  # CSV exports + DuckDB file (gitignored)
    writeback/           # CSVs for MariaDB write-back (gitignored)
  duckdb                 # DuckDB CLI binary (gitignored)
```

## Rebuild

Full rebuild is idempotent and fast (<10s for ~250K rows):

```bash
bin/analytics-build                 # Full rebuild
bin/analytics-build --skip-export   # Rebuild DuckDB only (reuse existing CSVs)
bin/analytics-build --writeback     # Full rebuild + write back to MariaDB
```

## Adding New Queries

1. Create a `.sql` file in `analytics/queries/`
2. Reference tables from the schema files (dimensions, facts, aggregates)
3. Run: `./analytics/duckdb analytics/data/ibl_analytics.duckdb < analytics/queries/your_query.sql`

## Data Flow

```
MariaDB (OLTP)  →  CSV export  →  DuckDB (OLAP)  →  CSV write-back  →  MariaDB
                bin/analytics-export    bin/analytics-build --writeback
```

## Tables

### Dimensions
| Table | Source | Description |
|-------|--------|-------------|
| dim_player | ibl_plr | Player master with TSI bands |
| dim_team | ibl_team_info | Team master |
| dim_season | ibl_hist (distinct years) | Season labels |
| dim_player_snapshot | ibl_plr_snapshots | Per-season TSI (Phase 2) |
| dim_franchise_seasons | ibl_franchise_seasons | Historical team names |

### Facts
| Table | Source | Description |
|-------|--------|-------------|
| fact_player_season | ibl_hist | Season stats + computed PPG/RPG/APG/QA |
| fact_player_game | ibl_box_scores | Game-level box scores |
| fact_team_season | ibl_jsb_history | Team records + playoff results |
| fact_team_game | ibl_box_scores_teams | Team game box scores |
| fact_player_awards | ibl_awards | Player awards |
| fact_team_awards | ibl_team_awards | Team awards |
| fact_transactions | ibl_jsb_transactions | Trades/injuries/waivers |
| fact_allstar_rosters | ibl_jsb_allstar_rosters | All-Star selections |

### Aggregates
| Table | Description |
|-------|-------------|
| agg_tsi_progression | Year-over-year rating deltas by TSI band |
| agg_player_career | Career totals, averages, peak season |
| agg_draft_cohort | Draft class trajectory comparison |
| agg_team_season_roster | Roster composition metrics per team-season |
| agg_playoff_predictor | Playoff prediction correlation data |

### MariaDB Write-Back Tables
| Table | Description |
|-------|-------------|
| ibl_analytics_tsi_bands | TSI progression per player-season |
| ibl_analytics_player_peaks | Career peak data per player |
