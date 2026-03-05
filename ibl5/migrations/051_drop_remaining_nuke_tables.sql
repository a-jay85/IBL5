-- Migration 051: Drop remaining empty legacy PHP-Nuke tables
-- Associated dead code (update_points, automated_news, message_box, paid)
-- removed in this same changeset.
DROP TABLE IF EXISTS nuke_autonews;
DROP TABLE IF EXISTS nuke_groups;
DROP TABLE IF EXISTS nuke_groups_points;
DROP TABLE IF EXISTS nuke_message;
DROP TABLE IF EXISTS nuke_subscriptions;
