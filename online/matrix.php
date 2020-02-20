<?php

require '../config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$query="SELECT * FROM nuke_ibl_team_info WHERE teamid != 35 ORDER BY teamid ASC";
$result=mysql_query($query);
$num=mysql_numrows($result);

echo "<HTML><HEAD><TITLE>Draft Pick Matrix</TITLE></HEAD>
    <BODY>
        <CENTER>
            <H2>Dude, Where's My Pick?</H2>
            Use this locator to see exactly who has your draft pick.
            <TABLE BORDER=1>
                <TR>
                    <TD ROWSPAN=2><CENTER>Team City <br>and Name</CENTER></TD>
                    <TD COLSPAN=2><CENTER>This Season</CENTER></TD>
                    <TD COLSPAN=2><CENTER>Next Season</CENTER></TD>
                    <TD COLSPAN=2><CENTER>Two Seasons Out</CENTER></TD>
                    <TD COLSPAN=2><CENTER>Three Seasons Out</CENTER></TD>
                </TR>
                <TR>
                    <TD bgcolor=#FFFFFF><CENTER>Rd 1</CENTER></TD>
                    <TD><CENTER>Rd 2</CENTER></TD>
                    <TD bgcolor=#FFFFFF><CENTER>Rd 1</CENTER></TD>
                    <TD><CENTER>Rd 2</CENTER></TD>
                    <TD bgcolor=#FFFFFF><CENTER>Rd 1</CENTER></TD>
                    <TD><CENTER>Rd 2</CENTER></TD>
                    <TD bgcolor=#FFFFFF><CENTER>Rd 1</CENTER></TD>
                    <TD><CENTER>Rd 2</CENTER></TD>
                </TR>
";

$i=0;

while ($i < $num) {
    $teamid=mysql_result($result,$i,"teamid");
    $team_city=mysql_result($result,$i,"team_city");
    $team_name=mysql_result($result,$i,"team_name");
    $color1=mysql_result($result,$i,"color1");
    $color2=mysql_result($result,$i,"color2");

    $j=0;
    $k=0;

    $query2="SELECT * FROM ibl_draft_picks WHERE teampick = '$team_name' ORDER BY year, round ASC";
    $result2=mysql_query($query2);
    $num2=mysql_numrows($result2);

    echo "<TR><TD bgcolor=#$color1><CENTER><a href=\"../modules.php?name=Team&op=team&tid=$teamid\"><font color=#$color2>$team_city $team_name</font></a></CENTER></TD>";

    while ($j < $num2) {

        $ownerofpick=mysql_result($result2,$j,"ownerofpick");
        $year=mysql_result($result2,$j,"year");
        $round=mysql_result($result2,$j,"round");

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

mysql_close();

?>
