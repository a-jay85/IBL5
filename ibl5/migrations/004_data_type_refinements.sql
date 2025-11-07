-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 4: Data Type Refinements
-- ============================================================================
-- This migration implements remaining data type optimizations:
-- 1. Complete data type optimizations for all tables (Priority 2.3)
-- 2. Implement ENUM types for fixed value lists (positions, conferences, etc.)
-- 3. Add CHECK constraints for data validation (MySQL 8.0+)
--
-- PREREQUISITES:
-- - Phase 1, 2, and 3 migrations must be completed
-- - InnoDB tables with foreign keys and timestamps in place
-- - MySQL 8.0 or higher (for CHECK constraints)
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: 2-3 hours depending on data size
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- Verify MySQL version (8.0+ required for CHECK constraints)
SELECT 'Checking MySQL version (8.0+ required for CHECK constraints)...' AS message;
SELECT VERSION() AS mysql_version;

-- ============================================================================
-- PART 1: DATA TYPE OPTIMIZATIONS
-- ============================================================================
-- Optimize integer sizes based on actual data ranges
-- Reduces storage requirements and improves query performance

-- ---------------------------------------------------------------------------
-- Player Table (ibl_plr) - Core optimizations
-- ---------------------------------------------------------------------------
-- Note: Age and peak are already TINYINT UNSIGNED from Phase 1
-- Note: active is already TINYINT(1) from Phase 1

-- Optimize statistics fields that will never exceed certain ranges
-- Games played rarely exceeds 32,767 per season
ALTER TABLE ibl_plr
  MODIFY stats_gs SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Games started',
  MODIFY stats_gm SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Games played';

-- Minutes played per season typically maxes around ~3,500
-- Using MEDIUMINT UNSIGNED (max 16,777,215) for safety
ALTER TABLE ibl_plr
  MODIFY stats_min MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Total minutes played';

-- Individual game statistics fields (made/attempted)
-- These accumulate over seasons but rarely exceed 5,000
ALTER TABLE ibl_plr
  MODIFY stats_fgm SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Field goals made',
  MODIFY stats_fga SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Field goals attempted',
  MODIFY stats_ftm SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Free throws made',
  MODIFY stats_fta SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Free throws attempted',
  MODIFY stats_3gm SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Three pointers made',
  MODIFY stats_3ga SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Three pointers attempted',
  MODIFY stats_orb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Offensive rebounds',
  MODIFY stats_drb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Defensive rebounds',
  MODIFY stats_ast SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Assists',
  MODIFY stats_stl SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Steals',
  MODIFY stats_to SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Turnovers',
  MODIFY stats_blk SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Blocks',
  MODIFY stats_pf SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Personal fouls';

-- Ratings typically 0-100 scale
ALTER TABLE ibl_plr
  MODIFY sta TINYINT UNSIGNED DEFAULT 0 COMMENT 'Stamina rating',
  MODIFY oo TINYINT UNSIGNED DEFAULT 0 COMMENT 'Outside offense rating',
  MODIFY od TINYINT UNSIGNED DEFAULT 0 COMMENT 'Outside defense rating',
  MODIFY do TINYINT UNSIGNED DEFAULT 0 COMMENT 'Inside offense rating',
  MODIFY dd TINYINT UNSIGNED DEFAULT 0 COMMENT 'Inside defense rating',
  MODIFY po TINYINT UNSIGNED DEFAULT 0 COMMENT 'Post offense rating',
  MODIFY pd TINYINT UNSIGNED DEFAULT 0 COMMENT 'Post defense rating',
  MODIFY to TINYINT UNSIGNED DEFAULT 0 COMMENT 'Transition offense rating',
  MODIFY td TINYINT UNSIGNED DEFAULT 0 COMMENT 'Transition defense rating',
  MODIFY talent TINYINT UNSIGNED DEFAULT 0 COMMENT 'Overall talent rating',
  MODIFY skill TINYINT UNSIGNED DEFAULT 0 COMMENT 'Skill rating',
  MODIFY intangibles TINYINT UNSIGNED DEFAULT 0 COMMENT 'Intangibles rating';

