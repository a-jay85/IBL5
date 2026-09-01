-- Migration 172 — key `ibl_team_win_loss` on the matchup triple, not the quadruple.
--
-- Migration 121 defined this view's `unique_games` CTE with a four-column dedup key
-- (game_date, visitor_teamid, home_teamid, game_of_that_day). `game_of_that_day` is a
-- league-wide ordinal within a date, NOT a per-matchup counter, so a duplicated
-- boxscore for the same matchup lands with a different ordinal and the four-column key
-- treats it as a second, distinct game. The season then reads 83 games for the two
-- teams involved instead of 82.
--
-- Fix: canonicalize on the matchup triple, keeping the row with the lowest
-- `game_of_that_day` ("first recorded wins"). `min(game_of_that_day)` is the in-repo
-- house pattern -- `vw_schedule_upcoming` in migration 121 canonicalizes identically.
-- Choosing a row deterministically matters because duplicate rows can disagree on the
-- score, and an arbitrary-row GROUP BY selection would make *which team is credited
-- with the win* nondeterministic.
--
-- The join back onto `canonical_games` uses `<=>` (NULL-safe equality), not `=`, because
-- `game_of_that_day` is NULLable. With plain `=`, a matchup whose rows all carry a NULL
-- ordinal yields `min(...) = NULL`, the equality is never true, and the game is dropped
-- from the view entirely -- silently costing both teams a game rather than deduping one.
-- Production currently holds no NULL ordinals, but the E2E seed does, and the column
-- permits them; `<=>` matches NULL to NULL and makes the drop impossible.
--
-- The inner `group by` on the triple is still required: `ibl_box_scores_teams` holds
-- two rows per game (one per team) carrying identical game-level quarter columns, and
-- that GROUP BY collapses the pair -- exactly as migration 121 did.
--
-- Everything from `team_games` onward is copied verbatim from migration 121.
-- Forward-only, pure DDL: CREATE OR REPLACE VIEW only requires the referenced tables to
-- exist, so it applies cleanly against empty CI databases.

CREATE OR REPLACE VIEW `ibl_team_win_loss` AS
with canonical_games as (
  select `ibl_box_scores_teams`.`game_date` AS `game_date`,
         `ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,
         `ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,
         min(`ibl_box_scores_teams`.`game_of_that_day`) AS `game_of_that_day`
  from `ibl_box_scores_teams`
  where `ibl_box_scores_teams`.`game_type` = 1
  group by `ibl_box_scores_teams`.`game_date`,`ibl_box_scores_teams`.`visitor_teamid`,`ibl_box_scores_teams`.`home_teamid`
),
unique_games as (
  select `b`.`game_date` AS `game_date`,
         `b`.`visitor_teamid` AS `visitor_teamid`,
         `b`.`home_teamid` AS `home_teamid`,
         `b`.`visitor_q1_points` + `b`.`visitor_q2_points` + `b`.`visitor_q3_points` + `b`.`visitor_q4_points` + coalesce(`b`.`visitor_ot_points`,0) AS `visitor_total`,
         `b`.`home_q1_points` + `b`.`home_q2_points` + `b`.`home_q3_points` + `b`.`home_q4_points` + coalesce(`b`.`home_ot_points`,0) AS `home_total`
  from `ibl_box_scores_teams` `b`
  join `canonical_games` `c`
    on `c`.`game_date` = `b`.`game_date`
   and `c`.`visitor_teamid` = `b`.`visitor_teamid`
   and `c`.`home_teamid` = `b`.`home_teamid`
   and `c`.`game_of_that_day` <=> `b`.`game_of_that_day`
  where `b`.`game_type` = 1
  group by `b`.`game_date`,`b`.`visitor_teamid`,`b`.`home_teamid`
),
team_games as (select `unique_games`.`visitor_teamid` AS `teamid`,`unique_games`.`game_date` AS `game_date`,if(`unique_games`.`visitor_total` > `unique_games`.`home_total`,1,0) AS `win`,if(`unique_games`.`visitor_total` < `unique_games`.`home_total`,1,0) AS `loss` from `unique_games` union all select `unique_games`.`home_teamid` AS `teamid`,`unique_games`.`game_date` AS `game_date`,if(`unique_games`.`home_total` > `unique_games`.`visitor_total`,1,0) AS `win`,if(`unique_games`.`home_total` < `unique_games`.`visitor_total`,1,0) AS `loss` from `unique_games`)
select case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`teamid`)) left join `ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`teamid` and `fs`.`season_ending_year` = case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end)) group by `tg`.`teamid`,case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end,`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`);
