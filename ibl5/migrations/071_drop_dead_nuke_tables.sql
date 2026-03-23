-- Drop legacy PHP-Nuke tables with zero active PHP references.
-- nuke_authors: get_author() removed in PR #471, last references cleaned here
-- nuke_optimize_gain: DB optimization tracking (never used)
-- nuke_pages: static pages (replaced by Standings module)
-- nuke_pages_categories: static page categories (unused)
-- nuke_poll_desc: polling system (unused)
DROP TABLE IF EXISTS nuke_authors;
DROP TABLE IF EXISTS nuke_optimize_gain;
DROP TABLE IF EXISTS nuke_pages;
DROP TABLE IF EXISTS nuke_pages_categories;
DROP TABLE IF EXISTS nuke_poll_desc;
