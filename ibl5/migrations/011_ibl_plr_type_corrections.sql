-- Migration: ibl_plr schema type corrections
-- Corrects oversized/incorrect column types now that MYSQLI_OPT_INT_AND_FLOAT_NATIVE is enabled.
--
-- Changes:
--   cy, cyt: INT(11) → TINYINT UNSIGNED (range 0-6, CHECK constraints enforce)
--   cy1-cy6: INT(11) → SMALLINT (range -7000 to 7000, fits SMALLINT)
--   coach: VARCHAR(16) → TINYINT UNSIGNED (stores 0-5 only)
--   teamname: VARCHAR(32) → VARCHAR(16) (max observed: 12 chars)
--   draftedby: VARCHAR(32) → VARCHAR(16) (max observed: 12 chars)
--   draftedbycurrentname: VARCHAR(32) → VARCHAR(16) (max observed: 12 chars)
--   college: VARCHAR(48) → VARCHAR(40) (max observed: 35 chars)

-- Step 1: Convert empty coach strings to '0' before type change
UPDATE ibl_plr SET coach = '0' WHERE coach = '';

-- Step 2: ALTER TABLE with all column modifications
ALTER TABLE ibl_plr
  MODIFY COLUMN `cy` TINYINT UNSIGNED DEFAULT 0,
  MODIFY COLUMN `cyt` TINYINT UNSIGNED DEFAULT 0,
  MODIFY COLUMN `cy1` SMALLINT DEFAULT 0,
  MODIFY COLUMN `cy2` SMALLINT DEFAULT 0,
  MODIFY COLUMN `cy3` SMALLINT DEFAULT 0,
  MODIFY COLUMN `cy4` SMALLINT DEFAULT 0,
  MODIFY COLUMN `cy5` SMALLINT DEFAULT 0,
  MODIFY COLUMN `cy6` SMALLINT DEFAULT 0,
  MODIFY COLUMN `coach` TINYINT UNSIGNED DEFAULT 0,
  MODIFY COLUMN `teamname` VARCHAR(16) DEFAULT '',
  MODIFY COLUMN `draftedby` VARCHAR(16) DEFAULT '',
  MODIFY COLUMN `draftedbycurrentname` VARCHAR(16) DEFAULT '',
  MODIFY COLUMN `college` VARCHAR(40) DEFAULT '';
