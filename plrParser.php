<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die( "Unable to select database");

$stringCurrentSimYear = "SELECT value FROM nuke_ibl_settings WHERE name = 'Current IBL Season Ending Year';";
$queryCurrentSimYear = mysql_query($stringCurrentSimYear);
$CurrentSimYear = mysql_result($queryCurrentSimYear, 0);

$stringTeamIDsNames = "SELECT teamid,team_name FROM nuke_ibl_team_info ORDER BY teamid ASC;";
$queryTeamIDsNames = mysql_query($stringTeamIDsNames);
$numRowsTeamIDsNames = mysql_num_rows($queryTeamIDsNames);

$plrFile = fopen("IBLv4.plr", "rb");
while (!feof($plrFile)) {
    $line = fgets($plrFile);

    $ordinal = substr($line,0,4);
    $name = trim(addslashes(substr($line,4,32)));
    $age = substr($line,36,2);
    $pid = substr($line,38,6);
    $tid = substr($line,44,2);
    $peak = substr($line,46,4);
    $pos = substr($line,50,2);
    $rlGP = substr($line,52,4);
    $rlMIN = substr($line,56,4);
    $rlFGM = substr($line,60,4);
    $rlFGA = substr($line,64,4);
    $rlFTM = substr($line,68,4);
    $rlFTA = substr($line,72,4);
    $rl3GM = substr($line,76,4);
    $rl3GA = substr($line,80,4);
    $rlORB = substr($line,84,4);
    $rlDRB = substr($line,88,4);
    $rlAST = substr($line,92,4);
    $rlSTL = substr($line,96,4);
    $rlTVR = substr($line,100,4);
    $rlBLK = substr($line,104,4);
    $rlPF = substr($line,108,4);
    $oo = substr($line,112,2);
    $od = substr($line,114,2);
    $do = substr($line,116,2);
    $dd = substr($line,118,2);
    $po = substr($line,120,2);
    $pd = substr($line,122,2);
    $to = substr($line,124,2);
    $td = substr($line,126,2);
    $clutch = substr($line,128,2);
    $consistency = substr($line,130,2);
    $PGDepth = substr($line,132,1);
    $SGDepth = substr($line,133,1);
    $SFDepth = substr($line,134,1);
    $PFDepth = substr($line,135,1);
    $CDepth = substr($line,136,1);
    $active = substr($line,137,1);
    // 138,2 = ?
    $injured = substr($line,140,4);
    $seasonGS = substr($line,144,4);
    $seasonGP = substr($line,148,4);
    $seasonMIN = substr($line,152,4);
    $season2GM = substr($line,156,4);
    $season2GA = substr($line,160,4);
    $seasonFTM = substr($line,164,4);
    $seasonFTA = substr($line,168,4);
    $season3GM = substr($line,172,4);
    $season3GA = substr($line,176,4);
    $seasonORB = substr($line,180,4);
    $seasonDRB = substr($line,184,4);
    $seasonAST = substr($line,188,4);
    $seasonSTL = substr($line,192,4);
    $seasonTVR = substr($line,196,4);
    $seasonBLK = substr($line,200,4);
    $seasonPF = substr($line,204,4);
    $playoffGS = substr($line,208,4);
    $playoffMIN = substr($line,212,4);
    $playoff2GM = substr($line,216,4);
    $playoff2GA = substr($line,220,4);
    $playoffFTM = substr($line,224,4);
    $playoffFTA = substr($line,228,4);
    $playoff3GM = substr($line,232,4);
    $playoff3GA = substr($line,236,4);
    $playoffORB = substr($line,240,4);
    $playoffDRB = substr($line,244,4);
    $playoffAST = substr($line,248,4);
    $playoffSTL = substr($line,252,4);
    $playoffTVR = substr($line,256,4);
    $playoffBLK = substr($line,260,4);
    $playoffPF = substr($line,264,4);
    $talent = substr($line,268,2);
    $skill = substr($line,270,2);
    $intangibles = substr($line,272,2);
    $coach = substr($line,274,2);
    $loyalty = substr($line,276,2);
    $playingTime = substr($line,278,2);
    $playForWinner = substr($line,280,2);
    $tradition = substr($line,282,2);
    $security = substr($line,284,2);
    $exp = substr($line,286,2);
    $bird = substr($line,288,2);
    $currentContractYear = substr($line,290,2);
    $totalContractYears = substr($line,292,2);
    // 294,4 = ?
    $contractYear1 = substr($line,298,4);
    $contractYear2 = substr($line,302,4);
    $contractYear3 = substr($line,306,4);
    $contractYear4 = substr($line,310,4);
    $contractYear5 = substr($line,314,4);
    $contractYear6 = substr($line,318,4);
    // 322,4 = ?
    $draftRound = substr($line,326,2);
    $draftPickNum = substr($line,328,2);
    // 330-331 = ?
    $contractOwnedBy = substr($line,332,2);
    // 333-340 = ?
    $seasonHighPTS = substr($line,341,2);
    $seasonHighREB = substr($line,343,2);
    $seasonHighAST = substr($line,345,2);
    $seasonHighSTL = substr($line,347,2);
    $seasonHighBLK = substr($line,349,2);
    $seasonHighDD = substr($line,351,2);
    $seasonHighTD = substr($line,353,2);
    $seasonPlayoffHighPTS = substr($line,355,2);
    $seasonPlayoffHighREB = substr($line,357,2);
    $seasonPlayoffHighAST = substr($line,359,2);
    $seasonPlayoffHighSTL = substr($line,361,2);
    $seasonPlayoffHighBLK = substr($line,363,2);
    $careerSeasonHighPTS = substr($line,365,6);
    $careerSeasonHighREB = substr($line,371,6);
    $careerSeasonHighAST = substr($line,377,6);
    $careerSeasonHighSTL = substr($line,383,6);
    $careerSeasonHighBLK = substr($line,389,6);
    $careerSeasonHighDD = substr($line,395,6);
    $careerSeasonHighTD = substr($line,401,6);
    $careerPlayoffHighPTS = substr($line,407,6);
    $careerPlayoffHighREB = substr($line,413,6);
    $careerPlayoffHighAST = substr($line,419,6);
    $careerPlayoffHighSTL = substr($line,425,6);
    $careerPlayoffHighBLK = substr($line,431,6);
    $careerGP = substr($line,437,5);
    $careerMIN = substr($line,442,5);
    $career2GM = substr($line,447,5);
    $career2GA = substr($line,452,5);
    $careerFTM = substr($line,457,5);
    $careerFTA = substr($line,462,5);
    $career3GM = substr($line,467,5);
    $career3GA = substr($line,472,5);
    $careerORB = substr($line,477,5);
    $careerDRB = substr($line,482,5);
    $careerAST = substr($line,487,5);
    $careerSTL = substr($line,492,5);
    $careerTVR = substr($line,497,5);
    $careerBLK = substr($line,502,5);
    $careerPF = substr($line,507,5);
    // 512-543 = blank
    // 544-549 = ?
    $heightInches = substr($line,550,2);
    $weight = substr($line,552,3);
    $rating2GA = substr($line,555,3);
    $rating2GP = substr($line,558,3);
    $ratingFTA = substr($line,561,3);
    $ratingFTP = substr($line,564,3);
    $rating3GA = substr($line,567,3);
    $rating3GP = substr($line,570,3);
    $ratingORB = substr($line,573,3);
    $ratingDRB = substr($line,576,3);
    $ratingAST = substr($line,579,3);
    $ratingSTL = substr($line,582,3);
    $ratingTVR = substr($line,585,3);
    $ratingBLK = substr($line,588,3);
    $ratingOO = substr($line,591,2);
    $ratingDO = substr($line,593,2);
    $ratingPO = substr($line,595,2);
    $ratingTO = substr($line,597,2);
    $ratingOD = substr($line,599,2);
    $ratingDD = substr($line,601,2);
    $ratingPD = substr($line,603,2);
    $ratingTD = substr($line,605,2);

    $seasonFGM = $season2GM + $season3GM;
    $seasonFGA = $season2GA + $season3GA;

    $careerFGM = $career2GM + $season3GM;
    $careerFGA = $career2GA + $career3GA;
    $careerPTS = $season2GM*2 + $seasonFTM + $season3GM*3 + $career2GM*2 + $careerFTM + $career3GM*3;
    $careerREB = $seasonORB + $seasonDRB + $careerORB + $careerDRB;

    $heightFT = floor($heightInches / 12);
    $heightIN = $heightInches % 12;
    $draftYear = $CurrentSimYear - $exp;
    if ($rlPF != 0) $minsPerPF = round($rlMIN / $rlPF);

    if ($ordinal <= 1440) {
        $playerUpdateQuery = "UPDATE `nuke_iblplyr`
            SET
            `ordinal` = $ordinal,
            `name` = '$name',
            `age` = $age,
            `tid` = $tid,
            `pos` = '$pos',
            `sta` = 40,
            `oo` = $ratingOO,
            `od` = $ratingOD,
            `do` = $ratingDO,
            `dd` = $ratingDD,
            `po` = $ratingPO,
            `pd` = $ratingPD,
            `to` = $ratingTO,
            `td` = $ratingTD,
            `Clutch` = $clutch,
            `Consistency` = $consistency,
            `PGDepth` = $PGDepth,
            `SGDepth` = $SGDepth,
            `SFDepth` = $SFDepth,
            `PFDepth` = $PFDepth,
            `CDepth` = $CDepth,
            `active` = $active,
            `stats_gs` = $seasonGS,
            `stats_gm` = $seasonGP,
            `stats_min` = $seasonMIN,
            `stats_fgm` = $seasonFGM,
            `stats_fga` = $seasonFGA,
            `stats_ftm` = $seasonFTM,
            `stats_fta` = $seasonFTA,
            `stats_3gm` = $season3GM,
            `stats_3ga` = $season3GA,
            `stats_orb` = $seasonORB,
            `stats_drb` = $seasonDRB,
            `stats_ast` = $seasonAST,
            `stats_stl` = $seasonSTL,
            `stats_to` = $seasonTVR,
            `stats_blk` = $seasonBLK,
            `stats_pf` = $seasonPF,
            `talent` = $talent,
            `skill` = $skill,
            `intangibles` = $intangibles,
            `coach` = $coach,
            `loyalty` = $loyalty,
            `playingTime` = $playingTime,
            `winner` = $playForWinner,
            `tradition` = $tradition,
            `security` = $security,
            `exp` = $exp,
            `bird` = $bird,
            `cy` = $currentContractYear,
            `cyt` = $totalContractYears,
            `cy1` = $contractYear1,
            `cy2` = $contractYear2,
            `cy3` = $contractYear3,
            `cy4` = $contractYear4,
            `cy5` = $contractYear5,
            `cy6` = $contractYear6,
            `sh_pts` = $seasonHighPTS,
            `sh_reb` = $seasonHighREB,
            `sh_ast` = $seasonHighAST,
            `sh_stl` = $seasonHighSTL,
            `sh_blk` = $seasonHighBLK,
            `s_dd` = $seasonHighDD,
            `s_td` = $seasonHighTD,
            `sp_pts` = $seasonPlayoffHighPTS,
            `sp_reb` = $seasonPlayoffHighREB,
            `sp_ast` = $seasonPlayoffHighAST,
            `sp_stl` = $seasonPlayoffHighSTL,
            `sp_blk` = $seasonPlayoffHighBLK,
            `ch_pts` = $careerSeasonHighPTS,
            `ch_reb` = $careerSeasonHighREB,
            `ch_ast` = $careerSeasonHighAST,
            `ch_stl` = $careerSeasonHighSTL,
            `ch_blk` = $careerSeasonHighBLK,
            `c_dd` = $careerSeasonHighDD,
            `c_td` = $careerSeasonHighTD,
            `cp_pts` = $careerPlayoffHighPTS,
            `cp_reb` = $careerPlayoffHighREB,
            `cp_ast` = $careerPlayoffHighAST,
            `cp_stl` = $careerPlayoffHighSTL,
            `cp_blk` = $careerPlayoffHighBLK,
            `car_gm` = $careerGP,
            `car_min` = $careerMIN,
            `car_fgm` = $careerFGM,
            `car_fga` = $careerFGA,
            `car_ftm` = $careerFTM,
            `car_fta` = $careerFTA,
            `car_tgm` = $career3GM,
            `car_tga` = $career3GA,
            `car_orb` = $careerORB,
            `car_drb` = $careerDRB,
            `car_ast` = $careerAST,
            `car_stl` = $careerSTL,
            `car_to` = $careerTVR,
            `car_blk` = $careerBLK,
            `car_pf` = $careerPF,
            `car_pts` = $careerPTS,
            `car_reb` = $careerREB,
            `r_fga` = $rating2GA,
            `r_fgp` = $rating2GP,
            `r_fta` = $ratingFTA,
            `r_ftp` = $ratingFTP,
            `r_tga` = $rating3GA,
            `r_tgp` = $rating3GP,
            `r_orb` = $ratingORB,
            `r_drb` = $ratingDRB,
            `r_ast` = $ratingAST,
            `r_stl` = $ratingSTL,
            `r_to` = $ratingTVR,
            `r_blk` = $ratingBLK,
            `draftround` = $draftRound,
            `draftpickno` = $draftPickNum,
            `injured` = $injured,
            `htft` = $heightFT,
            `htin` = $heightIN,
            `wt` = $weight,
            `draftyear` = $draftYear,
            `retired` = 0,
            `r_foul` = $minsPerPF
            WHERE
            `pid` = $pid;";
        if ($name !== '') {
            if (mysql_query($playerUpdateQuery)) echo $playerUpdateQuery.'<p>';
        }
    } elseif ($ordinal >= 1441 && $ordinal <= 1504) {
        if ($ordinal >= 1441 && $ordinal <= 1472) $sideOfTheBall = 'offense';
        if ($ordinal >= 1473 && $ordinal <= 1504) {
            $ordinal = $ordinal - 32; // Must use and adjust ordinal because JSB bugs out on team names for defensive stats
            $sideOfTheBall = 'defense';
        }

        $teamUpdateQuery = 'UPDATE `ibl_team_'.$sideOfTheBall.'_stats`
            SET
            `games` = '.$seasonGP.',
            `minutes` = '.($seasonGP*48).',
            `fgm` = '.($season2GM+$season3GM).',
            `fga` = '.($season2GA+$season3GA).',
            `ftm` = '.$seasonFTM.',
            `fta` = '.$seasonFTA.',
            `tgm` = '.$season3GM.',
            `tga` = '.$season3GA.',
            `orb` = '.$seasonORB.',
            `reb` = '.($seasonORB+$seasonDRB).',
            `ast` = '.$seasonAST.',
            `stl` = '.$seasonSTL.',
            `tvr` = '.$seasonTVR.',
            `blk` = '.$seasonBLK.',
            `pf` = '.$seasonPF.'
            WHERE
            `ordinal` = \''.$ordinal.'\';';
        if (mysql_query($teamUpdateQuery)) echo $teamUpdateQuery.'<br>';
    }
}
fclose($plrFile);

$i = 0;
while ($i < $numRowsTeamIDsNames) {
    $teamname = mysql_result($queryTeamIDsNames, $i, 'team_name');
    $teamID = mysql_result($queryTeamIDsNames, $i, 'teamid');
    $teamnameUpdateQuery = "UPDATE `nuke_iblplyr` SET `teamname` = '$teamname' WHERE `tid` = $teamID;";
    if (mysql_query($teamnameUpdateQuery)) echo $teamnameUpdateQuery.'<br>';
    $i++;
}

echo "done.";

?>
