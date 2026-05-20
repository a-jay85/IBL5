-- Migration 129: Rename "Chuck Person (I)" → "Person Chuck" across all base tables.
-- Views (ibl_heat_stats, ibl_playoff_stats, ibl_*_career_avgs/totals,
-- ibl_season_career_avgs) derive from these base tables and need no separate update.
--
-- Idempotent — UPDATE on a value that doesn't exist is a no-op.

-- -------------------------------------------------------
-- Structured name columns (base tables only)
-- -------------------------------------------------------

UPDATE `ibl_plr` SET `name` = 'Person Chuck' WHERE `name` = 'Chuck Person (I)';
UPDATE `ibl_box_scores` SET `name` = 'Person Chuck' WHERE `name` = 'Chuck Person (I)';
UPDATE `ibl_hist` SET `name` = 'Person Chuck' WHERE `name` = 'Chuck Person (I)';
UPDATE `ibl_awards` SET `name` = 'Person Chuck' WHERE `name` = 'Chuck Person (I)';
UPDATE `ibl_demands` SET `name` = 'Person Chuck' WHERE `name` = 'Chuck Person (I)';
UPDATE `ibl_fa_offers` SET `name` = 'Person Chuck' WHERE `name` = 'Chuck Person (I)';
UPDATE `ibl_jsb_draft_results` SET `player_name` = 'Person Chuck' WHERE `player_name` = 'Chuck Person (I)';
UPDATE `ibl_jsb_retired_players` SET `player_name` = 'Person Chuck' WHERE `player_name` = 'Chuck Person (I)';
UPDATE `ibl_jsb_transactions` SET `player_name` = 'Person Chuck' WHERE `player_name` = 'Chuck Person (I)';
UPDATE `ibl_one_on_one` SET `winner` = 'Person Chuck' WHERE `winner` = 'Chuck Person (I)';
UPDATE `ibl_one_on_one` SET `loser` = 'Person Chuck' WHERE `loser` = 'Chuck Person (I)';
UPDATE `ibl_plb_snapshots` SET `player_name` = 'Person Chuck' WHERE `player_name` = 'Chuck Person (I)';
UPDATE `ibl_plr_snapshots` SET `name` = 'Person Chuck' WHERE `name` = 'Chuck Person (I)';
UPDATE `ibl_rcb_season_records` SET `player_name` = 'Person Chuck' WHERE `player_name` = 'Chuck Person (I)';
UPDATE `ibl_saved_depth_chart_players` SET `player_name` = 'Person Chuck' WHERE `player_name` = 'Chuck Person (I)';

-- -------------------------------------------------------
-- Free-text fields (name embedded in prose)
-- -------------------------------------------------------

UPDATE `nuke_stories` SET `title` = REPLACE(`title`, 'Chuck Person (I)', 'Person Chuck') WHERE `title` LIKE '%Chuck Person (I)%';
UPDATE `nuke_stories` SET `hometext` = REPLACE(`hometext`, 'Chuck Person (I)', 'Person Chuck') WHERE `hometext` LIKE '%Chuck Person (I)%';
UPDATE `nuke_stories` SET `bodytext` = REPLACE(`bodytext`, 'Chuck Person (I)', 'Person Chuck') WHERE `bodytext` LIKE '%Chuck Person (I)%';
UPDATE `ibl_one_on_one` SET `playbyplay` = REPLACE(`playbyplay`, 'Chuck Person (I)', 'Person Chuck') WHERE `playbyplay` LIKE '%Chuck Person (I)%';
UPDATE `cache` SET `value` = REPLACE(`value`, 'Chuck Person (I)', 'Person Chuck') WHERE `value` LIKE '%Chuck Person (I)%';
