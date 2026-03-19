-- Add DEFAULT (UUID()) to all uuid columns that lack it.
-- MariaDB 10.6 supports uuid() as a DEFAULT expression.
-- ibl_olympics_team_info already has DEFAULT uuid() (set in baseline).
-- failed_jobs.uuid is varchar(255) and a Laravel queue table — left as-is.

ALTER TABLE ibl_box_scores
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID());

ALTER TABLE ibl_draft
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID());

ALTER TABLE ibl_olympics_box_scores
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID()) COMMENT 'Public API identifier';

ALTER TABLE ibl_olympics_plr
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID());

ALTER TABLE ibl_olympics_schedule
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID()) COMMENT 'Public API identifier';

ALTER TABLE ibl_plr
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID());

ALTER TABLE ibl_schedule
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID());

ALTER TABLE ibl_team_info
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID());
