-- Migration 056: Add AUTO_INCREMENT to ibl_team_awards.ID and UNIQUE KEY on (year, Award)
-- This enables idempotent upserts via INSERT ... ON DUPLICATE KEY UPDATE

ALTER TABLE ibl_team_awards MODIFY COLUMN ID int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE ibl_team_awards ADD UNIQUE KEY uk_year_award (year, Award);