-- Depth chart positions 0-15
ALTER TABLE ibl_plr
  MODIFY PGDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'Point guard depth',
  MODIFY SGDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'Shooting guard depth',
  MODIFY SFDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'Small forward depth',
  MODIFY PFDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'Power forward depth',
  MODIFY CDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'Center depth',
  MODIFY dc_PGDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC point guard depth',
  MODIFY dc_SGDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC shooting guard depth',
  MODIFY dc_SFDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC small forward depth',
  MODIFY dc_PFDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC power forward depth',
  MODIFY dc_CDepth TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC center depth';

-- Depth chart attributes 0-100
ALTER TABLE ibl_plr
  MODIFY dc_active TINYINT UNSIGNED DEFAULT 1 COMMENT 'DC active flag',
  MODIFY dc_minutes TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC minutes',
  MODIFY dc_of TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC offensive focus',
  MODIFY dc_df TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC defensive focus',
  MODIFY dc_oi TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC offensive importance',
  MODIFY dc_di TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC defensive importance',
  MODIFY dc_bh TINYINT UNSIGNED DEFAULT 0 COMMENT 'DC ball handling';

-- Draft information
ALTER TABLE ibl_plr
  MODIFY draftround TINYINT UNSIGNED DEFAULT 0 COMMENT 'Draft round (1-7)',
  MODIFY draftpickno TINYINT UNSIGNED DEFAULT 0 COMMENT 'Pick number in round',
  MODIFY draftyear SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Draft year';

-- Experience years
ALTER TABLE ibl_plr
  MODIFY exp TINYINT UNSIGNED DEFAULT 0 COMMENT 'Years of experience';

-- Career totals (can be larger)
-- Keep as INT UNSIGNED or MEDIUMINT UNSIGNED for career accumulation
ALTER TABLE ibl_plr
  MODIFY car_gm SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Career games',
  MODIFY car_min MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career minutes',
  MODIFY car_fgm MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career FGM',
  MODIFY car_fga MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career FGA',
  MODIFY car_ftm MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career FTM',
  MODIFY car_fta MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career FTA',
  MODIFY car_tgm MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career 3PM',
  MODIFY car_tga MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career 3PA',
  MODIFY car_orb MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career ORB',
  MODIFY car_drb MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career DRB',
  MODIFY car_reb MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career total rebounds',
  MODIFY car_ast MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career assists',
  MODIFY car_stl MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career steals',
  MODIFY car_to MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career turnovers',
  MODIFY car_blk MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career blocks',
  MODIFY car_pf MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career fouls',
  MODIFY car_pts MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career points',
  MODIFY car_playoff_min MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career playoff minutes',
  MODIFY car_preseason_min MEDIUMINT UNSIGNED DEFAULT 0 COMMENT 'Career preseason minutes';

-- Showcase stats
ALTER TABLE ibl_plr
  MODIFY sh_pts SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase points',
  MODIFY sh_reb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase rebounds',
  MODIFY sh_ast SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase assists',
  MODIFY sh_stl SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase steals',
  MODIFY sh_blk SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase blocks',
  MODIFY sp_pts SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase playoff points',
  MODIFY sp_reb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase playoff rebounds',
  MODIFY sp_ast SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase playoff assists',
  MODIFY sp_stl SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase playoff steals',
  MODIFY sp_blk SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Showcase playoff blocks',
  MODIFY ch_pts SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship points',
  MODIFY ch_reb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship rebounds',
  MODIFY ch_ast SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship assists',
  MODIFY ch_stl SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship steals',
  MODIFY ch_blk SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship blocks',
  MODIFY cp_pts SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship playoff points',
  MODIFY cp_reb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship playoff rebounds',
  MODIFY cp_ast SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship playoff assists',
  MODIFY cp_stl SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship playoff steals',
  MODIFY cp_blk SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Championship playoff blocks';

-- Double/triple doubles
ALTER TABLE ibl_plr
  MODIFY s_dd SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Season double doubles',
  MODIFY s_td SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Season triple doubles',
  MODIFY c_dd SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Career double doubles',
  MODIFY c_td SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Career triple doubles';

