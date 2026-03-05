-- Add clinchedLeague column to ibl_standings
-- Tracks whether a team has clinched the best league-wide record (W indicator)

ALTER TABLE ibl_standings
ADD COLUMN IF NOT EXISTS clinchedLeague TINYINT(1) DEFAULT NULL AFTER clinchedPlayoffs;

-- Update vw_team_standings view to include the new column
CREATE OR REPLACE SQL SECURITY INVOKER VIEW vw_team_standings AS
SELECT
    t.uuid AS team_uuid,
    t.teamid,
    t.team_city,
    t.team_name,
    CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
    t.owner_name,
    s.leagueRecord AS league_record,
    s.pct AS win_percentage,
    s.conference,
    s.confRecord AS conference_record,
    s.confGB AS conference_games_back,
    s.division,
    s.divRecord AS division_record,
    s.divGB AS division_games_back,
    s.homeWins AS home_wins,
    s.homeLosses AS home_losses,
    s.awayWins AS away_wins,
    s.awayLosses AS away_losses,
    CONCAT(s.homeWins, '-', s.homeLosses) AS home_record,
    CONCAT(s.awayWins, '-', s.awayLosses) AS away_record,
    s.gamesUnplayed AS games_remaining,
    s.confWins AS conference_wins,
    s.confLosses AS conference_losses,
    s.divWins AS division_wins,
    s.divLosses AS division_losses,
    s.clinchedConference AS clinched_conference,
    s.clinchedDivision AS clinched_division,
    s.clinchedPlayoffs AS clinched_playoffs,
    s.clinchedLeague AS clinched_league,
    s.confMagicNumber AS conference_magic_number,
    s.divMagicNumber AS division_magic_number,
    s.created_at,
    s.updated_at
FROM ibl_team_info t
JOIN ibl_standings s ON t.teamid = s.tid;
