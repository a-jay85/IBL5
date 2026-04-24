<?php

declare(strict_types=1);

// Post-migration-116 snake_case names: should not trigger the rule.
$allowed1 = "SELECT `clutch`, `consistency` FROM ibl_plr";
$allowed2 = "SELECT `pg_depth`, `sg_depth`, `sf_depth`, `pf_depth`, `c_depth` FROM ibl_plr";
$allowed3 = "SELECT `dc_pg_depth`, `dc_sg_depth`, `dc_sf_depth`, `dc_pf_depth`, `dc_c_depth` FROM ibl_plr";
$allowed4 = "SELECT `dc_can_play_in_game`, `playing_time`, `stamina` FROM ibl_plr";
