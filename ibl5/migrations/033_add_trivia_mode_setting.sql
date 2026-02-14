-- Add Trivia Mode setting to ibl_settings
-- Replaces the nuke_modules active flag toggling for Player/SeasonLeaderboards modules
INSERT INTO ibl_settings (name, value) VALUES ('Trivia Mode', 'Off')
  ON DUPLICATE KEY UPDATE value = value;
