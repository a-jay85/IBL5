<?php

require 'mainfile.php';

$plrFile = fopen("IBL5.plr", "rb");
while (!feof($plrFile)) {
    $line = fgets($plrFile);

    $ordinal = substr($line, 0, 4);
    $name = trim(addslashes(substr($line, 4, 32)));
    $age = substr($line, 36, 2);
    $pid = substr($line, 38, 6);
    $tid = substr($line, 44, 2);
    $peak = substr($line, 46, 4);
    $pos = substr($line, 50, 2);
    $realLifeGP = substr($line, 52, 4);
    $realLifeMIN = substr($line, 56, 4);
    $realLifeFGM = substr($line, 60, 4);
    $realLifeFGA = substr($line, 64, 4);
    $realLifeFTM = substr($line, 68, 4);
    $realLifeFTA = substr($line, 72, 4);
    $realLife3GM = substr($line, 76, 4);
    $realLife3GA = substr($line, 80, 4);
    $realLifeORB = substr($line, 84, 4);
    $realLifeDRB = substr($line, 88, 4);
    $realLifeAST = substr($line, 92, 4);
    $realLifeSTL = substr($line, 96, 4);
    $realLifeTVR = substr($line, 100, 4);
    $realLifeBLK = substr($line, 104, 4);
    $realLifePF = substr($line, 108, 4);
    $oo = substr($line, 112, 2);
    $od = substr($line, 114, 2);
    $do = substr($line, 116, 2);
    $dd = substr($line, 118, 2);
    $po = substr($line, 120, 2);
    $pd = substr($line, 122, 2);
    $to = substr($line, 124, 2);
    $td = substr($line, 126, 2);
    $clutch = substr($line, 128, 2);
    $consistency = substr($line, 130, 2);
    $PGDepth = substr($line, 132, 1);
    $SGDepth = substr($line, 133, 1);
    $SFDepth = substr($line, 134, 1);
    $PFDepth = substr($line, 135, 1);
    $CDepth = substr($line, 136, 1);
    $active = substr($line, 137, 1);
    $unknown_138139 = substr($line, 138, 2);
    $injuryDaysLeft = substr($line, 140, 4);
    $seasonGamesStarted = substr($line, 144, 4);
    $seasonGamesPlayed = substr($line, 148, 4);
    $seasonMIN = substr($line, 152, 4);
    $season2GM = substr($line, 156, 4);
    $season2GA = substr($line, 160, 4);
    $seasonFTM = substr($line, 164, 4);
    $seasonFTA = substr($line, 168, 4);
    $season3GM = substr($line, 172, 4);
    $season3GA = substr($line, 176, 4);
    $seasonORB = substr($line, 180, 4);
    $seasonDRB = substr($line, 184, 4);
    $seasonAST = substr($line, 188, 4);
    $seasonSTL = substr($line, 192, 4);
    $seasonTVR = substr($line, 196, 4);
    $seasonBLK = substr($line, 200, 4);
    $seasonPF = substr($line, 204, 4);
    $playoffGS = substr($line, 208, 4);
    $playoffMIN = substr($line, 212, 4);
    $playoff2GM = substr($line, 216, 4);
    $playoff2GA = substr($line, 220, 4);
    $playoffFTM = substr($line, 224, 4);
    $playoffFTA = substr($line, 228, 4);
    $playoff3GM = substr($line, 232, 4);
    $playoff3GA = substr($line, 236, 4);
    $playoffORB = substr($line, 240, 4);
    $playoffDRB = substr($line, 244, 4);
    $playoffAST = substr($line, 248, 4);
    $playoffSTL = substr($line, 252, 4);
    $playoffTVR = substr($line, 256, 4);
    $playoffBLK = substr($line, 260, 4);
    $playoffPF = substr($line, 264, 4);
    $talent = substr($line, 268, 2);
    $skill = substr($line, 270, 2);
    $intangibles = substr($line, 272, 2);
    $coach = substr($line, 274, 2);
    $loyalty = substr($line, 276, 2);
    $playingTime = substr($line, 278, 2);
    $playForWinner = substr($line, 280, 2);
    $tradition = substr($line, 282, 2);
    $security = substr($line, 284, 2);
    $exp = substr($line, 286, 2);
    $bird = substr($line, 288, 2);
    $currentContractYear = substr($line, 290, 2);
    $totalContractYears = substr($line, 292, 2);
    $unknown_294297 = substr($line, 294, 4);
    $contractYear1 = substr($line, 298, 4);
    $contractYear2 = substr($line, 302, 4);
    $contractYear3 = substr($line, 306, 4);
    $contractYear4 = substr($line, 310, 4);
    $contractYear5 = substr($line, 314, 4);
    $contractYear6 = substr($line, 318, 4);
    $unknown_322325 = substr($line, 322, 4);
    $draftRound = substr($line, 326, 2);
    $draftPickNumber = substr($line, 328, 2);
    $unknown_330 = substr($line, 330, 1);
    $contractOwnedBy = substr($line, 331, 2);
    $unknown_334340 = substr($line, 333, 8);
    $seasonHighPTS = substr($line, 341, 2);
    $seasonHighREB = substr($line, 343, 2);
    $seasonHighAST = substr($line, 345, 2);
    $seasonHighSTL = substr($line, 347, 2);
    $seasonHighBLK = substr($line, 349, 2);
    $seasonHighDoubleDoubles = substr($line, 351, 2);
    $seasonHighTripleDoubles = substr($line, 353, 2);
    $seasonPlayoffHighPTS = substr($line, 355, 2);
    $seasonPlayoffHighREB = substr($line, 357, 2);
    $seasonPlayoffHighAST = substr($line, 359, 2);
    $seasonPlayoffHighSTL = substr($line, 361, 2);
    $seasonPlayoffHighBLK = substr($line, 363, 2);
    $careerSeasonHighPTS = substr($line, 365, 6);
    $careerSeasonHighREB = substr($line, 371, 6);
    $careerSeasonHighAST = substr($line, 377, 6);
    $careerSeasonHighSTL = substr($line, 383, 6);
    $careerSeasonHighBLK = substr($line, 389, 6);
    $careerSeasonHighDoubleDoubles = substr($line, 395, 6);
    $careerSeasonHighTripleDoubles = substr($line, 401, 6);
    $careerPlayoffHighPTS = substr($line, 407, 6);
    $careerPlayoffHighREB = substr($line, 413, 6);
    $careerPlayoffHighAST = substr($line, 419, 6);
    $careerPlayoffHighSTL = substr($line, 425, 6);
    $careerPlayoffHighBLK = substr($line, 431, 6);
    $careerGP = substr($line, 437, 5);
    $careerMIN = substr($line, 442, 5);
    $career2GM = substr($line, 447, 5);
    $career2GA = substr($line, 452, 5);
    $careerFTM = substr($line, 457, 5);
    $careerFTA = substr($line, 462, 5);
    $career3GM = substr($line, 467, 5);
    $career3GA = substr($line, 472, 5);
    $careerORB = substr($line, 477, 5);
    $careerDRB = substr($line, 482, 5);
    $careerAST = substr($line, 487, 5);
    $careerSTL = substr($line, 492, 5);
    $careerTVR = substr($line, 497, 5);
    $careerBLK = substr($line, 502, 5);
    $careerPF = substr($line, 507, 5);
    // 512-543 = blank
    $unknown_544549 = substr($line, 544, 6);
    $heightInches = substr($line, 550, 2);
    $weight = substr($line, 552, 3);
    $rating2GA = substr($line, 555, 3);
    $rating2GP = substr($line, 558, 3);
    $ratingFTA = substr($line, 561, 3);
    $ratingFTP = substr($line, 564, 3);
    $rating3GA = substr($line, 567, 3);
    $rating3GP = substr($line, 570, 3);
    $ratingORB = substr($line, 573, 3);
    $ratingDRB = substr($line, 576, 3);
    $ratingAST = substr($line, 579, 3);
    $ratingSTL = substr($line, 582, 3);
    $ratingTVR = substr($line, 585, 3);
    $ratingBLK = substr($line, 588, 3);
    $ratingOO = substr($line, 591, 2);
    $ratingDO = substr($line, 593, 2);
    $ratingPO = substr($line, 595, 2);
    $ratingTO = substr($line, 597, 2);
    $ratingOD = substr($line, 599, 2);
    $ratingDD = substr($line, 601, 2);
    $ratingPD = substr($line, 603, 2);
    $ratingTD = substr($line, 605, 2);

    if ($ordinal <= 1440) {
        $playerUpdateQuery = "INSERT INTO ibl_plr__pure_plr
            (   `ordinal`,
                `name`,
                `age`,
                `pid`,
                `tid`,
                `peak`,
                `pos`,
                `realLifeGP`,
                `realLifeMIN`,
                `realLifeFGM`,
                `realLifeFGA`,
                `realLifeFTM`,
                `realLifeFTA`,
                `realLife3GM`,
                `realLife3GA`,
                `realLifeORB`,
                `realLifeDRB`,
                `realLifeAST`,
                `realLifeSTL`,
                `realLifeTVR`,
                `realLifeBLK`,
                `realLifePF`,
                `oo`,
                `od`,
                `do`,
                `dd`,
                `po`,
                `pd`,
                `to`,
                `td`,
                `Clutch`,
                `Consistency`,
                `PGDepth`,
                `SGDepth`,
                `SFDepth`,
                `PFDepth`,
                `CDepth`,
                `active`,
                `138-139`,
                `injuryDaysLeft`,
                `stats_gs`,
                `stats_gm`,
                `stats_min`,
                `stats_fgm`,
                `stats_fga`,
                `stats_ftm`,
                `stats_fta`,
                `stats_3gm`,
                `stats_3ga`,
                `stats_orb`,
                `stats_drb`,
                `stats_ast`,
                `stats_stl`,
                `stats_to`,
                `stats_blk`,
                `stats_pf`,
                `talent`,
                `skill`,
                `intangibles`,
                `coach`,
                `loyalty`,
                `playingTime`,
                `winner`,
                `tradition`,
                `security`,
                `exp`,
                `bird`,
                `cy`,
                `cyt`,
                `294-297`,
                `cy1`,
                `cy2`,
                `cy3`,
                `cy4`,
                `cy5`,
                `cy6`,
                `322-325`,
                `draftround`,
                `draftpickno`,
                `330`,
                `contractOwnedByTid`,
                `334-340`,
                `sh_pts`,
                `sh_reb`,
                `sh_ast`,
                `sh_stl`,
                `sh_blk`,
                `s_dd`,
                `s_td`,
                `sp_pts`,
                `sp_reb`,
                `sp_ast`,
                `sp_stl`,
                `sp_blk`,
                `ch_pts`,
                `ch_reb`,
                `ch_ast`,
                `ch_stl`,
                `ch_blk`,
                `c_dd`,
                `c_td`,
                `cp_pts`,
                `cp_reb`,
                `cp_ast`,
                `cp_stl`,
                `cp_blk`,
                `car_gm`,
                `car_min`,
                `car_fgm`,
                `car_fga`,
                `car_ftm`,
                `car_fta`,
                `car_tgm`,
                `car_tga`,
                `car_orb`,
                `car_drb`,
                `car_ast`,
                `car_stl`,
                `car_to`,
                `car_blk`,
                `car_pf`,
                `544-549`,
                `heightInches`,
                `weight`,
                `r_fga`,
                `r_fgp`,
                `r_fta`,
                `r_ftp`,
                `r_tga`,
                `r_tgp`,
                `r_orb`,
                `r_drb`,
                `r_ast`,
                `r_stl`,
                `r_tvr`,
                `r_blk`,
                `r_oo`,
                `r_do`,
                `r_po`,
                `r_to`,
                `r_od`,
                `r_dd`,
                `r_pd`,
                `r_td`
            )
        VALUES
            ($ordinal,
            '$name',
            $age,
            $pid,
            $tid,
            $peak,
            '$pos',
            $realLifeGP,
            $realLifeMIN,
            $realLifeFGM,
            $realLifeFGA,
            $realLifeFTM,
            $realLifeFTA,
            $realLife3GM,
            $realLife3GA,
            $realLifeORB,
            $realLifeDRB,
            $realLifeAST,
            $realLifeSTL,
            $realLifeTVR,
            $realLifeBLK,
            $realLifePF,
            $oo,
            $od,
            $do,
            $dd,
            $po,
            $pd,
            $to,
            $td,
            $clutch,
            $consistency,
            $PGDepth,
            $SGDepth,
            $SFDepth,
            $PFDepth,
            $CDepth,
            $active,
            $unknown_138139,
            $injuryDaysLeft,
            $seasonGamesStarted,
            $seasonGamesPlayed,
            $seasonMIN,
            $season2GM,
            $season2GA,
            $seasonFTM,
            $seasonFTA,
            $season3GM,
            $season3GA,
            $seasonORB,
            $seasonDRB,
            $seasonAST,
            $seasonSTL,
            $seasonTVR,
            $seasonBLK,
            $seasonPF,
            $talent,
            $skill,
            $intangibles,
            $coach,
            $loyalty,
            $playingTime,
            $playForWinner,
            $tradition,
            $security,
            $exp,
            $bird,
            $currentContractYear,
            $totalContractYears,
            $unknown_294297,
            $contractYear1,
            $contractYear2,
            $contractYear3,
            $contractYear4,
            $contractYear5,
            $contractYear6,
            $unknown_322325,
            $draftRound,
            $draftPickNumber,
            $unknown_330,
            $contractOwnedBy,
            '$unknown_334340',
            $seasonHighPTS,
            $seasonHighREB,
            $seasonHighAST,
            $seasonHighSTL,
            $seasonHighBLK,
            $seasonHighDoubleDoubles,
            $seasonHighTripleDoubles,
            $seasonPlayoffHighPTS,
            $seasonPlayoffHighREB,
            $seasonPlayoffHighAST,
            $seasonPlayoffHighSTL,
            $seasonPlayoffHighBLK,
            $careerSeasonHighPTS,
            $careerSeasonHighREB,
            $careerSeasonHighAST,
            $careerSeasonHighSTL,
            $careerSeasonHighBLK,
            $careerSeasonHighDoubleDoubles,
            $careerSeasonHighTripleDoubles,
            $careerPlayoffHighPTS,
            $careerPlayoffHighREB,
            $careerPlayoffHighAST,
            $careerPlayoffHighSTL,
            $careerPlayoffHighBLK,
            $careerGP,
            $careerMIN,
            $career2GM,
            $career2GA,
            $careerFTM,
            $careerFTA,
            $career3GM,
            $career3GA,
            $careerORB,
            $careerDRB,
            $careerAST,
            $careerSTL,
            $careerTVR,
            $careerBLK,
            $careerPF,
            $unknown_544549,
            $heightInches,
            $weight,
            $rating2GA,
            $rating2GP,
            $ratingFTA,
            $ratingFTP,
            $rating3GA,
            $rating3GP,
            $ratingORB,
            $ratingDRB,
            $ratingAST,
            $ratingSTL,
            $ratingTVR,
            $ratingBLK,
            $ratingOO,
            $ratingDO,
            $ratingPO,
            $ratingTO,
            $ratingOD,
            $ratingDD,
            $ratingPD,
            $ratingTD
        )
        ON DUPLICATE KEY UPDATE
            `ordinal` = $ordinal,
            `name` = '$name',
            `age` = $age,
            `pid` = $pid,
            `tid` = $tid,
            `peak` = $peak,
            `pos` = '$pos',
            `realLifeGP` = $realLifeGP,
            `realLifeMIN` = $realLifeMIN,
            `realLifeFGM` = $realLifeFGM,
            `realLifeFGA` = $realLifeFGA,
            `realLifeFTM` = $realLifeFTM,
            `realLifeFTA` = $realLifeFTA,
            `realLife3GM` = $realLife3GM,
            `realLife3GA` = $realLife3GA,
            `realLifeORB` = $realLifeORB,
            `realLifeDRB` = $realLifeDRB,
            `realLifeAST` = $realLifeAST,
            `realLifeSTL` = $realLifeSTL,
            `realLifeTVR` = $realLifeTVR,
            `realLifeBLK` = $realLifeBLK,
            `realLifePF` = $realLifePF,
            `oo` = $oo,
            `od` = $od,
            `do` = $do,
            `dd` = $dd,
            `po` = $po,
            `pd` = $pd,
            `to` = $to,
            `td` = $td,
            `Clutch` = $clutch,
            `Consistency` = $consistency,
            `PGDepth` = $PGDepth,
            `SGDepth` = $SGDepth,
            `SFDepth` = $SFDepth,
            `PFDepth` = $PFDepth,
            `CDepth` = $CDepth,
            `active` = $active,
            `138-139` = $unknown_138139,
            `injuryDaysLeft` = $injuryDaysLeft,
            `stats_gs` = $seasonGamesStarted,
            `stats_gm` = $seasonGamesPlayed,
            `stats_min` = $seasonMIN,
            `stats_fgm` = $season2GM,
            `stats_fga` = $season2GA,
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
            `294-297` = $unknown_294297,
            `cy1` = $contractYear1,
            `cy2` = $contractYear2,
            `cy3` = $contractYear3,
            `cy4` = $contractYear4,
            `cy5` = $contractYear5,
            `cy6` = $contractYear6,
            `322-325` = $unknown_322325,
            `draftround` = $draftRound,
            `draftpickno` = $draftPickNumber,
            `330` = $unknown_330,
            `contractOwnedByTid` = $contractOwnedBy,
            `334-340` = '$unknown_334340',
            `sh_pts` = $seasonHighPTS,
            `sh_reb` = $seasonHighREB,
            `sh_ast` = $seasonHighAST,
            `sh_stl` = $seasonHighSTL,
            `sh_blk` = $seasonHighBLK,
            `s_dd` = $seasonHighDoubleDoubles,
            `s_td` = $seasonHighTripleDoubles,
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
            `c_dd` = $careerSeasonHighDoubleDoubles,
            `c_td` = $careerSeasonHighTripleDoubles,
            `cp_pts` = $careerPlayoffHighPTS,
            `cp_reb` = $careerPlayoffHighREB,
            `cp_ast` = $careerPlayoffHighAST,
            `cp_stl` = $careerPlayoffHighSTL,
            `cp_blk` = $careerPlayoffHighBLK,
            `car_gm` = $careerGP,
            `car_min` = $careerMIN,
            `car_fgm` = $career2GM,
            `car_fga` = $career2GA,
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
            `544-549` = $unknown_544549,
            `heightInches` = $heightInches,
            `weight` = $weight,
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
            `r_tvr` = $ratingTVR,
            `r_blk` = $ratingBLK,
            `r_oo` = $ratingOO,
            `r_do` = $ratingDO,
            `r_po` = $ratingPO,
            `r_to` = $ratingTO,
            `r_od` = $ratingOD,
            `r_dd` = $ratingDD,
            `r_pd` = $ratingPD,
            `r_td` = $ratingTD;";
        if ($pid != 0) {
            if ($db->sql_query($playerUpdateQuery)) {
                echo $pid . '<p>';
            } else {
                die('Invalid query: ' . $db->sql_error());
            }
        }
    } elseif ($ordinal >= 1441 && $ordinal <= 1504) {
        if ($ordinal >= 1441 && $ordinal <= 1472) {
            $sideOfTheBall = 'offense';
        }

        if ($ordinal >= 1473 && $ordinal <= 1504) {
            $ordinal = $ordinal - 32; // Must use and adjust ordinal because JSB bugs out on team names for defensive stats
            $sideOfTheBall = 'defense';
        }

        $teamUpdateQuery = 'UPDATE `ibl_team_' . $sideOfTheBall . '_stats__test`
            SET
            `games` = ' . $seasonGamesPlayed . ',
            `minutes` = ' . ($seasonGamesPlayed * 48) . ',
            `fgm` = ' . ($season2GM + $season3GM) . ',
            `fga` = ' . ($season2GA + $season3GA) . ',
            `ftm` = ' . $seasonFTM . ',
            `fta` = ' . $seasonFTA . ',
            `tgm` = ' . $season3GM . ',
            `tga` = ' . $season3GA . ',
            `orb` = ' . $seasonORB . ',
            `reb` = ' . ($seasonORB + $seasonDRB) . ',
            `ast` = ' . $seasonAST . ',
            `stl` = ' . $seasonSTL . ',
            `tvr` = ' . $seasonTVR . ',
            `blk` = ' . $seasonBLK . ',
            `pf` = ' . $seasonPF . '
            WHERE
            `ordinal` = \'' . $ordinal . '\';';
        if ($db->sql_query($teamUpdateQuery)) {
            echo $teamUpdateQuery . '<br>';
        }

    }
}
fclose($plrFile);

$i = 0;
while ($i < $numRowsTeamIDsNames) {
    $teamname = $db->sql_result($queryTeamIDsNames, $i, 'team_name');
    $teamID = $db->sql_result($queryTeamIDsNames, $i, 'teamid');
    $teamnameUpdateQuery = "UPDATE `ibl_plr__test` SET `teamname` = '$teamname' WHERE `tid` = $teamID;";
    if ($db->sql_query($teamnameUpdateQuery)) {
        echo $teamnameUpdateQuery . '<br>';
    }

    $i++;
}

echo "done.";
