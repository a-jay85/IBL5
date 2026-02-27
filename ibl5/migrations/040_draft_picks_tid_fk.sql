-- Migration 040: Convert ibl_draft and ibl_draft_picks FKs from VARCHAR to INT
--
-- ibl_draft.team VARCHAR(255) → tid INT (FK → ibl_team_info.teamid)
-- ibl_draft_picks.teampick VARCHAR(32) → teampick_tid INT (FK → ibl_team_info.teamid)
-- ibl_draft_picks.ownerofpick VARCHAR(32) → owner_tid INT (FK → ibl_team_info.teamid)
--
-- The VARCHAR columns are retained for backward compatibility; new INT columns
-- hold the canonical FK relationship.

-- ============================================================
-- ibl_draft: add tid, populate, swap FK
-- ============================================================

ALTER TABLE ibl_draft ADD COLUMN tid INT NOT NULL DEFAULT 0 AFTER team;

UPDATE ibl_draft d
  JOIN ibl_team_info t ON d.team = t.team_name
   SET d.tid = t.teamid;

ALTER TABLE ibl_draft DROP FOREIGN KEY fk_draft_team;

ALTER TABLE ibl_draft
  ADD CONSTRAINT fk_draft_tid
  FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE ON UPDATE CASCADE;

-- ============================================================
-- ibl_draft_picks: add teampick_tid + owner_tid, populate, swap FKs
-- ============================================================

ALTER TABLE ibl_draft_picks ADD COLUMN teampick_tid INT NOT NULL DEFAULT 0 AFTER teampick;

ALTER TABLE ibl_draft_picks ADD COLUMN owner_tid INT NOT NULL DEFAULT 0 AFTER ownerofpick;

UPDATE ibl_draft_picks dp
  JOIN ibl_team_info t ON dp.teampick = t.team_name
   SET dp.teampick_tid = t.teamid;

UPDATE ibl_draft_picks dp
  JOIN ibl_team_info t ON dp.ownerofpick = t.team_name
   SET dp.owner_tid = t.teamid;

ALTER TABLE ibl_draft_picks DROP FOREIGN KEY fk_draftpick_team;
ALTER TABLE ibl_draft_picks DROP FOREIGN KEY fk_draftpick_owner;

ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_teampick_tid
  FOREIGN KEY (teampick_tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_owner_tid
  FOREIGN KEY (owner_tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ibl_draft_picks ADD INDEX idx_owner_tid_year (owner_tid, year);
