<?php

require 'mainfile.php';
$sharedFunctions = new Shared($db);

$offer_id = $_POST['offer'];
    
function checkIfPidExists($pid, $db) {
    $queryCheckIfPidExists = "SELECT 1 FROM ibl_plr WHERE pid = $pid;";
    $resultCheckIfPidExists = $db->sql_query($queryCheckIfPidExists);
    $pidResult = $db->sql_result($resultCheckIfPidExists, 0);

    if ($pidResult == NULL) {
        return $pid;
    } else {
        $pid += 2;
        return checkIfPidExists($pid, $db);
    }
}

if ($offer_id != NULL) {
    $query0 = "SELECT * FROM ibl_trade_info WHERE tradeofferid = '$offer_id'";
    $result0 = $db->sql_query($query0);
    $num0 = $db->sql_numrows($result0);
    
    $i = 0;
    
    $storytext = "";

    while ($i < $num0) {
        $itemid = $db->sql_result($result0, $i, "itemid");
        $itemtype = $db->sql_result($result0, $i, "itemtype");
        $from = $db->sql_result($result0, $i, "from");
        $to = $db->sql_result($result0, $i, "to");
    
        if ($itemtype == 'cash') {
            $itemid = checkIfPidExists($itemid, $db);
            
            $tidSendingTeam = $sharedFunctions->getTidFromTeamname($from);
            $tidReceivingTeam = $sharedFunctions->getTidFromTeamname($to);
            $queryCashDetails = "SELECT * FROM ibl_trade_cash WHERE tradeOfferID = $offer_id AND sendingTeam = '$from';";
            $cashDetails = $db->sql_fetchrow($db->sql_query($queryCashDetails));

            $cashYear[1] = $cashDetails['cy1'];
            $cashYear[2] = $cashDetails['cy2'];
            $cashYear[3] = $cashDetails['cy3'];
            $cashYear[4] = $cashDetails['cy4'];
            $cashYear[5] = $cashDetails['cy5'];
            $cashYear[6] = $cashDetails['cy6'];

            $queryInsertPositiveCashRow = "INSERT INTO `ibl_plr` (`HOF`, `ordinal`, `pid`, `name`, `nickname`, `age`, `peak`, `tid`, `teamname`, `pos`, `altpos`, `sta`, `oo`, `od`, `do`, `dd`, `po`, `pd`, `to`, `td`, `Clutch`, `Consistency`, `PGDepth`, `SGDepth`, `SFDepth`, `PFDepth`, `CDepth`, `active`, `dc_PGDepth`, `dc_SGDepth`, `dc_SFDepth`, `dc_PFDepth`, `dc_CDepth`, `dc_active`, `dc_minutes`, `dc_of`, `dc_df`, `dc_oi`, `dc_di`, `dc_bh`, `stats_gs`, `stats_gm`, `stats_min`, `stats_fgm`, `stats_fga`, `stats_ftm`, `stats_fta`, `stats_3gm`, `stats_3ga`, `stats_orb`, `stats_drb`, `stats_ast`, `stats_stl`, `stats_to`, `stats_blk`, `stats_pf`, `talent`, `skill`, `intangibles`, `coach`, `loyalty`, `playingTime`, `winner`, `tradition`, `security`,
                `exp`, `bird`, `cy`, `cyt`, `cy1`, `cy2`, `cy3`, `cy4`, `cy5`, `cy6`, `sh_pts`, `sh_reb`, `sh_ast`, `sh_stl`, `sh_blk`, `s_dd`, `s_td`, `sp_pts`, `sp_reb`, `sp_ast`, `sp_stl`, `sp_blk`, `ch_pts`, `ch_reb`, `ch_ast`, `ch_stl`, `ch_blk`, `c_dd`, `c_td`, `cp_pts`, `cp_reb`, `cp_ast`, `cp_stl`, `cp_blk`, `car_gm`, `car_min`, `car_fgm`, `car_fga`, `car_ftm`, `car_fta`, `car_tgm`, `car_tga`, `car_orb`, `car_drb`, `car_reb`, `car_ast`, `car_stl`, `car_to`, `car_blk`, `car_pf`, `car_pts`, `r_fga`, `r_fgp`, `r_fta`, `r_ftp`, `r_tga`, `r_tgp`, `r_orb`, `r_drb`, `r_ast`, `r_stl`, `r_to`, `r_blk`, `r_foul`, `draftround`, `draftedby`, `draftedbycurrentname`, `draftyear`, `draftpickno`, `injured`, `htft`, `htin`, `wt`, `retired`, `college`, `collegeid`, `car_playoff_min`, `car_preseason_min`, `droptime`, `temp`, `fgpct`, `poschange`)
                VALUES ('0', '100000', '$itemid', '| <B>Cash to $to</B>', '', '0', '0', '$tidSendingTeam', '$from', '', '', '0', '0', '0', '0', '0', '0', '0', '0', '0', '', '', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '', '', '', '', '', '',
                '1', '0', '1', '1', '$cashYear[1]', '$cashYear[2]', '$cashYear[3]', '$cashYear[4]', '$cashYear[5]', '$cashYear[6]', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '', '', '0', '0', '0', '', '', '', '0', '', '0', '0', '0', '0', '0', '', '0');";
            $resultInsertPositiveCashRow = $db->sql_query($queryInsertPositiveCashRow);

            $itemid++;

            $queryInsertNegativeCashRow = "INSERT INTO `ibl_plr` (`HOF`, `ordinal`, `pid`, `name`, `nickname`, `age`, `peak`, `tid`, `teamname`, `pos`, `altpos`, `sta`, `oo`, `od`, `do`, `dd`, `po`, `pd`, `to`, `td`, `Clutch`, `Consistency`, `PGDepth`, `SGDepth`, `SFDepth`, `PFDepth`, `CDepth`, `active`, `dc_PGDepth`, `dc_SGDepth`, `dc_SFDepth`, `dc_PFDepth`, `dc_CDepth`, `dc_active`, `dc_minutes`, `dc_of`, `dc_df`, `dc_oi`, `dc_di`, `dc_bh`, `stats_gs`, `stats_gm`, `stats_min`, `stats_fgm`, `stats_fga`, `stats_ftm`, `stats_fta`, `stats_3gm`, `stats_3ga`, `stats_orb`, `stats_drb`, `stats_ast`, `stats_stl`, `stats_to`, `stats_blk`, `stats_pf`, `talent`, `skill`, `intangibles`, `coach`, `loyalty`, `playingTime`, `winner`, `tradition`, `security`,
                `exp`, `bird`, `cy`, `cyt`, `cy1`, `cy2`, `cy3`, `cy4`, `cy5`, `cy6`, `sh_pts`, `sh_reb`, `sh_ast`, `sh_stl`, `sh_blk`, `s_dd`, `s_td`, `sp_pts`, `sp_reb`, `sp_ast`, `sp_stl`, `sp_blk`, `ch_pts`, `ch_reb`, `ch_ast`, `ch_stl`, `ch_blk`, `c_dd`, `c_td`, `cp_pts`, `cp_reb`, `cp_ast`, `cp_stl`, `cp_blk`, `car_gm`, `car_min`, `car_fgm`, `car_fga`, `car_ftm`, `car_fta`, `car_tgm`, `car_tga`, `car_orb`, `car_drb`, `car_reb`, `car_ast`, `car_stl`, `car_to`, `car_blk`, `car_pf`, `car_pts`, `r_fga`, `r_fgp`, `r_fta`, `r_ftp`, `r_tga`, `r_tgp`, `r_orb`, `r_drb`, `r_ast`, `r_stl`, `r_to`, `r_blk`, `r_foul`, `draftround`, `draftedby`, `draftedbycurrentname`, `draftyear`, `draftpickno`, `injured`, `htft`, `htin`, `wt`, `retired`, `college`, `collegeid`, `car_playoff_min`, `car_preseason_min`, `droptime`, `temp`, `fgpct`, `poschange`)
                VALUES ('0', '100000', '$itemid', '| <B>Cash from $from</B>', '', '0', '0', '$tidReceivingTeam', '$to', '', '', '0', '0', '0', '0', '0', '0', '0', '0', '0', '', '', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '', '', '', '', '', '',
                '1', '0', '1', '1', '-$cashYear[1]', '-$cashYear[2]', '-$cashYear[3]', '-$cashYear[4]', '-$cashYear[5]', '-$cashYear[6]', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '', '', '0', '0', '0', '', '', '', '0', '', '0', '0', '0', '0', '0', '', '0');";
            $resultInsertNegativeCashRow = $db->sql_query($queryInsertNegativeCashRow);

            if ($resultInsertPositiveCashRow AND $resultInsertNegativeCashRow) {
                $tradeLine = "The $from send $cashYear[1] $cashYear[2] $cashYear[3] $cashYear[4] $cashYear[5] $cashYear[6] in cash to the $to.<br>";
                $storytext .= $tradeLine;
            }
        } elseif ($itemtype == 0) {
            $queryj = "SELECT * FROM ibl_draft_picks WHERE `pickid` = '$itemid'";
            $resultj = $db->sql_query($queryj);
            $tradeLine = "The $from send the " . $db->sql_result($resultj, 0, "year") . " " . $db->sql_result($resultj, 0, "teampick") . " Round " . $db->sql_result($resultj, 0, "round") . " draft pick to the $to.<br>";
            $storytext .= $tradeLine;
    
            $queryi = 'UPDATE ibl_draft_picks SET `ownerofpick` = "' . $to . '" WHERE `pickid` = ' . $itemid . ' LIMIT 1;';
            $resulti = $db->sql_query($queryi);
        } elseif ($itemtype == 1) {
            $tid = $sharedFunctions->getTidFromTeamname($to);
    
            $queryk = "SELECT * FROM ibl_plr WHERE pid = '$itemid'";
            $resultk = $db->sql_query($queryk);
    
            $tradeLine = "The $from send " . $db->sql_result($resultk, 0, "pos") . " " . $db->sql_result($resultk, 0, "name") . " to the $to.<br>";
            $storytext .= $tradeLine;
    
            $queryi = 'UPDATE ibl_plr SET `teamname` = "' . $to . '", `tid` = ' . $tid . ' WHERE `pid` = ' . $itemid . ' LIMIT 1;';
            $resulti = $db->sql_query($queryi);
        }
    
        $currentSeasonPhase = $sharedFunctions->getCurrentSeasonPhase();
        if ($currentSeasonPhase == "Playoffs" or $currentSeasonPhase == "Draft" or $currentSeasonPhase == "Free Agency") {
            $queryInsert = "INSERT INTO ibl_trade_queue (query, tradeline) VALUES ('$queryi', '$tradeLine');";
            $db->sql_query("$queryInsert");
        }
    
        $i++;
    }
    
    $timestamp = date('Y-m-d H:i:s', time());
    $storytitle = "$from and $to make a trade.";
    
    $querystor = "INSERT INTO nuke_stories
                (catid,
                 aid,
                 title,
                 time,
                 hometext,
                 topic,
                 informant,
                 counter,
                 alanguage)
    VALUES      ('2',
                 'Associated Press',
                 '$storytitle',
                 '$timestamp',
                 '$storytext',
                 '31',
                 'Associated Press',
                 '0',
                 'english');";
    $resultstor = $db->sql_query($querystor);
    
    if (isset($resultstor) and $_SERVER['SERVER_NAME'] != "localhost") {
        $recipient = 'ibldepthcharts@gmail.com';
        mail($recipient, $storytitle, $storytext, "From: trades@iblhoops.net");
    }
    
    $fromDiscordID = $sharedFunctions->getDiscordIDFromTeamname($from);
    $toDiscordID = $sharedFunctions->getDiscordIDFromTeamname($to);
    $discordText = "<@!$fromDiscordID> and <@!$toDiscordID> agreed to a trade:<br>" . $storytext;
    
    Discord::postToChannel('#trades', $discordText);
    
    $queryClearInfo = "DELETE FROM ibl_trade_info WHERE `tradeofferid` = '$offer_id'";
    $resultClearInfo = $db->sql_query($queryClearInfo);
    $queryClearCash = "DELETE FROM ibl_trade_cash WHERE `tradeOfferID` = '$offer_id'";
    $resultClearCash = $db->sql_query($queryClearCash);
} else {
    echo "Nothing to see here!";
    exit;
}

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="5;url=modules.php?name=Trading&op=reviewtrade">
</HEAD><BODY>
Trade Offer accepted! Redirecting you to the Trade Review page...
</BODY></HTML>
