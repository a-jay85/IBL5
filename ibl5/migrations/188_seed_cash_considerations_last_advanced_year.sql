-- Migration 188: seed the cash-considerations advance marker
--
-- `ibl_cash_considerations.cy` is advanced once per season rollover by
-- Updater\SeasonRollover\CashConsiderationsYearAdvancer. This row records the
-- season ending year the advance was last performed for, so a repeated
-- updateAllTheThings.php run for the same season is a no-op.
--
-- Seeded at the current season ending year (2026): production rows were
-- already corrected by hand (ibl5-bugs#24, #25), so the 2027 rollover is the
-- first one that performs the advance. No `cy` data is modified here.

INSERT IGNORE INTO `ibl_settings` (`setting_key`, `value`, `league`)
VALUES ('Cash Considerations Last Advanced Year', '2026', 'ibl');
