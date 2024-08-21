<?php

require_once "mainfile.php";
$sharedFunctions = new Shared($db);

$val = $_GET['day'];

$query = "SELECT * FROM `ibl_fa_offers` ORDER BY name ASC, perceivedvalue DESC";
$result = $db->sql_query($query);
$num = $db->sql_numrows($result);

echo "<HTML>
	<HEAD><TITLE>Free Agent Processing</TITLE></HEAD>
	<BODY>
        <H1>You are viewing <font color=red>Day " . ($val + 1) . "</font> results!</H1>
		<TABLE BORDER=1>
			<TR>
				<TD COLSPAN=8>Free Agent Signings</TD>
				<TD>MLE</TD>
				<TD>LLE</TD>
			</TR>";

$i = 0;
while ($i < $num) {
    $name = $db->sql_result($result, $i, "name");
    $team = $db->sql_result($result, $i, "team");
    $tid = $sharedFunctions->getTidFromTeamname($team);
    $perceivedvalue = $db->sql_result($result, $i, "perceivedvalue");

    $offer1 = $db->sql_result($result, $i, "offer1");
    $offer2 = $db->sql_result($result, $i, "offer2");
    $offer3 = $db->sql_result($result, $i, "offer3");
    $offer4 = $db->sql_result($result, $i, "offer4");
    $offer5 = $db->sql_result($result, $i, "offer5");
    $offer6 = $db->sql_result($result, $i, "offer6");

    $MLE = $db->sql_result($result, $i, "MLE");
    $LLE = $db->sql_result($result, $i, "LLE");
    $random = $db->sql_result($result, $i, "random");

    $query2 = "SELECT * FROM `ibl_demands` WHERE name = '$name'";
    $result2 = $db->sql_query($query2);
    $num2 = $db->sql_numrows($result2);

    $dem1 = $db->sql_result($result2, 0, "dem1");
    $dem2 = $db->sql_result($result2, 0, "dem2");
    $dem3 = $db->sql_result($result2, 0, "dem3");
    $dem4 = $db->sql_result($result2, 0, "dem4");
    $dem5 = $db->sql_result($result2, 0, "dem5");
    $dem6 = $db->sql_result($result2, 0, "dem6");

    $offeryears = 6;
    if ($offer6 == 0) {
        $offeryears = 5;
    }
    if ($offer5 == 0) {
        $offeryears = 4;
    }
    if ($offer4 == 0) {
        $offeryears = 3;
    }
    if ($offer3 == 0) {
        $offeryears = 2;
    }
    if ($offer2 == 0) {
        $offeryears = 1;
    }
    $offertotal = ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100;

    $demyrs = 6;
    if ($dem6 == 0) {
        $demyrs = 5;
    }
    if ($dem5 == 0) {
        $demyrs = 4;
    }
    if ($dem4 == 0) {
        $demyrs = 3;
    }
    if ($dem3 == 0) {
        $demyrs = 2;
    }
    if ($dem2 == 0) {
        $demyrs = 1;
    }

    $demands = ($dem1 + $dem2 + $dem3 + $dem4 + $dem5 + $dem6) / $demyrs * ((11 - $val) / 10);
    if ($nameholder == $name) {
    } else {
        if ($perceivedvalue > $demands) {
            echo " <TR>
                <TD>$name</TD>
                <TD>$team</TD>
                <TD>$offer1</TD>
                <TD>$offer2</TD>
                <TD>$offer3</TD>
                <TD>$offer4</TD>
                <TD>$offer5</TD>
                <TD>$offer6</TD>
                <TD>$MLE</TD>
                <TD>$LLE</TD>
            </TR>";
            $text .= $name . " accepts the " . $team . " offer of a " . $offeryears . "-year deal worth a total of " . $offertotal . " million dollars.<br> ";
            $code .= "UPDATE `ibl_plr`
				SET `cy` = '0',
					`cy1` = '" . $offer1 . "',
					`cy2` = '" . $offer2 . "',
					`cy3` = '" . $offer3 . "',
					`cy4` = '" . $offer4 . "',
					`cy5` = '" . $offer5 . "',
					`cy6` = '" . $offer6 . "',
					`teamname` = '" . $team . "',
					`cyt` = '" . $offeryears . "',
					`tid` = $tid
				WHERE `name` = '" . $name . "'
				LIMIT 1;";
            if ($MLE == 1) {
                $code .= "UPDATE `ibl_team_info` SET `HasMLE` = '0' WHERE `team_name` = '" . $team . "' LIMIT 1;";
            }
            if ($LLE == 1) {
                $code .= "UPDATE `ibl_team_info` SET `HasLLE` = '0' WHERE `team_name` = '" . $team . "' LIMIT 1;";
            }
        }
    }

    $nameholder = $name;
    $i = $i + 1;
}

