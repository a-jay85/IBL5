-- Remove stale preseason sentinel data (year 9998/9999)
-- See ADR-0011: remove-preseason-sentinel-year

DELETE FROM ibl_box_scores WHERE game_date BETWEEN '9998-11-01' AND '9999-05-30';
DELETE FROM ibl_box_scores_teams WHERE game_date BETWEEN '9998-11-01' AND '9999-05-30';
DELETE FROM ibl_team_awards WHERE year = 9999;
DELETE FROM ibl_jsb_history WHERE season_year = 9999;
DELETE FROM ibl_jsb_transactions WHERE season_year = 9999;
DELETE FROM ibl_rcb_season_records WHERE season_year = 9999;
DELETE FROM ibl_rcb_alltime_records WHERE season_year = 9999;
DELETE FROM ibl_plr_snapshots WHERE season_year = 9999;
