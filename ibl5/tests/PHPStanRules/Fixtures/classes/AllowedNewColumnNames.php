<?php

declare(strict_types=1);

// Post-migration-113/116 names: should not trigger the rule.
$allowed1 = "SELECT r_trans_off, r_drive_off, r_tvr FROM ibl_plr";
$allowed2 = "SELECT start_date, end_date FROM ibl_sim_dates";
$allowed3 = "SELECT `value` FROM `cache` WHERE `cache_key` = ?";
// Backticked but not banned: still fine.
$allowed4 = "SELECT `pid`, `name` FROM ibl_plr";
