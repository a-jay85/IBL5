-- Migration 178 — extend bug_reports class enum with 'roster_error'.
-- Forward-only, purely additive: MODIFY COLUMN appends one value. Re-runnable; a second
-- run is a no-op because MODIFY COLUMN restates the full definition.
--
-- 'roster_error' is the terminal class for a report about a player's ratings, contract, or
-- team assignment being wrong in the league data. Those values live in the JSB files, not
-- in the website, so the commissioner owns the fix — the pipeline files no GitHub Issue and
-- redirects the GM to #roster-errors. No backfill arm: no existing row can carry a value
-- the classifier could not previously emit.

ALTER TABLE `ibl_bug_reports`
    MODIFY COLUMN `class` ENUM('bug','feature','not_a_thing','roster_error') NULL;
