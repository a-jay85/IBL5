-- Migration 031: Add indexes to improve database view performance

-- Opponent lookup index for ibl_team_defense_stats view
CREATE INDEX IF NOT EXISTS idx_date_visitor_home_gotd
    ON ibl_box_scores_teams (Date, visitorTeamID, homeTeamID, gameOfThatDay);

-- Team awards name index for GROUP BY / WHERE patterns
CREATE INDEX IF NOT EXISTS idx_name ON ibl_team_awards (name);
