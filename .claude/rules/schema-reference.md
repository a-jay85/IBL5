---
paths: ibl5/schema.sql
---

# Database Schema Reference

## Key Tables

| Category | Tables |
|----------|--------|
| Players | `ibl_plr` (main), `ibl_hist` (history), `ibl_plr_chunk` (stats) |
| Teams | `ibl_team_info`, `ibl_standings` |
| Games | `ibl_schedule`, `ibl_box_scores`, `ibl_box_scores_teams` |
| Contracts | `ibl_fa_offers`, `ibl_trade_*` tables |
| Draft | `ibl_draft`, `ibl_draft_picks` |
| Users | `nuke_users` (`username`, `user_ibl_team`) |

## Quick Table Reference

| Purpose | Table | Key Fields |
|---------|-------|------------|
| Players | `ibl_plr` | `pid`, `tid`, `name`, `cy`, `cy1-cy6` |
| Teams | `ibl_team_info` | `teamid`, `team_name` |
| Users | `nuke_users` | `username`, `user_ibl_team` |
| History | `ibl_hist` | Historical player stats |
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

## Foreign Key Relationships (24)
- `ibl_hist.pid` -> `ibl_plr.pid`
- `ibl_draft_picks.tid` -> `ibl_team_info.teamid`
- `ibl_box_scores.gameid` -> `ibl_schedule.Date`

## Engine Status
- **InnoDB (51):** All critical IBL tables - ACID transactions
- **MyISAM (84):** Legacy PhpNuke CMS tables

## Best Practices
- Use prepared statements with mysqli
- Leverage existing indexes for WHERE/JOIN
- Use database views for complex API queries
- Migrations go in `/ibl5/migrations/`
