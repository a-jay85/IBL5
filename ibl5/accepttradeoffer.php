<?php

require 'mainfile.php';
$sharedFunctions = new Shared($db);

$offer_id = $_POST['offer'];

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

    if ($itemtype == 0) {
        $queryj = "SELECT * FROM ibl_draft_picks WHERE `pickid` = '$itemid'";
        $resultj = $db->sql_query($queryj);
        $tradeLine = "The $from send the " . $db->sql_result($resultj, 0, "year") . " " . $db->sql_result($resultj, 0, "teampick") . " Round " . $db->sql_result($resultj, 0, "round") . " draft pick to the $to.<br>";
        $storytext .= $tradeLine;

        $queryi = 'UPDATE ibl_draft_picks SET `ownerofpick` = "' . $to . '" WHERE `pickid` = ' . $itemid . ' LIMIT 1;';
        $resulti = $db->sql_query($queryi);
    } else {
        $queryj = "SELECT * FROM ibl_team_info WHERE team_name = '$to'";
        $resultj = $db->sql_query($queryj);
        $tid = $db->sql_result($resultj, 0, "teamid");

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
             'english') ";
$resultstor = $db->sql_query($querystor);

if (isset($resultstor) and $_SERVER['SERVER_NAME'] != "localhost") {
    $recipient = 'ibldepthcharts@gmail.com';
    mail($recipient, $storytitle, $storytext, "From: trades@iblhoops.net");
}

$fromDiscordID = $sharedFunctions->getDiscordIDFromTeamname($from);
$toDiscordID = $sharedFunctions->getDiscordIDFromTeamname($to);
$discordText = "<@!$fromDiscordID> and <@!$toDiscordID> agreed to a trade:<br>" . $storytext;

Discord::postToChannel('#trades', $discordText);
// Discord::postToChannel('#trades', $storytext);

$queryclear = "DELETE FROM ibl_trade_info WHERE `tradeofferid` = '$offer_id'";
$resultclear = $db->sql_query($queryclear);

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="2;url=modules.php?name=Trading&op=reviewtrade">
</HEAD><BODY>
Trade Offer accepted! Redirecting you to the Trade Review page...
</BODY></HTML>
