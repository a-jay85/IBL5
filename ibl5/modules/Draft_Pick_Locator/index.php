<?php

$season = new Season($db);

$result = League::getAllTeamsResult($db);
$num = $db->sql_numrows($result);

echo "<HTML><HEAD><TITLE>Draft Pick Matrix</TITLE></HEAD>
    <BODY>
        <CENTER>
            <H2>Dude, Where's My Pick?</H2>
            Use this locator to see exactly who has your draft pick.
            <TABLE BORDER=1>
                <TR>
                    <TD ROWSPAN=2><CENTER>Team</CENTER></TD>
                    <TD COLSPAN=2><CENTER>$season->endingYear</CENTER></TD>
                    <TD COLSPAN=2><CENTER>" . ($season->endingYear + 1) . "</CENTER></TD>
                    <TD COLSPAN=2><CENTER>" . ($season->endingYear + 2) . "</CENTER></TD>
                    <TD COLSPAN=2><CENTER>" . ($season->endingYear + 3) . "</CENTER></TD>
                    <TD COLSPAN=2><CENTER>" . ($season->endingYear + 4) . "</CENTER></TD>
                    <TD COLSPAN=2><CENTER>" . ($season->endingYear + 5) . "</CENTER></TD>
                </TR>
                <TR>
                    <TD><CENTER>Round 1</CENTER></TD>
                    <TD><CENTER>Round 2</CENTER></TD>
                    <TD><CENTER>Round 1</CENTER></TD>
                    <TD><CENTER>Round 2</CENTER></TD>
                    <TD><CENTER>Round 1</CENTER></TD>
                    <TD><CENTER>Round 2</CENTER></TD>
                    <TD><CENTER>Round 1</CENTER></TD>
                    <TD><CENTER>Round 2</CENTER></TD>
                    <TD><CENTER>Round 1</CENTER></TD>
                    <TD><CENTER>Round 2</CENTER></TD>
                    <TD><CENTER>Round 1</CENTER></TD>
                    <TD><CENTER>Round 2</CENTER></TD>
                </TR>
";

$i = 0;

while ($i < $num) {
    $teamID = (int) $db->sql_result($result, $i, "teamid"); // Ensure teamID is an integer
    $team_city = $db->sql_result($result, $i, "team_city");
    $team_name = $db->sql_result($result, $i, "team_name");
    $color1 = $db->sql_result($result, $i, "color1");
    $color2 = $db->sql_result($result, $i, "color2");

    $j = 0;
    $k = 0;

    $query2 = "SELECT * FROM ibl_draft_picks WHERE teampick = '$team_name' ORDER BY year, round ASC";
    $result2 = $db->sql_query($query2);
    $num2 = $db->sql_numrows($result2);

    echo "<TR><TD bgcolor=#$color1><CENTER><a href=\"../modules.php?name=Team&op=team&teamID=$teamID\"><font color=#$color2>$team_city $team_name</font></a></CENTER></TD>";

    while ($j < $num2) {

        $ownerofpick = $db->sql_result($result2, $j, "ownerofpick");
        $year = $db->sql_result($result2, $j, "year");
        $round = $db->sql_result($result2, $j, "round");

        if ($ownerofpick != $team_name) {
            echo "<TD bgcolor=#FF0000><center>$ownerofpick</center></TD>";
        } else {
            echo "<TD bgcolor=#cccccc><center>$ownerofpick</center></TD>";
        }

        $j++;
    }

    echo "</TR>
    ";
    $i++;
}

echo "</TABLE></CENTER></HTML>";

$db->sql_close();
