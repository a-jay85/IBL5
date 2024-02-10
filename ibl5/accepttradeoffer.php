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
    $queryTradeRows = "SELECT * FROM ibl_trade_info WHERE tradeofferid = '$offer_id'";
    $resultTradeRows = $db->sql_query($queryTradeRows);
    $numTradeRows = $db->sql_numrows($resultTradeRows);

    $i = 0;
    
    $storytext = "";

    while ($i < $numTradeRows) {
        $itemid = $db->sql_result($resultTradeRows, $i, "itemid");
        $itemtype = $db->sql_result($resultTradeRows, $i, "itemtype");
        $from = $db->sql_result($resultTradeRows, $i, "from");
        $to = $db->sql_result($resultTradeRows, $i, "to");
    
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

            $contractCurrentYear = 1;
            $currentSeasonPhase = $sharedFunctions->getCurrentSeasonPhase();
            if ($currentSeasonPhase == "Free Agency") {
                $contractCurrentYear = 0;
            }
            
            if ($cashYear[6] != 0) {
                $contractTotalYears = 6;
            } elseif ($cashYear[5] != 0) {
                $contractTotalYears = 5;
            } elseif ($cashYear[4] != 0) {
                $contractTotalYears = 4;
            } elseif ($cashYear[3] != 0) {
                $contractTotalYears = 3;
            } elseif ($cashYear[2] != 0) {
                $contractTotalYears = 2;
            } else {
                $contractTotalYears = 1;
            }

            $queryInsertPositiveCashRow = "INSERT INTO `ibl_plr` 
                (`ordinal`, 
                `pid`, 
                `name`, 
                `tid`, 
                `teamname`, 
                `exp`, 
                `cy`, 
                `cyt`, 
                `cy1`, 
                `cy2`, 
                `cy3`, 
                `cy4`, 
                `cy5`, 
                `cy6`) 
            VALUES
                ('100000',
                '$itemid',
                '| <B>Cash to $to</B>',
                '$tidSendingTeam',
                '$from',
                '$contractCurrentYear',
                '$contractCurrentYear',
                '$contractTotalYears',
                '$cashYear[1]',
                '$cashYear[2]',
                '$cashYear[3]',
                '$cashYear[4]',
                '$cashYear[5]',
                '$cashYear[6]'); ";
            $resultInsertPositiveCashRow = $db->sql_query($queryInsertPositiveCashRow);

            $itemid++;

            $queryInsertNegativeCashRow = "INSERT INTO `ibl_plr` 
                (`ordinal`,
                `pid`,
                `name`,
                `tid`,
                `teamname`,
                `exp`,
                `cy`,
                `cyt`,
                `cy1`,
                `cy2`,
                `cy3`,
                `cy4`,
                `cy5`,
                `cy6`)
            VALUES
                ('100000',
                '$itemid',
                '| <B>Cash from $from</B>',
                '$tidReceivingTeam',
                '$to',
                '$contractCurrentYear',
                '$contractCurrentYear',
                '$contractTotalYears',
                '-$cashYear[1]',
                '-$cashYear[2]',
                '-$cashYear[3]',
                '-$cashYear[4]',
                '-$cashYear[5]',
                '-$cashYear[6]'); ";
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
