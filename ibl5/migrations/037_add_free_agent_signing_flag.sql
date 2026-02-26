ALTER TABLE ibl_plr
    ADD COLUMN fa_signing_flag TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Free agent signing flag (1=signed as FA, 0=drafted/traded/continuing)'
    AFTER cy6;
