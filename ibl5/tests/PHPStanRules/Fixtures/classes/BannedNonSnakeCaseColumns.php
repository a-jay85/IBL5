<?php

declare(strict_types=1);

$clutch = "SELECT `Clutch` FROM ibl_plr";
$consistency = "SELECT `Consistency` FROM ibl_plr";
$pgDepth = "SELECT `PGDepth` FROM ibl_plr";
$sgDepth = "SELECT `SGDepth` FROM ibl_plr";
$sfDepth = "SELECT `SFDepth` FROM ibl_plr";
$pfDepth = "SELECT `PFDepth` FROM ibl_plr";
$cDepth = "SELECT `CDepth` FROM ibl_plr";
$dcPg = "SELECT `dc_PGDepth` FROM ibl_plr";
$dcSg = "SELECT `dc_SGDepth` FROM ibl_plr";
$dcSf = "SELECT `dc_SFDepth` FROM ibl_plr";
$dcPf = "SELECT `dc_PFDepth` FROM ibl_plr";
$dcC = "SELECT `dc_CDepth` FROM ibl_plr";
$dcCan = "SELECT `dc_canPlayInGame` FROM ibl_plr";
$playingTime = "SELECT `playingTime` FROM ibl_plr";
$sta = "SELECT `sta` FROM ibl_plr";
$discordID = "SELECT `discordID` FROM ibl_team_info";
$contractWins = "SELECT `Contract_Wins` FROM ibl_team_info";
$contractLosses = "SELECT `Contract_Losses` FROM ibl_team_info";
$contractAvgW = "SELECT `Contract_AvgW` FROM ibl_team_info";
$contractAvgL = "SELECT `Contract_AvgL` FROM ibl_team_info";
$usedExtChunk = "SELECT `Used_Extension_This_Chunk` FROM ibl_team_info";
$usedExtSeason = "SELECT `Used_Extension_This_Season` FROM ibl_team_info";
$hasMLE = "SELECT `HasMLE` FROM ibl_team_info";
$hasLLE = "SELECT `HasLLE` FROM ibl_team_info";
