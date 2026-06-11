-- Migration 145: Resize ASG/EOY ballot columns varchar(255) -> varchar(128)
-- (maintenance-44 — backlog 15.15)
--
-- The 16 ibl_votes_ASG + 12 ibl_votes_EOY ballot columns store a "Name, Team"
-- COMPOSITE assembled in VotingBallotView::renderCandidateRow
-- ($safeValue = $safeName . ', ' . $safeTeamName) and split back apart by
-- VotingResultsService::extractPlayerName (last-comma split). The stored value
-- is RAW: the view escapes only for the HTML attribute, the browser decodes the
-- entity on submit, and modules/Voting/index.php rebuilds $ballot from $_POST
-- with is_string() guards only; VotingRepository::saveAsgVote/saveEoyVote bind
-- those strings with prepared statements ('s'), no re-escaping. So the stored
-- length is bounded by the SOURCE column widths, never by entity-expansion.
-- The only other writer (LeagueControlPanelRepository) sets these columns to
-- NULL (clear), never a longer value.
--
-- Bound math (max code-producible composite length):
--   Player categories (MVP/Six/ROY + all ASG):
--     name     = ibl_plr.name              varchar(32)
--     teamName = ibl_team_info.team_name    varchar(16)  (PlayerRepository
--                aliases t.team_name AS teamname)
--     composite = 32 + 2 (", ") + 16 = 50
--   GM category:
--     name     = ibl_team_info.owner_name   varchar(32)
--     teamName = trim(team_city + ' ' + team_name)
--              = team_city varchar(24) + ' ' + team_name varchar(16) <= 41
--     composite = 32 + 2 (", ") + 41 = 75
--   GLOBAL MAXIMUM = 75 characters.
-- Target varchar(128) carries 53 chars headroom. varchar(64) is REJECTED
-- (75 > 64 would truncate GM composites). 128 is the smallest power-of-two
-- >= the bound with comfortable margin and still a large reduction from 255.
--
-- Fail-loud on dirty data: STRICT_ALL_TABLES guarantees a MODIFY that WOULD
-- truncate any existing row ERRORS the deploy rather than silently truncating.
-- The 75-char code bound guarantees no current-code value triggers it.
-- MariaDB 10.11's default sql_mode already includes STRICT_TRANS_TABLES;
-- widening it to STRICT_ALL_TABLES for this session makes the guarantee hold
-- regardless of how the target server is configured.
--
-- Residual risk: the folded 2026 baseline does not prove deep pre-baseline
-- history. Covered by (a) the STRICT_ALL_TABLES fail-loud guarantee above and
-- (b) the prod-audit query below.
--
-- Pre-resize audit (run against prod before/at deploy; expect max_len <= 128):
--   SELECT MAX(GREATEST(
--     CHAR_LENGTH(COALESCE(east_f1,'')), CHAR_LENGTH(COALESCE(east_f2,'')),
--     CHAR_LENGTH(COALESCE(east_f3,'')), CHAR_LENGTH(COALESCE(east_f4,'')),
--     CHAR_LENGTH(COALESCE(east_b1,'')), CHAR_LENGTH(COALESCE(east_b2,'')),
--     CHAR_LENGTH(COALESCE(east_b3,'')), CHAR_LENGTH(COALESCE(east_b4,'')),
--     CHAR_LENGTH(COALESCE(west_f1,'')), CHAR_LENGTH(COALESCE(west_f2,'')),
--     CHAR_LENGTH(COALESCE(west_f3,'')), CHAR_LENGTH(COALESCE(west_f4,'')),
--     CHAR_LENGTH(COALESCE(west_b1,'')), CHAR_LENGTH(COALESCE(west_b2,'')),
--     CHAR_LENGTH(COALESCE(west_b3,'')), CHAR_LENGTH(COALESCE(west_b4,''))
--   )) FROM ibl_votes_ASG;
--   SELECT MAX(GREATEST(
--     CHAR_LENGTH(COALESCE(mvp_1,'')), CHAR_LENGTH(COALESCE(mvp_2,'')),
--     CHAR_LENGTH(COALESCE(mvp_3,'')), CHAR_LENGTH(COALESCE(six_1,'')),
--     CHAR_LENGTH(COALESCE(six_2,'')), CHAR_LENGTH(COALESCE(six_3,'')),
--     CHAR_LENGTH(COALESCE(roy_1,'')), CHAR_LENGTH(COALESCE(roy_2,'')),
--     CHAR_LENGTH(COALESCE(roy_3,'')), CHAR_LENGTH(COALESCE(gm_1,'')),
--     CHAR_LENGTH(COALESCE(gm_2,'')), CHAR_LENGTH(COALESCE(gm_3,''))
--   )) FROM ibl_votes_EOY;
--
-- Idempotent: every statement is MODIFY COLUMN, which is naturally re-runnable
-- (setting a column to the type it already has is a no-op, not an error).
-- Column names are the migration-120 snake_case forms; DEFAULT NULL and the
-- per-column COMMENT are preserved verbatim.

