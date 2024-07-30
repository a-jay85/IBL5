<?php

global $db;

$query = "SELECT * FROM ibl_power WHERE Team = '$team->name'";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);
$win = $db->sql_result($result, 0, "win");
$loss = $db->sql_result($result, 0, "loss");
$gb = $db->sql_result($result, 0, "gb");
$division = $db->sql_result($result, 0, "Division");
$conference = $db->sql_result($result, 0, "Conference");
$home_win = $db->sql_result($result, 0, "home_win");
$home_loss = $db->sql_result($result, 0, "home_loss");
$road_win = $db->sql_result($result, 0, "road_win");
$road_loss = $db->sql_result($result, 0, "road_loss");
$last_win = $db->sql_result($result, 0, "last_win");
$last_loss = $db->sql_result($result, 0, "last_loss");

$query2 = "SELECT * FROM ibl_power WHERE Division = '$division' ORDER BY gb DESC";
$result2 = $db->sql_query($query2);
$num = $db->sql_numrows($result2);
$i = 0;
$gbbase = $db->sql_result($result2, $i, "gb");
$gb = $gbbase - $gb;
while ($i < $num) {
    $Team2 = $db->sql_result($result2, $i, "Team");
    if ($Team2 == $team->name) {
        $Div_Pos = $i + 1;
    }
    $i++;
}

$query3 = "SELECT * FROM ibl_power WHERE Conference = '$conference' ORDER BY gb DESC";
$result3 = $db->sql_query($query3);
$num = $db->sql_numrows($result3);
$i = 0;
while ($i < $num) {
    $Team3 = $db->sql_result($result3, $i, "Team");
    if ($Team3 == $team->name) {
        $Conf_Pos = $i + 1;
    }
    $i++;
}

$output .= "<tr bgcolor=\"#$team->color1\">
    <td align=\"center\">
        <font color=\"#$team->color2\"><b>Current Season</b></font>
    </td>
</tr>
<tr>
    <td>
        <table>
            <tr>
                <td align='right'><b>Team:</td>
                <td>$team->name</td>
            </tr>
            <tr>
                <td align='right'><b>f.k.a.:</td>
                <td>$team->formerlyKnownAs</td>
            </tr>
            <tr>
                <td align='right'><b>Record:</td>
                <td>$win-$loss</td>
            </tr>
            <tr>
                <td align='right'><b>Arena:</td>
                <td>$team->arena</td>
            </tr>
            <tr>
                <td align='right'><b>Conference:</td>
                <td>$conference</td>
            </tr>
            <tr>
                <td align='right'><b>Conf Position:</td>
                <td>$Conf_Pos</td>
            </tr>
            <tr>
                <td align='right'><b>Division:</td>
                <td>$division</td>
            </tr>
            <tr>
                <td align='right'><b>Div Position:</td>
                <td>$Div_Pos</td>
            </tr>
            <tr>
                <td align='right'><b>GB:</td>
                <td>$gb</td>
            </tr>
            <tr>
                <td align='right'><b>Home Record:</td>
                <td>$home_win-$home_loss</td>
            </tr>
            <tr>
                <td align='right'><b>Road Record:</td>
                <td>$road_win-$road_loss</td>
            </tr>
            <tr>
                <td align='right'><b>Last 10:</td>
                <td>$last_win-$last_loss</td>
            </tr>
        </table>
    </td>
</tr>";