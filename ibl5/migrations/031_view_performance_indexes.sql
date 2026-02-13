-- Migration 031: Add indexes to improve database view performance
--
-- Based on DATABASE_VIEW_AUDIT.md findings.
--
-- 1. Composite index for ibl_team_defense_stats self-join opponent lookup.
--    The self-join matches on (Date, visitorTeamID, homeTeamID, gameOfThatDay)
--    but the optimizer can only use idx_date (single column), achieving 0.40%
--    filter rate. This composite index covers the full join condition without
--    requiring game_type as the leading column (unlike idx_gt_date_teams).
--
-- 2. Index on ibl_team_awards(name) for GROUP BY name patterns used in
--    vw_team_awards, vw_franchise_summary, and RecordHoldersRepository.
--    Table is small (126 rows) so impact is minimal but avoids full scan.

-- Opponent lookup index for ibl_team_defense_stats view
CREATE INDEX idx_date_visitor_home_gotd
    ON ibl_box_scores_teams (Date, visitorTeamID, homeTeamID, gameOfThatDay);

-- Team awards name index for GROUP BY / WHERE patterns
CREATE INDEX idx_name ON ibl_team_awards (name);
