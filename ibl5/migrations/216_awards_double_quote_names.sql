-- destructive-migration[rewrite-no-backup]: renames 10 known ibl_awards rows; the paired down.sql and scripts/undo/216 script restore every renamed row by table_id
-- backlog#1828: JSB award text wrote an apostrophe in a player name as a double quote.
-- No IBL player name contains a double quote, so map it back to an apostrophe.
-- Step 1: a quote row whose apostrophe twin already exists for the same (year, award)
-- would break uk_year_award_name on rename, so delete the quote row first.
DELETE q FROM ibl_awards q
  JOIN ibl_awards a
    ON a.year = q.year
   AND a.award = q.award
   AND a.name = REPLACE(q.name, '"', '''')
 WHERE q.name LIKE '%"%';
-- Step 2: rename the remaining quote rows. Re-running finds no quote rows and changes nothing.
UPDATE ibl_awards
   SET name = REPLACE(name, '"', '''')
 WHERE name LIKE '%"%';
