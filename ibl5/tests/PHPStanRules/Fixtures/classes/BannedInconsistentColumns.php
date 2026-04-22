<?php

declare(strict_types=1);

$turnover = "SELECT `stats_to` FROM ibl_plr";
$rating3a = "SELECT `r_tga` FROM ibl_plr";
$rating3p = "SELECT `r_tgp` FROM ibl_plr";
$bareTid = "SELECT * FROM ibl_plr WHERE `tid` = 1";
$snakeTeamId = "SELECT * FROM ibl_rcb_alltime_records WHERE `team_id` = 1";
$camelTeamID = "SELECT `teamID` FROM ibl_box_scores";
$pascalTeamID = "UPDATE ibl_power SET v = 0 WHERE `TeamID` = 1";
$compoundHome = "SELECT `homeTID` FROM ibl_box_scores";
$compoundVisitor = "SELECT `visitorTID` FROM ibl_box_scores";
$compoundHomeTeam = "SELECT `homeTeamID` FROM ibl_box_scores_teams";
$compoundVisitorTeam = "SELECT `visitorTeamID` FROM ibl_box_scores_teams";
$ownerTid = "SELECT `owner_tid` FROM ibl_draft_picks";
$teampickTid = "SELECT `teampick_tid` FROM ibl_draft_picks";
