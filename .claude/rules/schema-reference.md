---
description: Canonical database schema reference derived from migrations.
paths: ibl5/migrations/000_baseline_schema.sql
last_verified: 2026-06-11
---

# Database Schema Reference

## Key Tables

| Category | Tables |
|----------|--------|
| Players | `ibl_plr` (main), `ibl_hist` (VIEW over `ibl_plr_snapshots`), `ibl_plr_snapshots` (per-season ratings/stats) |
| Teams | `ibl_team_info`, `ibl_standings` |
| Games | `ibl_schedule`, `ibl_box_scores`, `ibl_box_scores_teams` |
| Contracts | `ibl_fa_offers`, `ibl_trade_*` tables |
| Draft | `ibl_draft`, `ibl_draft_picks` |
| Users | `auth_users` (`username`, `password`), `ibl_team_info` (`gm_username` — user-to-team mapping) |

## Quick Table Reference

| Purpose | Table | Key Fields |
|---------|-------|------------|
| Players | `ibl_plr` | `pid`, `tid`, `name`, `cy`, `cy1-cy6` |
| Teams | `ibl_team_info` | `teamid`, `team_name`, `gm_username` |
| Users | `auth_users` | `username`, `email`, `password` (team mapping via `ibl_team_info.gm_username`) |
| History | `ibl_hist` (VIEW) | Historical player stats (sourced from `ibl_plr_snapshots`) |
| Schedule | `ibl_schedule` | Game schedule |

## Common Query Patterns

### Player with Current Stats
```php
$query = "SELECT p.*, h.* FROM ibl_plr p
          LEFT JOIN ibl_hist h ON p.pid = h.pid
          WHERE p.pid = ? AND h.year = ?";
```

### Team Roster
```php
$query = "SELECT * FROM ibl_plr WHERE tid = ? ORDER BY ordinal";
```

### Using Database Views (API)
```php
$query = "SELECT * FROM vw_player_current WHERE uuid = ?";
```

## API-Ready Features
- **Timestamps:** 19 tables have `created_at`, `updated_at`
- **UUIDs:** 5 critical tables for secure public IDs
- **Views:** `vw_player_current`, `vw_team_standings`, `vw_game_schedule`, `vw_player_stats_summary`, `vw_trade_history`, `vw_team_awards`, `vw_franchise_summary`

## Foreign Key Relationships
- `ibl_draft_picks.tid` -> `ibl_team_info.teamid`
- `ibl_box_scores.gameid` -> `ibl_schedule.Date`

## Engine Status
- **All tables are InnoDB** — ACID transactions, full rollback support in tests.

## Best Practices
- Use prepared statements with mysqli
- Leverage existing indexes for WHERE/JOIN
- Use database views for complex API queries
- Migrations go in `/ibl5/migrations/`
- **Game-count filters:** when counting games from `ibl_box_scores` vs `ibl_plr`, apply the All-Star / Rookie-game / finals-phase exclusions documented in `ibl5/docs/PLR_VS_BOXSCORES_ANALYSIS.md` — omitting them overcounts games (~12% in sampled seasons).
