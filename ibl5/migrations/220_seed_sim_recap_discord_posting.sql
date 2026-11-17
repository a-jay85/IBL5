-- Migration 220: seed the sim-recap Discord posting switch
--
-- Commissioner switch for sim-recap Discord auto-posting (League Control Panel).
-- Default Off: nothing posts until the commissioner sets it to On.
-- LeagueControlPanelRepository::updateSetting() is UPDATE-only, so the row
-- must exist before the first toggle. INSERT IGNORE on the (setting_key, league)
-- primary key makes a re-run a no-op and never overwrites the commissioner's choice.

INSERT IGNORE INTO `ibl_settings` (`setting_key`, `setting_value`, `league`)
VALUES ('Sim Recap Discord Posting', 'Off', 'ibl');
