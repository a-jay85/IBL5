-- Migration 038: Add pid lookup column to ibl_demands, convert ibl_olympics_stats FK to pid

-- Step 1: ibl_demands -- add pid column and populate from ibl_plr
ALTER TABLE ibl_demands ADD COLUMN IF NOT EXISTS pid INT NOT NULL DEFAULT 0 AFTER name;
UPDATE ibl_demands d JOIN ibl_plr p ON d.name = p.name SET d.pid = p.pid;
ALTER TABLE ibl_demands DROP FOREIGN KEY IF EXISTS fk_demands_player;
ALTER TABLE ibl_demands ADD INDEX IF NOT EXISTS idx_pid (pid);

-- Step 2: ibl_olympics_stats -- add pid column, populate, and add FK
ALTER TABLE ibl_olympics_stats ADD COLUMN IF NOT EXISTS pid INT NOT NULL DEFAULT 0 AFTER name;
UPDATE ibl_olympics_stats os JOIN ibl_plr p ON os.name = p.name SET os.pid = p.pid;
ALTER TABLE ibl_olympics_stats DROP FOREIGN KEY IF EXISTS fk_olympics_stats_name;
ALTER TABLE ibl_olympics_stats DROP FOREIGN KEY IF EXISTS fk_olympics_stats_pid;
ALTER TABLE ibl_olympics_stats ADD CONSTRAINT fk_olympics_stats_pid FOREIGN KEY (pid) REFERENCES ibl_plr(pid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE ibl_olympics_stats ADD INDEX IF NOT EXISTS idx_pid (pid);
