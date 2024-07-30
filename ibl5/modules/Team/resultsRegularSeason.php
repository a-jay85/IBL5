<?php

global $db;

$querywl = "SELECT * FROM ibl_team_win_loss WHERE currentname = '$team->name' ORDER BY year DESC";
$resultwl = $db->sql_query($querywl);
$numwl = $db->sql_numrows($resultwl);

$h = 0;
$wintot = 0;
$lostot = 0;

$output .= "<tr bgcolor=\"#$team->color1\">
    <td align=center>
        <font color=\"#$team->color2\"><b>Regular Season History</b></font>
    </td>
</tr>
<tr>
    <td>
        <div id=\"History-R\" style=\"overflow:auto\">";

while ($h < $numwl) {
    $yearwl = $db->sql_result($resultwl, $h, "year");
    $namewl = $db->sql_result($resultwl, $h, "namethatyear");
    $wins = $db->sql_result($resultwl, $h, "wins");
    $losses = $db->sql_result($resultwl, $h, "losses");
    $wintot += $wins;
    $lostot += $losses;
    @$winpct = number_format($wins / ($wins + $losses), 3);
    $output .= "<a href=\"./modules.php?name=Team&op=team&tid=$team->teamID&yr=$yearwl\">" . ($yearwl - 1) . "-$yearwl $namewl</a>: $wins-$losses ($winpct)<br>";

    $h++;
}

$wlpct = ($wintot + $lostot) ? number_format($wintot / ($wintot + $lostot), 3) : "0.000";

$output .= "</div>
    </td>
</tr>
<tr>
    <td>
        <b>Totals:</b> $wintot-$lostot ($wlpct)
    </td>
</tr>";