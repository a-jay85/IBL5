<?php

declare(strict_types=1);

// Post-migration-116 snake_case names: should not trigger the rule.
$allowed1 = "SELECT `clutch`, `consistency` FROM ibl_plr";
$allowed2 = "SELECT `pg_depth`, `sg_depth`, `sf_depth`, `pf_depth`, `c_depth` FROM ibl_plr";
$allowed3 = "SELECT `dc_pg_depth`, `dc_sg_depth`, `dc_sf_depth`, `dc_pf_depth`, `dc_c_depth` FROM ibl_plr";
$allowed4 = "SELECT `dc_can_play_in_game`, `playing_time`, `stamina` FROM ibl_plr";

// Post-migration-118 snake_case standings names: should not trigger the rule.
$allowed5 = "SELECT `league_record`, `conf_record`, `conf_gb`, `div_record`, `div_gb` FROM ibl_standings";
$allowed6 = "SELECT `home_record`, `away_record`, `games_unplayed` FROM ibl_standings";
$allowed7 = "SELECT `conf_wins`, `conf_losses`, `div_wins`, `div_losses` FROM ibl_standings";
$allowed8 = "SELECT `home_wins`, `home_losses`, `away_wins`, `away_losses` FROM ibl_standings";
$allowed9 = "SELECT `conf_magic_number`, `div_magic_number` FROM ibl_standings";
$allowed10 = "SELECT `clinched_conference`, `clinched_division`, `clinched_playoffs`, `clinched_league` FROM ibl_standings";
