-- Migration 063: Fix drafted players with NULL retired value
--
-- The Draft module's createPlayerFromDraftClass() INSERT omitted the `retired`
-- column, which defaults to NULL. Team roster queries filter WHERE retired = 0,
-- so drafted players were invisible on team pages. The code fix (adding retired=0
-- to the INSERT) prevents new occurrences; this migration fixes existing rows.

UPDATE ibl_plr SET retired = 0 WHERE retired IS NULL;
