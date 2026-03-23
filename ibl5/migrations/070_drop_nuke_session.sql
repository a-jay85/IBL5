-- Drop the legacy PHP-Nuke session tracking table.
-- This table was only written to by the online() function (now removed)
-- and was never read for display purposes.
DROP TABLE IF EXISTS nuke_session;
