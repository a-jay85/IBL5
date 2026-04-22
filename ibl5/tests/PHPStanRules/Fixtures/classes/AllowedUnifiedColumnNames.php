<?php

declare(strict_types=1);

// Post-migration-114 canonical names: should not trigger the rule.
$turnover = "SELECT `stats_tvr` FROM ibl_plr";
$rating = "SELECT `r_3ga`, `r_3gp` FROM ibl_plr";
$teamId = "SELECT * FROM ibl_plr WHERE `teamid` = 1";
$compounds = "SELECT `home_teamid`, `visitor_teamid` FROM ibl_box_scores";
$draftPicks = "SELECT `owner_teamid`, `teampick_teamid` FROM ibl_draft_picks";
// Backticked but unrelated — still fine.
$other = "SELECT `pid`, `name` FROM ibl_plr";
