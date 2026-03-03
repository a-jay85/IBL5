-- Migration 041: Normalize ibl_plr from teamname to tid
--
-- Phase A: Update vw_current_salary to JOIN for team name (backward-compatible)
-- Phase B: Drop the denormalized teamname column (run after PHP deploy)

-- ============================================================================
-- PHASE A: Update view (safe, backward-compatible)
-- ============================================================================

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
WHERE p.retired = 0;

-- ============================================================================
-- PHASE B: Drop column (run after PHP deploy confirms no teamname writes)
-- ============================================================================

DROP INDEX teamname ON ibl_plr;
ALTER TABLE ibl_plr DROP COLUMN teamname;
