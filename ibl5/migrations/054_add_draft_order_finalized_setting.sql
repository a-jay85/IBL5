-- Add Draft Order Finalized setting
INSERT INTO ibl_settings (name, value)
VALUES ('Draft Order Finalized', 'No')
ON DUPLICATE KEY UPDATE value = value;
