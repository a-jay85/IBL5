-- Migration 189: seed the cash-considerations advance marker
--
-- `ibl_cash_considerations.cy` is advanced once per season rollover by
-- Updater\SeasonRollover\CashConsiderationsYearAdvancer. This row records the
-- season ending year the advance was last performed for, so a repeated
-- updateAllTheThings.php run for the same season is a no-op.
--
-- Seeded at 2009, the IBL `Current Season Ending Year` in the prod-synced main
-- stack (the prod database itself was not read directly). Production rows were
-- already corrected by hand (ibl5-bugs#24, #25), so the 2010 rollover is the
-- first one that performs the advance. A marker above the live year would make
-- every rollover up to that year a silent no-op; a marker below it is harmless.
-- No `cy` data is modified here.

INSERT IGNORE INTO `ibl_settings` (`setting_key`, `value`, `league`)
VALUES ('Cash Considerations Last Advanced Year', '2009', 'ibl');
