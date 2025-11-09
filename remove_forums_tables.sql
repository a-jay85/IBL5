-- SQL script to remove Forums-related database tables
-- This script removes all Forums module tables and Private Messages tables
-- Generated for the Forums module removal task
-- 
-- WARNING: This will permanently delete all forums data, posts, topics, and private messages
-- Make sure to backup your database before running this script
--
-- Usage: mysql -u username -p database_name < remove_forums_tables.sql

-- Drop Foreign Key Constraints First (if any exist)
-- Note: These may not exist in older installations, so we use IF EXISTS where supported

-- Drop all Forums-related tables
DROP TABLE IF EXISTS nuke_bbvote_voters;
DROP TABLE IF EXISTS nuke_bbvote_results;
DROP TABLE IF EXISTS nuke_bbvote_desc;
DROP TABLE IF EXISTS nuke_bbuser_group;
DROP TABLE IF EXISTS nuke_bbtopics_watch;
DROP TABLE IF EXISTS nuke_bbtopics;
DROP TABLE IF EXISTS nuke_bbthemes_name;
DROP TABLE IF EXISTS nuke_bbthemes;
DROP TABLE IF EXISTS nuke_bbsmilies;
DROP TABLE IF EXISTS nuke_bbsessions;
DROP TABLE IF EXISTS nuke_bbsearch_wordmatch;
DROP TABLE IF EXISTS nuke_bbsearch_wordlist;
DROP TABLE IF EXISTS nuke_bbsearch_results;
DROP TABLE IF EXISTS nuke_bbranks;
DROP TABLE IF EXISTS nuke_bbprivmsgs_text;
DROP TABLE IF EXISTS nuke_bbprivmsgs;
DROP TABLE IF EXISTS nuke_bbposts_text;
DROP TABLE IF EXISTS nuke_bbposts;
DROP TABLE IF EXISTS nuke_bbgroups;
DROP TABLE IF EXISTS nuke_bbforums;
DROP TABLE IF EXISTS nuke_bbforum_prune;
DROP TABLE IF EXISTS nuke_bbdisallow;
DROP TABLE IF EXISTS nuke_bbconfig;
DROP TABLE IF EXISTS nuke_bbcategories;
DROP TABLE IF EXISTS nuke_bbbanlist;
DROP TABLE IF EXISTS nuke_bbauth_access;
DROP TABLE IF EXISTS nuke_bbwords;

-- Verification query - should return 0 rows if all tables are removed
-- SELECT TABLE_NAME FROM information_schema.TABLES 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME LIKE 'nuke_bb%';
