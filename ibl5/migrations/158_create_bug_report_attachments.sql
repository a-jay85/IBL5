-- Discord bug-pipeline attachment capture (ADR-0098). Forward-only, purely additive:
-- one new child table, no destructive DDL, no ALTER of an existing table.

CREATE TABLE IF NOT EXISTS `ibl_bug_report_attachments` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `report_id`     INT UNSIGNED    NOT NULL,
    `attachment_id` VARCHAR(20)     NOT NULL COMMENT 'Discord snowflake — always a string, never Number()/tonumber',
    `original_url`  TEXT            NOT NULL COMMENT 'Discord CDN URL — expires ~24h, so local_path is the durable copy',
    `local_path`    TEXT            NULL     COMMENT 'Absolute path in the attachment cache; NULL when the download failed (degrade-to-URL)',
    `filename`      VARCHAR(255)    NOT NULL COMMENT 'Discord-supplied name — DISPLAY ONLY, never used to build a filesystem path',
    `content_type`  VARCHAR(100)    NOT NULL,
    `file_size`     BIGINT UNSIGNED NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ux_report_attachment` (`report_id`, `attachment_id`),
    CONSTRAINT `fk_bug_attachment_report`
        FOREIGN KEY (`report_id`) REFERENCES `ibl_bug_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