$i = 0;
echo "<TR>
    <TD COLSPAN=8>ALL OFFERS MADE</TD>
    <TD>MLE</TD>
    <TD>LLE</TD>
    <TD>RANDOM</TD>
</TR> ";

while ($i < $num) {
    $name = $db->sql_result($result, $i, "name");
    $perceivedvalue = $db->sql_result($result, $i, "perceivedvalue");
    $team = $db->sql_result($result, $i, "team");

    $offer1 = $db->sql_result($result, $i, "offer1");
    $offer2 = $db->sql_result($result, $i, "offer2");
    $offer3 = $db->sql_result($result, $i, "offer3");
    $offer4 = $db->sql_result($result, $i, "offer4");
    $offer5 = $db->sql_result($result, $i, "offer5");
    $offer6 = $db->sql_result($result, $i, "offer6");

    $MLE = $db->sql_result($result, $i, "MLE");
    $LLE = $db->sql_result($result, $i, "LLE");
    $random = $db->sql_result($result, $i, "random");

    echo "<TR>
        <TD>$name</TD>
        <TD>$team</TD>
        <TD>$offer1</TD>
        <TD>$offer2</TD>
        <TD>$offer3</TD>
        <TD>$offer4</TD>
        <TD>$offer5</TD>
        <TD>$offer6</TD>
        <TD>$MLE</TD>
        <TD>$LLE</TD>
        <TD>$random</TD>
        <TD>$perceivedvalue</TD>
    </TR>";
    $offeryears = 6;
    if ($offer6 == 0) {
        $offeryears = 5;
    }
    if ($offer5 == 0) {
        $offeryears = 4;
    }
    if ($offer4 == 0) {
        $offeryears = 3;
    }
    if ($offer3 == 0) {
        $offeryears = 2;
    }
    if ($offer2 == 0) {
        $offeryears = 1;
    }
    $offertotal = ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100;

    $exttext .= "The " . $team . " offered " . $name . " a " . $offeryears . "-year deal worth a total of " . $offertotal . " million dollars.<br> ";
    $i = $i + 1;
}

echo "</TABLE>
    <hr>
    <h2>SQL QUERY BOX</h2>
    <br>
    <FORM>
        <TEXTAREA COLS=125 ROWS=20>$code</TEXTAREA>
        <hr> 
        <h2>ACCEPTED OFFERS IN HTML FORMAT (FOR NEWS ARTICLE)</h2>
        <br>
        <TEXTAREA COLS=125 ROWS=20>$text</TEXTAREA>
        <hr>
        <h2>ALL OFFERS IN HTML FORMAT (FOR NEWS ARTICLE EXTENDED TEXT)</h2>
        <br>
        <TEXTAREA COLS=125 ROWS=20>$exttext</TEXTAREA>
        <hr>
        <h2>ALL OFFERS IN DISCORD FORMAT (FOR <a href=\"https://discord.com/channels/666986450889474053/682990441641279531\">#live-sims</a>)</h2>
        <br>
        <TEXTAREA COLS=125 ROWS=20>$discordText</TEXTAREA>
    </FORM>
    <hr>
</HTML>";
