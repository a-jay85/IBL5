-- Migration 165: Derive Eastern/Western Conference Champions from playoff round 3
-- instead of writing them at regular-season clinch.
--
-- NOTE ON NUMBERING: the implementation plan was authored when 162 was the latest
-- migration on disk and therefore called this file `163_...`. Master has since gained
-- 163_backfill_2007_retired_flag.sql and 164_remove_stale_granger_retirement.sql
-- (neither touches ibl_team_awards, vw_team_awards, vw_franchise_summary,
-- vw_playoff_series_results, ibl_league_config or ibl_franchise_seasons), so this
-- migration is 165 and the backup table is suffixed _165 to match.
--
-- Root cause: StandingsUpdater::checkClinched() upserted an
-- 'Eastern/Western Conference Champions' row into ibl_team_awards the moment a team
-- clinched the best REGULAR-SEASON record in its conference. That is the wrong event.
-- A conference champion is the team that wins the conference finals -- playoff round 3.
-- The two events usually agree, which is why the bug went unnoticed for twenty seasons,
-- but they disagree whenever a lower seed wins the conference finals.
--
-- Fix (three parts):
--   A. vw_team_awards gains a fourth UNION branch that DERIVES the conference champion
--      from vw_playoff_series_results round 3, joined to ibl_league_config for the
--      season's conference assignment (via ibl_franchise_seasons for renamed
--      franchises). No writer is needed for 2009+ -- the award materializes the instant
--      a round-3 series reaches its clinch threshold.
--   B. The 40 stored conference rows are backed up and deleted, so the derivation is
--      the single source of truth. The corrected 2007/2008 values EMERGE from the
--      derivation rather than being UPDATEd.
--   C. vw_franchise_summary's conf_titles predicate is tightened from
--      LIKE '%Conference%' to an exact two-value IN(), so a future award whose name
--      merely contains the word "Conference" cannot inflate the count. Provable no-op
--      on current data (those are the only two conference award strings that exist).
--
-- The four corrupt/premature rows this repairs (ibl_team_awards ids), with their
-- stored values:
--   id 211 -- 2007 Eastern Conference Champions, stored 'Braves'   (derived: 'Nets')
--   id 212 -- 2007 Western Conference Champions, stored 'PLACEHOLDER_2007_W' (derivation agrees)
--   id 349 -- 2008 Eastern Conference Champions, stored 'Knicks'   (derivation agrees)
--   id 350 -- 2008 Western Conference Champions, stored 'Jazz'     (derived: 'Clippers')
-- Ids 349/350 were written before a single 2008 playoff game, so both were premature
-- by construction; 350 was premature AND wrong -- 'Jazz' names the team swept 0-4 by
-- the Clippers in that very round-3 series.
--
-- Reversal (documented, not executed):
--   INSERT IGNORE INTO ibl_team_awards SELECT * FROM ibl_team_awards_conference_backup_165;
-- restores all 40 rows with their original ids, including 211, 212, 349, 350.
--
-- Idempotent: the backup table uses CREATE TABLE IF NOT EXISTS ... LIKE, so it keeps
-- ibl_team_awards' primary key and a second run's INSERT IGNORE is a no-op. The DELETE
-- finds no matching rows on a second run. Both CREATE OR REPLACE VIEW statements are
-- idempotent by definition.
--
-- Does NOT touch: division awards, IBL Champions, IBL HEAT Champions, the div_titles /
-- ibl_titles / heat_titles predicates, or the wl/po sub-joins in vw_franchise_summary.
-- The DELETE matches the two exact award strings, never LIKE '%Conference%'.

