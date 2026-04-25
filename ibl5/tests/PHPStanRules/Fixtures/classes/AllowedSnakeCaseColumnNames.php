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

// Post-migration-120 snake_case Tier 5 names: should not trigger the rule.
$allowed11 = "SELECT `trade_offer_id`, `sending_team`, `receiving_team` FROM ibl_trade_cash";
$allowed12 = "SELECT `award`, `table_id`, `id` FROM ibl_team_awards";
$allowed13 = "SELECT `mle`, `lle` FROM ibl_fa_offers";
$allowed14 = "SELECT `east_f1`, `east_b2`, `west_f3`, `west_b4` FROM ibl_votes_ASG";
$allowed15 = "SELECT `mvp_1`, `roy_2`, `gm_3`, `six_1` FROM ibl_votes_EOY";
$allowed16 = "SELECT `sim` FROM ibl_sim_dates";
$allowed17 = "SELECT `censor_mode`, `censor_replace`, `default_theme`, `version_num` FROM nuke_config";
$allowed18 = "SELECT `poll_id` FROM nuke_stories";

// Post-migration-121 snake_case Tier 6 names: should not trigger the rule.
$allowed19 = "SELECT `game_date`, `game_min`, `game_2gm`, `game_2ga`, `game_ftm`, `game_fta` FROM ibl_box_scores";
$allowed20 = "SELECT `game_3gm`, `game_3ga`, `game_orb`, `game_drb`, `game_ast`, `game_stl` FROM ibl_box_scores";
$allowed21 = "SELECT `game_tov`, `game_blk`, `game_pf`, `game_of_that_day` FROM ibl_box_scores";
$allowed22 = "SELECT `visitor_wins`, `visitor_losses`, `home_wins`, `home_losses` FROM ibl_box_scores";
$allowed23 = "SELECT `visitor_q1_points`, `visitor_q2_points`, `visitor_q3_points`, `visitor_q4_points`, `visitor_ot_points` FROM ibl_box_scores_teams";
$allowed24 = "SELECT `home_q1_points`, `home_q2_points`, `home_q3_points`, `home_q4_points`, `home_ot_points` FROM ibl_box_scores_teams";
$allowed25 = "SELECT `id`, `season_year`, `box_id`, `game_date`, `visitor_teamid`, `visitor_score`, `home_teamid`, `home_score` FROM ibl_schedule";
