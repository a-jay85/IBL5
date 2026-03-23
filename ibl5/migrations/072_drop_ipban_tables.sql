-- Drop legacy PHP-Nuke IP ban and anti-flood tables.
-- ipban.php (the only consumer) has been removed.
-- nuke_banned_ip: 2 entries from 2011-2012, no admin UI to manage
-- nuke_antiflood: temporary flood-protection data, replaced by auth_users_throttling
DROP TABLE IF EXISTS nuke_antiflood;
DROP TABLE IF EXISTS nuke_banned_ip;
