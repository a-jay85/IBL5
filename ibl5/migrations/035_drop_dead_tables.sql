-- Migration 035: Drop dead tables and columns
-- 26 tables with zero PHP references + 1 dead column
--
-- Every table was cross-referenced against all PHP code:
--   - Literal table name references in classes/
--   - $prefix . "_tablename" references in mainfile.php, admin/, modules/, includes/
-- Only tables with ZERO references across all code are dropped.

-- Drop dead column
ALTER TABLE ibl_team_info DROP COLUMN Contract_Coach;

-- Drop dead IBL tables
DROP TABLE IF EXISTS ibl_playoff_results;
DROP TABLE IF EXISTS ibl_plr_chunk;
DROP TABLE IF EXISTS olympic_stats;

-- Drop dead PHP-Nuke tables
DROP TABLE IF EXISTS nuke_banner;
DROP TABLE IF EXISTS nuke_banner_clients;
DROP TABLE IF EXISTS nuke_banner_plans;
DROP TABLE IF EXISTS nuke_banner_positions;
DROP TABLE IF EXISTS nuke_banner_terms;
DROP TABLE IF EXISTS nuke_cities;
DROP TABLE IF EXISTS nuke_comments_moderated;
DROP TABLE IF EXISTS nuke_confirm;
DROP TABLE IF EXISTS nuke_faqanswer;
DROP TABLE IF EXISTS nuke_faqcategories;
DROP TABLE IF EXISTS nuke_links_editorials;
DROP TABLE IF EXISTS nuke_links_votedata;
DROP TABLE IF EXISTS nuke_pollcomments;
DROP TABLE IF EXISTS nuke_pollcomments_moderated;
DROP TABLE IF EXISTS nuke_public_messages;
DROP TABLE IF EXISTS nuke_users_temp;

-- Drop dead misc tables
DROP TABLE IF EXISTS online;
DROP TABLE IF EXISTS user_online;
DROP TABLE IF EXISTS poll;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS responses;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;