-- Ratings and rankings
ALTER TABLE ibl_plr
  MODIFY r_fga SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank FGA',
  MODIFY r_fgp SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank FG%',
  MODIFY r_fta SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank FTA',
  MODIFY r_ftp SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank FT%',
  MODIFY r_tga SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank 3PA',
  MODIFY r_tgp SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank 3P%',
  MODIFY r_orb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank ORB',
  MODIFY r_drb SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank DRB',
  MODIFY r_ast SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank AST',
  MODIFY r_stl SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank STL',
  MODIFY r_to SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank TO',
  MODIFY r_blk SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank BLK',
  MODIFY r_foul SMALLINT UNSIGNED DEFAULT 0 COMMENT 'Rank fouls';

-- ---------------------------------------------------------------------------
-- Historical Stats Tables - Similar optimizations
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_hist
  MODIFY year SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Season year',
  MODIFY games SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Games played',
  MODIFY minutes MEDIUMINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Minutes played',
  MODIFY fgm SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Field goals made',
  MODIFY fga SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Field goals attempted',
  MODIFY ftm SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Free throws made',
  MODIFY fta SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Free throws attempted',
  MODIFY tgm SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Three pointers made',
  MODIFY tga SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Three pointers attempted',
  MODIFY orb SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Offensive rebounds',
  MODIFY reb SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total rebounds',
  MODIFY ast SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Assists',
  MODIFY stl SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Steals',
  MODIFY tvr SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Turnovers',
  MODIFY blk SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Blocks',
  MODIFY pf SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Personal fouls',
  MODIFY pts SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Points';

-- ---------------------------------------------------------------------------
-- Standings Table - Optimize win/loss counts and records
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_standings
  MODIFY gamesUnplayed TINYINT UNSIGNED DEFAULT NULL COMMENT 'Games remaining',
  MODIFY confWins TINYINT UNSIGNED DEFAULT NULL COMMENT 'Conference wins',
  MODIFY confLosses TINYINT UNSIGNED DEFAULT NULL COMMENT 'Conference losses',
  MODIFY divWins TINYINT UNSIGNED DEFAULT NULL COMMENT 'Division wins',
  MODIFY divLosses TINYINT UNSIGNED DEFAULT NULL COMMENT 'Division losses',
  MODIFY homeWins TINYINT UNSIGNED DEFAULT NULL COMMENT 'Home wins',
  MODIFY homeLosses TINYINT UNSIGNED DEFAULT NULL COMMENT 'Home losses',
  MODIFY awayWins TINYINT UNSIGNED DEFAULT NULL COMMENT 'Away wins',
  MODIFY awayLosses TINYINT UNSIGNED DEFAULT NULL COMMENT 'Away losses',
  MODIFY confMagicNumber TINYINT DEFAULT NULL COMMENT 'Conf magic number',
  MODIFY divMagicNumber TINYINT DEFAULT NULL COMMENT 'Div magic number';

-- ---------------------------------------------------------------------------
-- Box Scores - Game statistics
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores
  MODIFY gameMIN TINYINT UNSIGNED DEFAULT NULL COMMENT 'Minutes played',
  MODIFY game2GM TINYINT UNSIGNED DEFAULT NULL COMMENT 'Field goals made',
  MODIFY game2GA TINYINT UNSIGNED DEFAULT NULL COMMENT 'Field goals attempted',
  MODIFY gameFTM TINYINT UNSIGNED DEFAULT NULL COMMENT 'Free throws made',
  MODIFY gameFTA TINYINT UNSIGNED DEFAULT NULL COMMENT 'Free throws attempted',
  MODIFY game3GM TINYINT UNSIGNED DEFAULT NULL COMMENT 'Three pointers made',
  MODIFY game3GA TINYINT UNSIGNED DEFAULT NULL COMMENT 'Three pointers attempted',
  MODIFY gameORB TINYINT UNSIGNED DEFAULT NULL COMMENT 'Offensive rebounds',
  MODIFY gameDRB TINYINT UNSIGNED DEFAULT NULL COMMENT 'Defensive rebounds',
  MODIFY gameAST TINYINT UNSIGNED DEFAULT NULL COMMENT 'Assists',
  MODIFY gameSTL TINYINT UNSIGNED DEFAULT NULL COMMENT 'Steals',
  MODIFY gameTOV TINYINT UNSIGNED DEFAULT NULL COMMENT 'Turnovers',
  MODIFY gameBLK TINYINT UNSIGNED DEFAULT NULL COMMENT 'Blocks',
  MODIFY gamePF TINYINT UNSIGNED DEFAULT NULL COMMENT 'Personal fouls';

