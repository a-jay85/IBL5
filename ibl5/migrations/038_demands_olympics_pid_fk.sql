-- Migration 038: Convert ibl_demands and ibl_olympics_stats FKs from name (VARCHAR) to pid (INT)
--
-- ibl_demands: PK is name (varchar), FK references ibl_plr(name). Add pid column, add FK to ibl_plr(pid).
-- ibl_olympics_stats: FK on name references ibl_plr(name). Add pid column, add FK to ibl_plr(pid).

-- Step 1: ibl_demands — add pid column and populate from ibl_plr
ALTER TABLE ibl_demands ADD COLUMN pid INT NOT NULL DEFAULT 0 AFTER name;
UPDATE ibl_demands d JOIN ibl_plr p ON d.name = p.name SET d.pid = p.pid;

-- Drop old name-based FK, add pid-based FK
ALTER TABLE ibl_demands DROP FOREIGN KEY fk_demands_player;
ALTER TABLE ibl_demands ADD CONSTRAINT fk_demands_pid FOREIGN KEY (pid) REFERENCES ibl_plr(pid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE ibl_demands ADD INDEX idx_pid (pid);

-- Step 2: ibl_olympics_stats — add pid column and populate from ibl_plr
ALTER TABLE ibl_olympics_stats ADD COLUMN pid INT NOT NULL DEFAULT 0 AFTER name;
UPDATE ibl_olympics_stats os JOIN ibl_plr p ON os.name = p.name SET os.pid = p.pid;

-- Drop old name-based FK, add pid-based FK
ALTER TABLE ibl_olympics_stats DROP FOREIGN KEY fk_olympics_stats_name;
ALTER TABLE ibl_olympics_stats ADD CONSTRAINT fk_olympics_stats_pid FOREIGN KEY (pid) REFERENCES ibl_plr(pid) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE ibl_olympics_stats ADD INDEX idx_pid (pid);
