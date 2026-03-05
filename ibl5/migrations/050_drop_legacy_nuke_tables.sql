-- Migration 050: Drop empty/trivial legacy PHP-Nuke tables
-- NOTE: nuke_autonews, nuke_groups, nuke_groups_points, nuke_message,
-- and nuke_subscriptions are excluded — they still have active PHP callers
-- in mainfile.php (automated_news, update_points, message_box, paid).
DROP TABLE IF EXISTS nuke_headlines;
DROP TABLE IF EXISTS nuke_links_categories;
DROP TABLE IF EXISTS nuke_links_links;
DROP TABLE IF EXISTS nuke_links_modrequest;
DROP TABLE IF EXISTS nuke_links_newlink;
DROP TABLE IF EXISTS nuke_main;
DROP TABLE IF EXISTS nuke_queue;
DROP TABLE IF EXISTS nuke_related;