-- ---------------------------------------------------------------------------
-- B. Back up the stored conference rows, then remove them.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ibl_team_awards_conference_backup_165` LIKE `ibl_team_awards`;

INSERT IGNORE INTO `ibl_team_awards_conference_backup_165`
SELECT * FROM `ibl_team_awards`
WHERE award IN ('Eastern Conference Champions', 'Western Conference Champions');

DELETE FROM `ibl_team_awards`
WHERE award IN ('Eastern Conference Champions', 'Western Conference Champions');

-- ---------------------------------------------------------------------------
-- A. Rebuild vw_team_awards with the derived conference-champions branch.
--    Branches 1-3 are copied verbatim from migration 123; branch 4 is new.
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW `vw_team_awards` AS
SELECT `ibl_team_awards`.`year` AS `year`,
       `ibl_team_awards`.`name` AS `name`,
       `ibl_team_awards`.`award` AS `award`,
       `ibl_team_awards`.`id` AS `id`
FROM `ibl_team_awards`
UNION ALL
SELECT `ranked`.`year` AS `year`,
       `ranked`.`name` AS `name`,
       'IBL Champions' AS `award`,
       0 AS `id`
FROM (
    SELECT `psr`.`year` AS `year`,
           `psr`.`winner` AS `name`,
           `psr`.`round` AS `round`,
           MAX(`psr`.`round`) OVER (PARTITION BY `psr`.`year`) AS `max_round`,
           COUNT(0) OVER (PARTITION BY `psr`.`year`, `psr`.`round`) AS `series_in_round`
    FROM `vw_playoff_series_results` `psr`
) `ranked`
WHERE `ranked`.`round` = `ranked`.`max_round`
  AND `ranked`.`series_in_round` = 1
UNION ALL
-- Derived conference champions: the winner of the single round-3 series in each
-- conference, once that series has reached the season's clinch threshold.
--
-- Three deliberate guards, none of which may be "simplified":
--   * `psr.round = 3` is a literal, not MAX(round). Generalizing it would also match
--     round 4 (the Finals) and fabricate a third conference title every season.
--   * NULLIF(CAST(... AS UNSIGNED), 0) fails CLOSED. MariaDB's CAST returns 0 for
--     unparseable text, so a malformed playoff_round3_format would otherwise make
--     `winner_games >= 0` trivially true and award a title mid-series. NULLIF turns
--     that 0 into NULL, so the comparison is NULL and the row is suppressed.
--   * `series_in_conf = 1` (partitioned by year AND conference) suppresses the award
--     for any season whose round-3 data is malformed or duplicated within a conference.
--
-- The LEFT JOIN to ibl_franchise_seasons resolves the franchise's name AS OF that
-- season, so a renamed franchise still matches ibl_league_config.team_name; COALESCE
-- falls back to the current name when no historical row exists.
SELECT `ranked`.`year` AS `year`,
       `ranked`.`name` AS `name`,
       `ranked`.`award` AS `award`,
       0 AS `id`
FROM (
    SELECT
        `psr`.`year` AS `year`,
        `psr`.`winner` AS `name`,
        CONCAT(`lc`.`conference`, ' Conference Champions') AS `award`,
        COUNT(*) OVER (PARTITION BY `psr`.`year`, `lc`.`conference`) AS `series_in_conf`
    FROM `vw_playoff_series_results` `psr`
    LEFT JOIN `ibl_franchise_seasons` `fs`
        ON `fs`.`franchise_id` = `psr`.`winner_tid` AND `fs`.`season_ending_year` = `psr`.`year`
    JOIN `ibl_league_config` `lc`
        ON `lc`.`season_ending_year` = `psr`.`year`
       AND `lc`.`team_name` = COALESCE(`fs`.`team_name`, `psr`.`winner`)
    WHERE `psr`.`round` = 3
      AND `psr`.`winner_games` >= NULLIF(CAST(SUBSTRING_INDEX(`lc`.`playoff_round3_format`, ' ', 1) AS UNSIGNED), 0)
) `ranked`
WHERE `ranked`.`series_in_conf` = 1
UNION ALL
SELECT `hc`.`year` AS `year`,
       `ti`.`team_name` AS `name`,
       'IBL HEAT Champions' AS `award`,
       0 AS `id`
FROM (
    SELECT YEAR(`bst`.`game_date`) AS `year`,
           CASE WHEN `bst`.`home_q1_points` + `bst`.`home_q2_points` + `bst`.`home_q3_points` + `bst`.`home_q4_points`
                  + COALESCE(`bst`.`home_ot_points`, 0)
                > `bst`.`visitor_q1_points` + `bst`.`visitor_q2_points` + `bst`.`visitor_q3_points` + `bst`.`visitor_q4_points`
                  + COALESCE(`bst`.`visitor_ot_points`, 0)
                THEN `bst`.`home_teamid`
                ELSE `bst`.`visitor_teamid`
           END AS `winner_tid`,
           ROW_NUMBER() OVER (
               PARTITION BY YEAR(`bst`.`game_date`)
               ORDER BY `bst`.`game_date` DESC, `bst`.`game_of_that_day`
           ) AS `rn`
    FROM `ibl_box_scores_teams` `bst`
    WHERE `bst`.`game_type` = 3
) `hc`
JOIN `ibl_team_info` `ti` ON `ti`.`teamid` = `hc`.`winner_tid`
WHERE `hc`.`rn` = 1;

-- ---------------------------------------------------------------------------
-- C. Rebuild vw_franchise_summary with an exact conf_titles predicate.
--    Copied verbatim from migration 123 except the single conf_titles line.
-- ---------------------------------------------------------------------------

CREATE OR REPLACE SQL SECURITY INVOKER VIEW `vw_franchise_summary` AS
SELECT
    `ti`.`teamid` AS `teamid`,
    COALESCE(`wl`.`totwins`, 0)  AS `totwins`,
    COALESCE(`wl`.`totloss`, 0)  AS `totloss`,
    CASE
        WHEN COALESCE(`wl`.`totwins`, 0) + COALESCE(`wl`.`totloss`, 0) = 0 THEN 0.000
        ELSE ROUND(COALESCE(`wl`.`totwins`, 0) / (COALESCE(`wl`.`totwins`, 0) + COALESCE(`wl`.`totloss`, 0)), 3)
    END AS `winpct`,
    COALESCE(`po`.`playoffs`, 0)    AS `playoffs`,
    COALESCE(`tc`.`div_titles`, 0)  AS `div_titles`,
    COALESCE(`tc`.`conf_titles`, 0) AS `conf_titles`,
    COALESCE(`tc`.`ibl_titles`, 0)  AS `ibl_titles`,
    COALESCE(`tc`.`heat_titles`, 0) AS `heat_titles`
FROM `ibl_team_info` `ti`
LEFT JOIN (
    SELECT
        `ibl_team_win_loss`.`currentname` AS `currentname`,
        SUM(`ibl_team_win_loss`.`wins`)   AS `totwins`,
        SUM(`ibl_team_win_loss`.`losses`) AS `totloss`
    FROM `ibl_team_win_loss`
    GROUP BY `ibl_team_win_loss`.`currentname`
) `wl` ON `wl`.`currentname` = `ti`.`team_name`
LEFT JOIN (
    SELECT
        `po_inner`.`team_name` AS `team_name`,
        COUNT(DISTINCT `po_inner`.`year`) AS `playoffs`
    FROM (
        SELECT `vw_playoff_series_results`.`winner` AS `team_name`, `vw_playoff_series_results`.`year` AS `year`
        FROM `vw_playoff_series_results`
        WHERE `vw_playoff_series_results`.`round` = 1
        UNION
        SELECT `vw_playoff_series_results`.`loser` AS `team_name`, `vw_playoff_series_results`.`year` AS `year`
        FROM `vw_playoff_series_results`
        WHERE `vw_playoff_series_results`.`round` = 1
    ) `po_inner`
    GROUP BY `po_inner`.`team_name`
) `po` ON `po`.`team_name` = `ti`.`team_name`
LEFT JOIN (
    SELECT
        `vw_team_awards`.`name` AS `name`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%Division%'      THEN 1 ELSE 0 END) AS `div_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` IN ('Eastern Conference Champions', 'Western Conference Champions') THEN 1 ELSE 0 END) AS `conf_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%IBL Champions%' THEN 1 ELSE 0 END) AS `ibl_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%HEAT%'          THEN 1 ELSE 0 END) AS `heat_titles`
    FROM `vw_team_awards`
    GROUP BY `vw_team_awards`.`name`
) `tc` ON `tc`.`name` = `ti`.`team_name`
WHERE `ti`.`teamid` BETWEEN 1 AND 30;
