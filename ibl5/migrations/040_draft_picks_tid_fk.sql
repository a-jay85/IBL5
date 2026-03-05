-- Migration 040: Convert ibl_draft and ibl_draft_picks FKs from VARCHAR to INT

-- ============================================================
-- ibl_draft: add tid, populate, swap FK
-- ============================================================

ALTER TABLE ibl_draft ADD COLUMN IF NOT EXISTS tid INT NOT NULL DEFAULT 0 AFTER team;

UPDATE ibl_draft d
  JOIN ibl_team_info t ON d.team = t.team_name
   SET d.tid = t.teamid;

ALTER TABLE ibl_draft DROP FOREIGN KEY IF EXISTS fk_draft_team;
ALTER TABLE ibl_draft DROP FOREIGN KEY IF EXISTS fk_draft_tid;

ALTER TABLE ibl_draft
  ADD CONSTRAINT fk_draft_tid
  FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- ============================================================
-- ibl_draft_picks: add teampick_tid + owner_tid, populate, swap FKs
-- ============================================================

ALTER TABLE ibl_draft_picks ADD COLUMN IF NOT EXISTS teampick_tid INT NOT NULL DEFAULT 0 AFTER teampick;

ALTER TABLE ibl_draft_picks ADD COLUMN IF NOT EXISTS owner_tid INT NOT NULL DEFAULT 0 AFTER ownerofpick;

UPDATE ibl_draft_picks dp
  JOIN ibl_team_info t ON dp.teampick = t.team_name
   SET dp.teampick_tid = t.teamid;

UPDATE ibl_draft_picks dp
  JOIN ibl_team_info t ON dp.ownerofpick = t.team_name
   SET dp.owner_tid = t.teamid;

ALTER TABLE ibl_draft_picks DROP FOREIGN KEY IF EXISTS fk_draftpick_team;
ALTER TABLE ibl_draft_picks DROP FOREIGN KEY IF EXISTS fk_draftpick_owner;
ALTER TABLE ibl_draft_picks DROP FOREIGN KEY IF EXISTS fk_draftpick_teampick_tid;
ALTER TABLE ibl_draft_picks DROP FOREIGN KEY IF EXISTS fk_draftpick_owner_tid;

ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_teampick_tid
  FOREIGN KEY (teampick_tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_owner_tid
  FOREIGN KEY (owner_tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ibl_draft_picks ADD INDEX IF NOT EXISTS idx_owner_tid_year (owner_tid, year);
