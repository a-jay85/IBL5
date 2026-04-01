-- Migration 095: Create dedicated ibl_cash_considerations table
--
-- Moves cash consideration and buyout entries from ibl_plr (where they were
-- stored as fake player rows with 130+ unused columns) into a purpose-built
-- table. Updates vw_current_salary to UNION the new table so existing salary
-- SUM queries continue to work without PHP changes.

-- ============================================================================
-- Step 1: Create the new table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ibl_cash_considerations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tid` int(11) NOT NULL COMMENT 'Team this cash entry affects',
    `type` enum('cash','buyout') NOT NULL DEFAULT 'cash' COMMENT 'Cash trade vs buyout',
    `label` varchar(64) NOT NULL DEFAULT '' COMMENT 'Display label (e.g. Cash to Bulls, Kings Buyout)',
    `counterparty_tid` int(11) DEFAULT NULL COMMENT 'Other team involved (NULL for buyouts)',
    `trade_offer_id` int(11) DEFAULT NULL COMMENT 'Link to originating trade (ibl_trade_info.tradeofferid)',
    `cy` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Current contract year (1-6)',
    `cyt` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Total contract years (1-6)',
    `cy1` smallint(6) NOT NULL DEFAULT 0 COMMENT 'Salary year 1 (thousands, negative=incoming)',
    `cy2` smallint(6) NOT NULL DEFAULT 0,
    `cy3` smallint(6) NOT NULL DEFAULT 0,
    `cy4` smallint(6) NOT NULL DEFAULT 0,
    `cy5` smallint(6) NOT NULL DEFAULT 0,
    `cy6` smallint(6) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_tid` (`tid`),
    KEY `idx_type` (`type`),
    CONSTRAINT `fk_cash_considerations_team` FOREIGN KEY (`tid`)
        REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Step 2: Migrate cash entries from ibl_plr
-- ============================================================================

-- Strip the "| " prefix and any <B></B> tags to produce clean labels.
-- REPLACE handles both tagged ("| <B>Cash to Bulls</B>") and untagged ("| Cash to Bulls") formats.
INSERT INTO ibl_cash_considerations (tid, type, label, cy, cyt, cy1, cy2, cy3, cy4, cy5, cy6, created_at, updated_at)
SELECT
    tid,
    'cash',
    REPLACE(REPLACE(TRIM(LEADING '| ' FROM name), '<B>', ''), '</B>', ''),
    cy, cyt, cy1, cy2, cy3, cy4, cy5, cy6,
    created_at, updated_at
FROM ibl_plr
WHERE (name LIKE '| Cash%' OR name LIKE '| <B>Cash%')
  AND retired = 0;

-- Migrate buyout entries
INSERT INTO ibl_cash_considerations (tid, type, label, cy, cyt, cy1, cy2, cy3, cy4, cy5, cy6, created_at, updated_at)
SELECT
    tid,
    'buyout',
    REPLACE(REPLACE(TRIM(LEADING '| ' FROM name), '<B>', ''), '</B>', ''),
    cy, cyt, cy1, cy2, cy3, cy4, cy5, cy6,
    created_at, updated_at
FROM ibl_plr
WHERE name LIKE '%Buyout%'
  AND retired = 0;

-- ============================================================================
-- Step 3: Delete migrated rows from ibl_plr
-- ============================================================================

DELETE FROM ibl_plr
WHERE (name LIKE '| Cash%' OR name LIKE '| <B>Cash%' OR name LIKE '%Buyout%')
  AND name NOT LIKE '%no starter%';

-- ============================================================================
-- Step 4: Update vw_current_salary to include cash considerations via UNION
-- ============================================================================

-- The UNION ALL ensures salary SUM queries (getTeamTotalSalary, etc.) pick up
-- cash rows automatically. Negative IDs distinguish cash from player rows.
CREATE OR REPLACE VIEW vw_current_salary AS
SELECT
    p.pid, p.name, p.tid,
    t.team_name AS teamname,
    p.pos, p.cy, p.cyt,
    p.cy1, p.cy2, p.cy3, p.cy4, p.cy5, p.cy6,
    CASE p.cy WHEN 1 THEN p.cy1 WHEN 2 THEN p.cy2 WHEN 3 THEN p.cy3
              WHEN 4 THEN p.cy4 WHEN 5 THEN p.cy5 WHEN 6 THEN p.cy6 ELSE 0 END AS current_salary,
    CASE p.cy WHEN 0 THEN p.cy1 WHEN 1 THEN p.cy2 WHEN 2 THEN p.cy3
              WHEN 3 THEN p.cy4 WHEN 4 THEN p.cy5 WHEN 5 THEN p.cy6 ELSE 0 END AS next_year_salary
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON p.tid = t.teamid
WHERE p.retired = 0
UNION ALL
SELECT
    -cc.id AS pid,
    cc.label AS name,
    cc.tid,
    t.team_name AS teamname,
    '' AS pos,
    cc.cy, cc.cyt,
    cc.cy1, cc.cy2, cc.cy3, cc.cy4, cc.cy5, cc.cy6,
    CASE cc.cy WHEN 1 THEN cc.cy1 WHEN 2 THEN cc.cy2 WHEN 3 THEN cc.cy3
               WHEN 4 THEN cc.cy4 WHEN 5 THEN cc.cy5 WHEN 6 THEN cc.cy6 ELSE 0 END AS current_salary,
    CASE cc.cy WHEN 0 THEN cc.cy1 WHEN 1 THEN cc.cy2 WHEN 2 THEN cc.cy3
               WHEN 3 THEN cc.cy4 WHEN 4 THEN cc.cy5 WHEN 5 THEN cc.cy6 ELSE 0 END AS next_year_salary
FROM ibl_cash_considerations cc
LEFT JOIN ibl_team_info t ON cc.tid = t.teamid;
