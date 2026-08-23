-- 165_backfill_pre_2007_retired_flag.sql
--
-- Backfill `ibl_plr.retired = 1` for players who retired before the league began
-- archiving .ret files. Nothing ever wrote that column until this PR, so they
-- still render without the retired asterisk on the career leaderboards.
--
-- The pid list is a HUMAN-REVIEWED literal from
-- ibl5/scripts/list-retirement-candidates.php, deliberately NOT a computed
-- predicate: 721 players carry retired = 1 while absent from
-- ibl_jsb_retired_players, so any join-based rule would clear 721 correct flags.
-- Additive only and idempotent: the `retired IS NULL OR retired = 0` guard means a re-run affects 0 rows,
-- and nothing here can set retired back to 0.

UPDATE ibl_plr
   SET retired = 1
 WHERE pid IN (
    931  -- Brandon Roy: required; a manual production fix was blocked
    -- PLACEHOLDER: replaced with reviewed pid list before commit
 )
   AND (retired IS NULL OR retired = 0);
