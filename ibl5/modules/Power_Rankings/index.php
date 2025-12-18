<?php

global $mysqli_db;
$season = new Season($mysqli_db);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

Nuke\Header::header();

OpenTable();
echo "<center><font class=\"storytitle\">" . ($season->endingYear - 1) . "-$season->endingYear IBL Power Rankings</font></center>\n\n";
echo "<p>\n\n";

$query = "SELECT * FROM ibl_power WHERE TeamID BETWEEN 1 AND 32 ORDER BY ranking DESC";
$result = $mysqli_db->query($query);
$num = ($result instanceof mysqli_result) ? $result->num_rows : 0;

echo "<table width=\"500\" cellpadding=\"4\" cellspacing=\"0\" border=\"0\" align=center>\n";
echo "<tr>\n";
echo "\t<td align=right>\n";
echo "\t\t<font class=\"storytitle\">Rank\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Team\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Record\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Home\n";
echo "\t</td>\n";
echo "\t<td align=center>\n";
echo "\t\t<font class=\"storytitle\">Away\n";
echo "\t</td>\n";
echo "\t<td align=right>\n";
echo "\t\t<font class=\"storytitle\">Rating\n";
echo "\t</td>\n";
echo "</tr>\n";

$i = 0;
while ($i < $num && $result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
    $tid = $row["TeamID"] ?? '';
    $Team = $row["Team"] ?? '';
    $ranking = $row["ranking"] ?? '';
    $wins = $row["win"] ?? '';
    $losses = $row["loss"] ?? '';
    $homeWins = $row["home_win"] ?? '';
    $homeLosses = $row["home_loss"] ?? '';
    $awayWins = $row["road_win"] ?? '';
    $awayLosses = $row["road_loss"] ?? '';

    $i++;

    if (($i % 2) == 0) {
        $bgcolor = "FFFFFF";
    } else {
        $bgcolor = "DDDDDD";
    }

    echo "<tr bgcolor=$bgcolor>";
    echo "\t<td align=right>";
    echo "\t\t<font class=\"option\">$i.</td>";
    echo "\t<td align=center>";
    echo "\t\t<a href=\"modules.php?name=Team&op=team&teamID=$tid\"><img src=\"images/logo/$tid.jpg\"></a>";
    echo "\t</td>";
    echo "\t<td align=center>";
    echo "\t\t<font class=\"option\">$wins-$losses";
    echo "\t</td>";
    echo "\t<td align=center>";
    echo "\t\t$homeWins-$homeLosses";
    echo "\t</td>";
    echo "\t<td align=center>";
    echo "\t\t$awayWins-$awayLosses";
    echo "\t</td>";
    echo "\t<td align=center>";
    echo "\t\t<font class=\"option\">$ranking";
    echo "\t</td>";
    echo "</tr>";
}

CloseTable();

Nuke\Footer::footer();
