-- Migration 191: Narrow int(11) stat columns on the Olympics career tables
-- Backlog 15.17 / a-jay85/IBL5-backlog#218
--
-- TARGET: matches ibl_plr.car_* (car_gm smallint(5) unsigned; car_min..car_pts
--   mediumint(8) unsigned). ibl_olympics_career_avgs.games and
--   ibl_olympics_career_totals.games become smallint(5) unsigned; totals
--   minutes, fgm, fga, ftm, fta, tgm, tga, orb, reb, ast, stl, tvr, blk, pf, pts
--   become mediumint(8) unsigned. The backlog entry named ibl_hist as the
--   reference, but ibl_hist is int(11) throughout, so ibl_plr.car_* is the real
--   convention. retired is already tinyint(1) (migration 135) and is untouched.
--
-- FORWARD BOUND: no code path writes either table. They are BASE TABLEs loaded
--   by manual JSB import (see migration 150 header). Readers are
--   PlayerStatsRepository (SELECT * by pid) and CareerLeaderboardsRepository
--   (SELECT h.* ORDER BY a whitelisted column). Olympic career counts cannot
--   approach 65535 games or 16777215 of any counting stat (live max: games 9,
--   minutes 359, pts 275). Prod sql_mode is empty, so an out-of-range write
--   would clamp silently; this static bound is the only forward protection.
--   The leaderboard derives drb in PHP, so unsigned SQL subtraction never runs.
--
-- ROLLBACK (lossless, widening never loses data):
--   ALTER TABLE `ibl_olympics_career_avgs`
--     MODIFY COLUMN `games` int(11) NOT NULL DEFAULT 0 COMMENT 'Olympic games played';
--   ALTER TABLE `ibl_olympics_career_totals`
--     MODIFY COLUMN `games` int(11) NOT NULL DEFAULT 0 COMMENT 'Total Olympic games played',
--     MODIFY COLUMN `minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Total minutes played',
--     MODIFY COLUMN `fgm` int(11) NOT NULL DEFAULT 0 COMMENT 'Total field goals made',
--     MODIFY COLUMN `fga` int(11) NOT NULL DEFAULT 0 COMMENT 'Total field goals attempted',
--     MODIFY COLUMN `ftm` int(11) NOT NULL DEFAULT 0 COMMENT 'Total free throws made',
--     MODIFY COLUMN `fta` int(11) NOT NULL DEFAULT 0 COMMENT 'Total free throws attempted',
--     MODIFY COLUMN `tgm` int(11) NOT NULL DEFAULT 0 COMMENT 'Total three pointers made',
--     MODIFY COLUMN `tga` int(11) NOT NULL DEFAULT 0 COMMENT 'Total three pointers attempted',
--     MODIFY COLUMN `orb` int(11) NOT NULL DEFAULT 0 COMMENT 'Total offensive rebounds',
--     MODIFY COLUMN `reb` int(11) NOT NULL DEFAULT 0 COMMENT 'Total rebounds',
--     MODIFY COLUMN `ast` int(11) NOT NULL DEFAULT 0 COMMENT 'Total assists',
--     MODIFY COLUMN `stl` int(11) NOT NULL DEFAULT 0 COMMENT 'Total steals',
--     MODIFY COLUMN `tvr` int(11) NOT NULL DEFAULT 0 COMMENT 'Total turnovers',
--     MODIFY COLUMN `blk` int(11) NOT NULL DEFAULT 0 COMMENT 'Total blocks',
--     MODIFY COLUMN `pf` int(11) NOT NULL DEFAULT 0 COMMENT 'Total personal fouls',
--     MODIFY COLUMN `pts` int(11) NOT NULL DEFAULT 0 COMMENT 'Total points';
--
-- APPLY-TIME GUARD (statements 1-2): the mode-independent UNION-subquery idiom
--   raises ERROR 1242 under strict and non-strict sql_mode when any live row is
--   negative (signedness changes) or above the new unsigned max, aborting before
--   any ALTER. STRICT_ALL_TABLES is not used: it would not abort on prod.
--
-- IDEMPOTENT: information_schema gates (migration 009 idiom) make a re-apply a
--   no-op once every target column already has its new type.

-- Statement 1: totals guard
SELECT IF(
  (SELECT COUNT(*) FROM `ibl_olympics_career_totals`
    WHERE `games` > 65535 OR `games` < 0
       OR `minutes` > 16777215 OR `minutes` < 0
       OR `fgm` > 16777215 OR `fgm` < 0
       OR `fga` > 16777215 OR `fga` < 0
       OR `ftm` > 16777215 OR `ftm` < 0
       OR `fta` > 16777215 OR `fta` < 0
       OR `tgm` > 16777215 OR `tgm` < 0
       OR `tga` > 16777215 OR `tga` < 0
       OR `orb` > 16777215 OR `orb` < 0
       OR `reb` > 16777215 OR `reb` < 0
       OR `ast` > 16777215 OR `ast` < 0
       OR `stl` > 16777215 OR `stl` < 0
       OR `tvr` > 16777215 OR `tvr` < 0
       OR `blk` > 16777215 OR `blk` < 0
       OR `pf` > 16777215 OR `pf` < 0
       OR `pts` > 16777215 OR `pts` < 0) > 0,
  (SELECT 1 UNION SELECT 2),
  0
);

-- Statement 2: avgs guard
SELECT IF(
  (SELECT COUNT(*) FROM `ibl_olympics_career_avgs`
    WHERE `games` > 65535 OR `games` < 0) > 0,
  (SELECT 1 UNION SELECT 2),
  0
);

-- Statement 3: idempotent totals ALTER
SET @oc_totals_needs_alter = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ibl_olympics_career_totals'
    AND ((COLUMN_NAME = 'games' AND COLUMN_TYPE <> 'smallint(5) unsigned')
      OR (COLUMN_NAME IN ('minutes','fgm','fga','ftm','fta','tgm','tga','orb',
                          'reb','ast','stl','tvr','blk','pf','pts')
          AND COLUMN_TYPE <> 'mediumint(8) unsigned'))
);
SET @oc_totals_sql = IF(@oc_totals_needs_alter > 0,
  'ALTER TABLE `ibl_olympics_career_totals`
     MODIFY COLUMN `games` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT \'Total Olympic games played\',
     MODIFY COLUMN `minutes` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total minutes played\',
     MODIFY COLUMN `fgm` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total field goals made\',
     MODIFY COLUMN `fga` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total field goals attempted\',
     MODIFY COLUMN `ftm` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total free throws made\',
     MODIFY COLUMN `fta` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total free throws attempted\',
     MODIFY COLUMN `tgm` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total three pointers made\',
     MODIFY COLUMN `tga` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total three pointers attempted\',
     MODIFY COLUMN `orb` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total offensive rebounds\',
     MODIFY COLUMN `reb` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total rebounds\',
     MODIFY COLUMN `ast` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total assists\',
     MODIFY COLUMN `stl` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total steals\',
     MODIFY COLUMN `tvr` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total turnovers\',
     MODIFY COLUMN `blk` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total blocks\',
     MODIFY COLUMN `pf` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total personal fouls\',
     MODIFY COLUMN `pts` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT \'Total points\'',
  'SELECT 1');
PREPARE _stmt FROM @oc_totals_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- Statement 4: idempotent avgs ALTER
SET @oc_avgs_needs_alter = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ibl_olympics_career_avgs'
    AND COLUMN_NAME = 'games'
    AND COLUMN_TYPE <> 'smallint(5) unsigned'
);
SET @oc_avgs_sql = IF(@oc_avgs_needs_alter > 0,
  'ALTER TABLE `ibl_olympics_career_avgs`
     MODIFY COLUMN `games` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT \'Olympic games played\'',
  'SELECT 1');
PREPARE _stmt FROM @oc_avgs_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
