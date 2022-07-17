<?php

require 'mainfile.php';

$query = "SELECT * FROM nuke_ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY TeamID ASC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

$i = 0;

while ($i < $num) {
    $tid = $db->sql_result($result, $i, "TeamID");
    $Team = $db->sql_result($result, $i, "Team");
    $i++;
    list($wins, $losses, $gb) = record($tid);
    $query3 = "UPDATE nuke_ibl_power SET win = $wins, loss = $losses, gb = $gb WHERE TeamID = $tid;";

    $result3 = $db->sql_query($query3);
    $ranking = ranking($tid, $wins, $losses);
    $query4 = "UPDATE nuke_ibl_power SET ranking = $ranking WHERE TeamID = $tid;";
    $result4 = $db->sql_query($query4);
    echo "Updating $Team wins $wins and losses $losses and ranking $ranking<br>";
}

function record($tid)
{
    global $db;

    $query = "SELECT * FROM ibl_schedule WHERE (Visitor = $tid OR Home = $tid) AND BoxID > 0 ORDER BY Date ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);
    $wins = 0;
    $losses = 0;
    $i = 0;
    while ($i < $num) {
        $visitor = $db->sql_result($result, $i, "Visitor");
        $VScore = $db->sql_result($result, $i, "VScore");
        $home = $db->sql_result($result, $i, "Home");
        $HScore = $db->sql_result($result, $i, "HScore");

        if ($tid == $visitor) {
            if ($VScore > $HScore) {
                $wins = $wins + 1;
            } else {
                $losses = $losses + 1;
            }
        } else {
            if ($VScore > $HScore) {
                $losses = $losses + 1;
            } else {
                $wins = $wins + 1;
            }
        }
        $i++;
    }
    $gb = ($wins / 2) - ($losses / 2);
    return array($wins, $losses, $gb);
}

function ranking($tid, $wins, $losses)
{
    global $db;

    $query = "SELECT * FROM ibl_schedule WHERE Visitor = $tid AND BoxID > 0 ORDER BY Date ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);
    $winpoints = 0;
    $losspoints = 0;
    $i = 0;
    while ($i < $num) {
        $visitor = $db->sql_result($result, $i, "Visitor");
        $VScore = $db->sql_result($result, $i, "VScore");
        $home = $db->sql_result($result, $i, "Home");
        $HScore = $db->sql_result($result, $i, "HScore");

        $query2 = "SELECT * FROM nuke_ibl_power WHERE TeamID = $home";
        $result2 = $db->sql_query($query2);
        $oppwins = $db->sql_result($result2, 0, "win");
        $opploss = $db->sql_result($result2, 0, "loss");

        if ($VScore > $HScore) {
            $winpoints = $winpoints + $oppwins;
        } else {
            $losspoints = $losspoints + $opploss;
        }
        $i++;
    }

    $query = "SELECT * FROM ibl_schedule WHERE Home = $tid AND BoxID > 0 ORDER BY Date ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);
    $i = 0;
    while ($i < $num) {
        $visitor = $db->sql_result($result, $i, "Visitor");
        $VScore = $db->sql_result($result, $i, "VScore");
        $home = $db->sql_result($result, $i, "Home");
        $HScore = $db->sql_result($result, $i, "HScore");

        $query2 = "SELECT * FROM nuke_ibl_power WHERE TeamID = $visitor";
        $result2 = $db->sql_query($query2);
        $oppwins = $db->sql_result($result2, 0, "win");
        $opploss = $db->sql_result($result2, 0, "loss");

        if ($VScore > $HScore) {
            $losspoints = $losspoints + $opploss;
        } else {
            $winpoints = $winpoints + $oppwins;
        }
        $i++;
    }
    $winpoints = $winpoints + $wins;
    $losspoints = $losspoints + $losses;
    $ranking = round(($winpoints / ($winpoints + $losspoints)) * 100, 1);
    return $ranking;
}