-- ---------------------------------------------------------------------------
-- Team Stats Tables
-- ---------------------------------------------------------------------------
-- Note: ibl_team_win_loss has year, wins, losses as VARCHAR types
-- Original migration also had case inconsistencies (Year vs year, Wins vs wins, Losses vs losses)
-- and referenced non-existent SeasonType column
-- Optimization would require data migration from VARCHAR to numeric types
-- Commenting out for now - would need separate data migration
-- ALTER TABLE ibl_team_win_loss
--   MODIFY year SMALLINT UNSIGNED NOT NULL COMMENT 'Season year',
--   MODIFY wins TINYINT UNSIGNED DEFAULT 0 COMMENT 'Wins',
--   MODIFY losses TINYINT UNSIGNED DEFAULT 0 COMMENT 'Losses';

-- ---------------------------------------------------------------------------
-- Draft Tables
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_draft
  MODIFY year SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Draft year',
  MODIFY round TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Draft round',
  MODIFY pick TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Pick number';

ALTER TABLE ibl_draft_class
  MODIFY age TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Player age',
  MODIFY fga TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FG attempts rating',
  MODIFY fgp TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FG percentage rating',
  MODIFY fta TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FT attempts rating',
  MODIFY ftp TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'FT percentage rating',
  MODIFY tga TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '3P attempts rating',
  MODIFY tgp TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '3P percentage rating',
  MODIFY orb TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Off rebounds rating',
  MODIFY drb TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Def rebounds rating',
  MODIFY ast TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Assists rating',
  MODIFY stl TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Steals rating',
  MODIFY tvr TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Turnovers rating',
  MODIFY blk TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Blocks rating',
  MODIFY offo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Off offense rating',
  MODIFY offd TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Off defense rating',
  MODIFY offp TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Off post rating',
  MODIFY offt TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Off transition rating',
  MODIFY defo TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Def offense rating',
  MODIFY defd TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Def defense rating',
  MODIFY defp TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Def post rating',
  MODIFY deft TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Def transition rating';

-- Note: ibl_draft_picks.year is VARCHAR(4) and round is CHAR(1)
-- ibl_draft_picks does not have a 'pick' column
-- Optimization would require data migration from VARCHAR/CHAR to numeric types
-- ALTER TABLE ibl_draft_picks
--   MODIFY round TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Draft round',
--   MODIFY year SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Draft year';

-- ---------------------------------------------------------------------------
-- Schedule Table
-- ---------------------------------------------------------------------------
-- Note: ibl_schedule does not have 'Day' or 'Neutral' columns
-- Removed those columns from optimization
ALTER TABLE ibl_schedule
  MODIFY Year SMALLINT UNSIGNED NOT NULL COMMENT 'Season year',
  MODIFY Visitor SMALLINT UNSIGNED NOT NULL COMMENT 'Visiting team ID',
  MODIFY Home SMALLINT UNSIGNED NOT NULL COMMENT 'Home team ID',
  MODIFY VScore TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Visitor score',
  MODIFY HScore TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Home score';

-- ---------------------------------------------------------------------------
-- Power Rankings
-- ---------------------------------------------------------------------------
-- Note: Column is 'ranking' not 'powerRanking' (DECIMAL(6,1) in schema)
-- Optimization would change from DECIMAL to TINYINT - would lose precision
-- Commenting out to preserve decimal precision
-- ALTER TABLE ibl_power
--   MODIFY ranking TINYINT UNSIGNED DEFAULT NULL COMMENT 'Power ranking (1-30)';

-- ---------------------------------------------------------------------------
-- Playoff Results
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_playoff_results
  MODIFY year SMALLINT UNSIGNED NOT NULL COMMENT 'Playoff year',
  MODIFY round TINYINT UNSIGNED NOT NULL COMMENT 'Playoff round';

-- ---------------------------------------------------------------------------
-- Team History
-- ---------------------------------------------------------------------------
-- Note: ibl_team_history does not have statistical columns like Games, Minutes, FieldGoalsMade, etc.
-- The actual table has: teamid, team_city, team_name, color1, color2, depth, sim_depth,
-- asg_vote, eoy_vote, totwins, totloss, winpct, playoffs, div_titles, conf_titles,
-- ibl_titles, heat_titles
-- Removing this entire section as the columns don't exist
-- If team historical statistics are stored elsewhere (e.g., ibl_box_scores_teams),
-- those tables should be optimized instead

