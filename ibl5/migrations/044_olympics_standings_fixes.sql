-- Migration 044: Olympics standings fixes
-- Adds clinchedLeague column and changes conference ENUM to VARCHAR(32) on both standings tables

-- Add clinchedLeague (present in ibl_standings, missing from Olympics)
ALTER TABLE ibl_olympics_standings
  ADD COLUMN clinchedLeague TINYINT(1) DEFAULT NULL
  COMMENT '1=clinched league best record' AFTER clinchedPlayoffs;

-- Change conference from ENUM to VARCHAR on both tables for Olympics group support
ALTER TABLE ibl_standings
  MODIFY COLUMN conference VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT ''
  COMMENT 'Conference affiliation or Olympics group name';

ALTER TABLE ibl_olympics_standings
  MODIFY COLUMN conference VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT ''
  COMMENT 'Conference affiliation or Olympics group name';
