-- Migration 039: Convert ibl_fa_offers FKs from name/team (VARCHAR) to pid/tid (INT)
--
-- Add pid column (FK to ibl_plr.pid) and tid column (FK to ibl_team_info.teamid)
-- Keep name/team columns for backward compat (reads)

-- Add pid column
ALTER TABLE ibl_fa_offers ADD COLUMN pid INT NOT NULL DEFAULT 0 AFTER name;
UPDATE ibl_fa_offers f JOIN ibl_plr p ON f.name = p.name SET f.pid = p.pid;
ALTER TABLE ibl_fa_offers DROP FOREIGN KEY fk_faoffer_player;
ALTER TABLE ibl_fa_offers ADD CONSTRAINT fk_faoffer_pid FOREIGN KEY (pid) REFERENCES ibl_plr(pid) ON DELETE CASCADE ON UPDATE CASCADE;

-- Add tid column
ALTER TABLE ibl_fa_offers ADD COLUMN tid INT NOT NULL DEFAULT 0 AFTER team;
UPDATE ibl_fa_offers f JOIN ibl_team_info t ON f.team = t.team_name SET f.tid = t.teamid;
ALTER TABLE ibl_fa_offers DROP FOREIGN KEY fk_faoffer_team;
ALTER TABLE ibl_fa_offers ADD CONSTRAINT fk_faoffer_tid FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid) ON DELETE CASCADE ON UPDATE CASCADE;

-- Add composite index for pid+tid lookups
ALTER TABLE ibl_fa_offers ADD INDEX idx_tid_pid (tid, pid);

-- Update view to use new FK columns instead of string JOINs
CREATE OR REPLACE VIEW vw_free_agency_offers AS
SELECT
    fa.primary_key AS offer_id,
    p.uuid AS player_uuid,
    p.pid AS pid,
    p.name AS player_name,
    p.pos AS position,
    p.age AS age,
    t.uuid AS team_uuid,
    t.teamid AS teamid,
    t.team_city AS team_city,
    t.team_name AS team_name,
    CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
    fa.offer1 AS year1_amount,
    fa.offer2 AS year2_amount,
    fa.offer3 AS year3_amount,
    fa.offer4 AS year4_amount,
    fa.offer5 AS year5_amount,
    fa.offer6 AS year6_amount,
    fa.offer1 + fa.offer2 + fa.offer3 + fa.offer4 + fa.offer5 + fa.offer6 AS total_contract_value,
    fa.modifier AS modifier,
    fa.random AS random,
    fa.perceivedvalue AS perceived_value,
    fa.MLE AS is_mle,
    fa.LLE AS is_lle,
    fa.created_at AS created_at,
    fa.updated_at AS updated_at
FROM ibl_fa_offers fa
JOIN ibl_plr p ON fa.pid = p.pid
JOIN ibl_team_info t ON fa.tid = t.teamid;
