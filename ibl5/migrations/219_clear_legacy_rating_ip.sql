-- backlog#1258: legacy story-rating IPs are kept forever and read by nothing.
-- Reset every stored value to the column default. Idempotent; irreversible
-- (the old values survive only in the 14-day backup rotation).
-- destructive-migration[rewrite-no-backup,down-file-missing]: a privacy purge of stored IP addresses; a backup table or down file would keep the very data this deletes
UPDATE nuke_stories SET rating_ip = '0' WHERE rating_ip NOT IN ('0', '');