SET SESSION sql_mode = CONCAT(@@sql_mode, ',STRICT_ALL_TABLES');

ALTER TABLE `ibl_votes_ASG`
  MODIFY COLUMN `east_f1` varchar(128) DEFAULT NULL COMMENT 'Eastern frontcourt 1st pick',
  MODIFY COLUMN `east_f2` varchar(128) DEFAULT NULL COMMENT 'Eastern frontcourt 2nd pick',
  MODIFY COLUMN `east_f3` varchar(128) DEFAULT NULL COMMENT 'Eastern frontcourt 3rd pick',
  MODIFY COLUMN `east_f4` varchar(128) DEFAULT NULL COMMENT 'Eastern frontcourt 4th pick',
  MODIFY COLUMN `east_b1` varchar(128) DEFAULT NULL COMMENT 'Eastern backcourt 1st pick',
  MODIFY COLUMN `east_b2` varchar(128) DEFAULT NULL COMMENT 'Eastern backcourt 2nd pick',
  MODIFY COLUMN `east_b3` varchar(128) DEFAULT NULL COMMENT 'Eastern backcourt 3rd pick',
  MODIFY COLUMN `east_b4` varchar(128) DEFAULT NULL COMMENT 'Eastern backcourt 4th pick',
  MODIFY COLUMN `west_f1` varchar(128) DEFAULT NULL COMMENT 'Western frontcourt 1st pick',
  MODIFY COLUMN `west_f2` varchar(128) DEFAULT NULL COMMENT 'Western frontcourt 2nd pick',
  MODIFY COLUMN `west_f3` varchar(128) DEFAULT NULL COMMENT 'Western frontcourt 3rd pick',
  MODIFY COLUMN `west_f4` varchar(128) DEFAULT NULL COMMENT 'Western frontcourt 4th pick',
  MODIFY COLUMN `west_b1` varchar(128) DEFAULT NULL COMMENT 'Western backcourt 1st pick',
  MODIFY COLUMN `west_b2` varchar(128) DEFAULT NULL COMMENT 'Western backcourt 2nd pick',
  MODIFY COLUMN `west_b3` varchar(128) DEFAULT NULL COMMENT 'Western backcourt 3rd pick',
  MODIFY COLUMN `west_b4` varchar(128) DEFAULT NULL COMMENT 'Western backcourt 4th pick';

ALTER TABLE `ibl_votes_EOY`
  MODIFY COLUMN `mvp_1` varchar(128) DEFAULT NULL COMMENT 'MVP ballot 1st place',
  MODIFY COLUMN `mvp_2` varchar(128) DEFAULT NULL COMMENT 'MVP ballot 2nd place',
  MODIFY COLUMN `mvp_3` varchar(128) DEFAULT NULL COMMENT 'MVP ballot 3rd place',
  MODIFY COLUMN `six_1` varchar(128) DEFAULT NULL COMMENT 'Sixth Man ballot 1st place',
  MODIFY COLUMN `six_2` varchar(128) DEFAULT NULL COMMENT 'Sixth Man ballot 2nd place',
  MODIFY COLUMN `six_3` varchar(128) DEFAULT NULL COMMENT 'Sixth Man ballot 3rd place',
  MODIFY COLUMN `roy_1` varchar(128) DEFAULT NULL COMMENT 'Rookie of Year ballot 1st place',
  MODIFY COLUMN `roy_2` varchar(128) DEFAULT NULL COMMENT 'Rookie of Year ballot 2nd place',
  MODIFY COLUMN `roy_3` varchar(128) DEFAULT NULL COMMENT 'Rookie of Year ballot 3rd place',
  MODIFY COLUMN `gm_1`  varchar(128) DEFAULT NULL COMMENT 'GM of Year ballot 1st place',
  MODIFY COLUMN `gm_2`  varchar(128) DEFAULT NULL COMMENT 'GM of Year ballot 2nd place',
  MODIFY COLUMN `gm_3`  varchar(128) DEFAULT NULL COMMENT 'GM of Year ballot 3rd place';