-- ============================================================================
-- PART 2: IMPLEMENT ENUM TYPES FOR FIXED VALUE LISTS
-- ============================================================================
-- Use ENUM for columns with a fixed set of possible values
-- Provides data validation and reduces storage

-- ---------------------------------------------------------------------------
-- Player Position (ibl_plr)
-- ---------------------------------------------------------------------------
-- Standard basketball positions plus combination positions
ALTER TABLE ibl_plr
  MODIFY pos ENUM('PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', '') 
  NOT NULL DEFAULT '' 
  COMMENT 'Player position';

-- ---------------------------------------------------------------------------
-- Conference (ibl_standings)
-- ---------------------------------------------------------------------------
-- Eastern or Western conference
ALTER TABLE ibl_standings
  MODIFY conference ENUM('Eastern', 'Western', '') 
  DEFAULT '' 
  COMMENT 'Conference affiliation';

-- ---------------------------------------------------------------------------
-- Draft Position (ibl_draft_class)
-- ---------------------------------------------------------------------------
-- Draft class position designation
ALTER TABLE ibl_draft_class
  MODIFY pos ENUM('PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', '') 
  NOT NULL DEFAULT '' 
  COMMENT 'Draft prospect position';

-- ============================================================================
-- PART 3: ADD CHECK CONSTRAINTS FOR DATA VALIDATION
-- ============================================================================
-- MySQL 8.0+ supports CHECK constraints for data integrity
-- These prevent invalid data from being inserted

-- ---------------------------------------------------------------------------
-- Player Age Constraints (ibl_plr)
-- ---------------------------------------------------------------------------
-- NOTE: can't run these because there are entries where age is irrelevant and blank

-- Players must be between 18 and 50 years old
-- ALTER TABLE ibl_plr
--   ADD CONSTRAINT chk_plr_age 
--   CHECK (age IS NULL OR (age >= 18 AND age <= 50));

