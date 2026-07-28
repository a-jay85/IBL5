-- 157_rename_recap_text_columns.sql
--
-- Disambiguates the two recap text columns. Both were named `recap_text`, and the
-- generation agent guessed differently on sim 721 vs sim 722 — 721 stored a 220-char
-- teaser at the envelope level and put headers + Discord mentions in the per-game rows;
-- 722 did the opposite. Only 722's shape makes simSummaries.php?format=txt produce the
-- paste-into-Discord document, so 722's reading is canonical:
--
--   ibl_sim_summaries.sim_recap_text    = the FULL assembled Discord document
--   ibl_sim_game_recaps.game_recap_text = bare prose for one game (no header, no mentions)
--
-- Forward-only: 155 and 156 are already recorded as applied on long-lived databases and
-- the runner tracks by filename, so an amended 155/156 would never re-run. This ships
-- the change forward as its own migration, exactly as 156 did for 155.

ALTER TABLE `ibl_sim_summaries`
  CHANGE COLUMN `recap_text` `sim_recap_text` MEDIUMTEXT NULL
  COMMENT 'Full assembled Discord recap document (intro + per-date rules + per-game headers/mentions/prose + outro)';

ALTER TABLE `ibl_sim_game_recaps`
  CHANGE COLUMN `recap_text` `game_recap_text` MEDIUMTEXT NOT NULL
  COMMENT 'Bare prose for one game — no score header line, no Discord mention line';
