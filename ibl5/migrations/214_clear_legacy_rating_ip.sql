-- backlog#1258: legacy story-rating IPs are kept forever and read by nothing.
-- Reset every stored value to the column default. Idempotent; irreversible
-- (the old values survive only in the 14-day backup rotation).
-- destructive-migration[rewrite-no-backup,down-file-missing]: the point is to stop keeping these IPs, so a backup table or a down file that restores them would defeat the change; no code reads the column
UPDATE nuke_stories SET rating_ip = '0' WHERE rating_ip NOT IN ('0', '');
