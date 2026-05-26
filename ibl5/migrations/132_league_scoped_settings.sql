-- Add league column to ibl_settings for league-scoped settings
ALTER TABLE `ibl_settings`
    ADD COLUMN `league` VARCHAR(16) NOT NULL DEFAULT 'ibl' AFTER `value`;

ALTER TABLE `ibl_settings`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`name`, `league`);

-- Olympics settings (only the 3 that apply)
INSERT INTO `ibl_settings` (`name`, `value`, `league`) VALUES
    ('Current Season Phase', 'Preseason', 'olympics'),
    ('Current Season Ending Year', '2026', 'olympics'),
    ('Sim Length in Days', '3', 'olympics')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