-- Peak age should be >= current age (you can't have peaked before your current age)
-- ALTER TABLE ibl_plr
--   ADD CONSTRAINT chk_plr_peak 
--   CHECK (peak IS NULL OR age IS NULL OR peak >= age);

-- ---------------------------------------------------------------------------
-- Standings Constraints (ibl_standings)
-- ---------------------------------------------------------------------------
-- Winning percentage must be between 0.000 and 1.000
ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_pct 
  CHECK (pct IS NULL OR (pct >= 0.000 AND pct <= 1.000));

-- Games unplayed cannot be negative and should not exceed season length (82)
ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_games_unplayed 
  CHECK (gamesUnplayed IS NULL OR (gamesUnplayed >= 0 AND gamesUnplayed <= 82));

-- Win/loss totals should be reasonable (max 82 for regular season)
ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_conf_wins 
  CHECK (confWins IS NULL OR confWins <= 82);

ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_conf_losses 
  CHECK (confLosses IS NULL OR confLosses <= 82);

ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_home_wins 
  CHECK (homeWins IS NULL OR homeWins <= 41);

ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_home_losses 
  CHECK (homeLosses IS NULL OR homeLosses <= 41);

ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_away_wins 
  CHECK (awayWins IS NULL OR awayWins <= 41);

ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_standings_away_losses 
  CHECK (awayLosses IS NULL OR awayLosses <= 41);

-- ---------------------------------------------------------------------------
-- Player Ratings Constraints (ibl_plr)
-- ---------------------------------------------------------------------------
-- Ratings should be 0-100 scale
ALTER TABLE ibl_plr
  ADD CONSTRAINT chk_plr_sta CHECK (sta >= 0 AND sta <= 100),
  ADD CONSTRAINT chk_plr_oo CHECK (oo >= 1 AND oo <= 9),
  ADD CONSTRAINT chk_plr_od CHECK (od >= 1 AND od <= 9),
  ADD CONSTRAINT chk_plr_do CHECK (`do` >= 1 AND `do` <= 9),
  ADD CONSTRAINT chk_plr_dd CHECK (dd >= 1 AND dd <= 9),
  ADD CONSTRAINT chk_plr_po CHECK (po >= 1 AND po <= 9),
  ADD CONSTRAINT chk_plr_pd CHECK (pd >= 1 AND pd <= 9),
  ADD CONSTRAINT chk_plr_to CHECK (`to` >= 1 AND `to` <= 9),
  ADD CONSTRAINT chk_plr_td CHECK (td >= 1 AND td <= 9),
  ADD CONSTRAINT chk_plr_talent CHECK (talent >= 1 AND talent <= 5),
  ADD CONSTRAINT chk_plr_skill CHECK (skill >= 1 AND skill <= 5),
  ADD CONSTRAINT chk_plr_intangibles CHECK (intangibles >= 1 AND intangibles <= 5);

-- ---------------------------------------------------------------------------
-- Box Score Minutes Constraint
-- ---------------------------------------------------------------------------
-- Minutes in a game cannot exceed 48 (regulation) + ~15 overtimes (max realistic)
ALTER TABLE ibl_box_scores
  ADD CONSTRAINT chk_box_minutes 
  CHECK (gameMIN IS NULL OR (gameMIN >= 0 AND gameMIN <= 70));

-- ---------------------------------------------------------------------------
-- Schedule Team ID and Scores Constraints
-- ---------------------------------------------------------------------------
-- Team IDs must be between 1 and 32 (maximum teams in league)
ALTER TABLE ibl_schedule
  ADD CONSTRAINT chk_schedule_visitor_id 
  CHECK (Visitor >= 1 AND Visitor <= 32);

ALTER TABLE ibl_schedule
  ADD CONSTRAINT chk_schedule_home_id 
  CHECK (Home >= 1 AND Home <= 32);

-- Scores should be reasonable (0-200, accounting for rare high-scoring games)
ALTER TABLE ibl_schedule
  ADD CONSTRAINT chk_schedule_vscore 
  CHECK (VScore >= 0 AND VScore <= 200);

ALTER TABLE ibl_schedule
  ADD CONSTRAINT chk_schedule_hscore 
  CHECK (HScore >= 0 AND HScore <= 200);

-- ---------------------------------------------------------------------------
-- Draft Round and Pick Constraints
-- ---------------------------------------------------------------------------
-- Draft rounds typically 1-7
ALTER TABLE ibl_draft
  ADD CONSTRAINT chk_draft_round 
  CHECK (round >= 0 AND round <= 7);

-- Draft picks 1-30 per round
ALTER TABLE ibl_draft
  ADD CONSTRAINT chk_draft_pick 
  CHECK (pick >= 0 AND pick <= 32);

-- Note: ibl_draft_picks.round is CHAR(1), not numeric - CHECK constraint won't work
-- Commenting out these constraints as they reference non-existent or incompatible columns
-- ALTER TABLE ibl_draft_picks
--   ADD CONSTRAINT chk_draft_picks_round 
--   CHECK (round >= 0 AND round <= 7);
--
-- ALTER TABLE ibl_draft_picks
--   ADD CONSTRAINT chk_draft_picks_pick 
--   CHECK (pick >= 0 AND pick <= 32);

-- ---------------------------------------------------------------------------
-- Power Rankings Constraint
-- ---------------------------------------------------------------------------
-- Note: Column is 'ranking' not 'powerRanking'
-- ranking is DECIMAL(6,1) in schema, so using decimal literals in constraint
-- Power ranking should be 1-32 (maximum teams in league)
ALTER TABLE ibl_power
  ADD CONSTRAINT chk_power_ranking 
  CHECK (ranking IS NULL OR (ranking >= 1.0 AND ranking <= 32.0));

-- ---------------------------------------------------------------------------
-- Player Statistics Constraints
-- ---------------------------------------------------------------------------
-- Field goal percentage components: made <= attempted
-- Note: These are checked at application level since they involve multiple columns
-- Documented here for reference:
-- - stats_fgm <= stats_fga (field goals made <= attempted)
-- - stats_ftm <= stats_fta (free throws made <= attempted)
-- - stats_3gm <= stats_3ga (three pointers made <= attempted)

-- ---------------------------------------------------------------------------
-- Contract Value Constraints
-- ---------------------------------------------------------------------------
-- Salary should be non-negative and reasonable (max ~50M per year)
ALTER TABLE ibl_plr
  ADD CONSTRAINT chk_plr_cy CHECK (cy >= 0 AND cy <= 50000000),
  ADD CONSTRAINT chk_plr_cyt CHECK (cyt >= 0 AND cyt <= 50000000),
  ADD CONSTRAINT chk_plr_cy1 CHECK (cy1 >= 0 AND cy1 <= 50000000),
  ADD CONSTRAINT chk_plr_cy2 CHECK (cy2 >= 0 AND cy2 <= 50000000),
  ADD CONSTRAINT chk_plr_cy3 CHECK (cy3 >= 0 AND cy3 <= 50000000),
  ADD CONSTRAINT chk_plr_cy4 CHECK (cy4 >= 0 AND cy4 <= 50000000),
  ADD CONSTRAINT chk_plr_cy5 CHECK (cy5 >= 0 AND cy5 <= 50000000),
  ADD CONSTRAINT chk_plr_cy6 CHECK (cy6 >= 0 AND cy6 <= 50000000);

-- ---------------------------------------------------------------------------
-- Team ID Constraints
-- ---------------------------------------------------------------------------
-- Team IDs must be between 0 (free agent) and 32 (maximum teams in league)
ALTER TABLE ibl_plr
  ADD CONSTRAINT chk_plr_tid 
  CHECK (tid >= 0 AND tid <= 32);

-- ============================================================================
-- PART 4: ADD NOT NULL CONSTRAINTS WHERE APPROPRIATE
-- ============================================================================
-- Enforce required fields that should never be NULL

-- Note: Many columns already have NOT NULL from the schema
-- Adding NOT NULL to key identifier columns that should always have values

-- ---------------------------------------------------------------------------
-- Player Core Fields (ibl_plr)
-- ---------------------------------------------------------------------------
-- Name and team ID should always be present
-- Note: tid = 0 represents free agents, so we keep DEFAULT 0
ALTER TABLE ibl_plr
  MODIFY name VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'Player name',
  MODIFY tid INT NOT NULL DEFAULT 0 COMMENT 'Team ID (0 = free agent)',
  MODIFY pos ENUM('PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', '') 
    NOT NULL DEFAULT '' COMMENT 'Player position';

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these queries after migration to verify changes

-- Verify data types were changed
SELECT 'Verifying data type changes...' AS message;
SELECT 
  COLUMN_NAME, 
  DATA_TYPE, 
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT,
  COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'ibl_plr'
  AND COLUMN_NAME IN ('age', 'peak', 'stats_gm', 'stats_min', 'sta', 'oo', 'pos')
ORDER BY COLUMN_NAME;

-- Verify CHECK constraints were added
SELECT 'Verifying CHECK constraints...' AS message;
SELECT 
  CONSTRAINT_NAME,
  TABLE_NAME,
  CONSTRAINT_TYPE
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND CONSTRAINT_TYPE = 'CHECK'
  AND TABLE_NAME LIKE 'ibl_%'
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- Verify ENUM types
SELECT 'Verifying ENUM types...' AS message;
SELECT 
  TABLE_NAME,
  COLUMN_NAME,
  COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND DATA_TYPE = 'enum'
  AND TABLE_NAME LIKE 'ibl_%'
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================================================
-- ROLLBACK PROCEDURES
-- ============================================================================
-- If issues occur, constraints can be dropped individually:
--
-- DROP CHECK constraints:
-- ALTER TABLE ibl_plr DROP CONSTRAINT chk_plr_age;
-- ALTER TABLE ibl_plr DROP CONSTRAINT chk_plr_peak;
-- ALTER TABLE ibl_standings DROP CONSTRAINT chk_standings_pct;
-- ... etc for other constraints
--
-- Revert ENUM to VARCHAR:
-- ALTER TABLE ibl_plr MODIFY pos VARCHAR(4) DEFAULT '';
-- ALTER TABLE ibl_standings MODIFY conference VARCHAR(7) DEFAULT '';
-- ALTER TABLE ibl_draft_class MODIFY pos CHAR(2) NOT NULL DEFAULT '';
--
-- Revert integer sizes:
-- ALTER TABLE ibl_plr MODIFY stats_gm INT DEFAULT 0;
-- ... etc for other columns
--
-- Note: Data type changes should be tested in development first
-- Full restore from backup may be necessary for complete rollback

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================
SELECT 'Phase 4 Migration Complete!' AS message;
SELECT 'Data type refinements, CHECK constraints, and ENUM types have been applied.' AS details;
SELECT 'Please review the verification queries above to confirm all changes.' AS next_step;
