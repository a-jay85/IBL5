<?php
class Shared
{
    public static function getLastSimDatesArray()
    {
        $queryLastSimDates = mysql_query("SELECT *
            FROM ibl_sim_dates
            ORDER BY sim DESC
            LIMIT 1");

        return mysql_fetch_assoc($queryLastSimDates);
    }

    public static function getCurrentSeasonEndingYear()
    {
        $queryCurrentSeasonEndingYear = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Current Season Ending Year'
            LIMIT 1");

        return mysql_result($queryCurrentSeasonEndingYear, 0);
    }

    public static function getCurrentSeasonPhase()
    {
        $queryCurrentSeasonPhase = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Current Season Phase'
            LIMIT 1");

        return mysql_result($queryCurrentSeasonPhase, 0);
    }

    public static function getTeamnameFromTid($tid)
    {
    	$queryTeamnameFromTid = mysql_query("SELECT team_name
            FROM nuke_ibl_team_info
            WHERE teamid = $tid
            LIMIT 1;");

        return mysql_result($queryTeamnameFromTid, 0);
    }

    public static function getTidFromTeamname($teamname)
    {
        $queryTidFromTeamname = mysql_query("SELECT teamid
            FROM nuke_ibl_team_info
            WHERE team_name = '$teamname'
            LIMIT 1;");

        return mysql_result($queryTidFromTeamname, 0);
    }

    public static function getWaiverWireStatus()
    {
        $queryWaiverWireStatus = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Allow Waiver Moves'
            LIMIT 1");

        return mysql_result($queryWaiverWireStatus, 0);
    }

    public static function getAllowTradesStatus()
    {
        $queryAllowTradesStatus = mysql_query("SELECT value
            FROM nuke_ibl_settings
            WHERE name = 'Allow Trades'
            LIMIT 1");

        return mysql_result($queryAllowTradesStatus, 0);
    }

    public static function displaytopmenu($tid) {
    	$queryteam="SELECT * FROM nuke_ibl_team_info WHERE teamid = '$tid' ";
    	$resultteam=mysql_query($queryteam);
    	$color1=mysql_result($resultteam,0,"color1");
    	$color2=mysql_result($resultteam,0,"color2");

    	echo "<table width=600 border=0><tr>";

    	$teamCityQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `nuke_ibl_team_info` ORDER BY `team_city` ASC";
    	$teamCityResult = mysql_query($teamCityQuery);
    	$teamNameQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `nuke_ibl_team_info` ORDER BY `team_name` ASC";
    	$teamNameResult = mysql_query($teamNameQuery);
    	$teamIDQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `nuke_ibl_team_info` ORDER BY `teamid` ASC";
    	$teamIDResult = mysql_query($teamIDQuery);

    	echo '<p>';
    	echo '<b> Team Pages: </b>';
    	echo '<select name="teamSelectCity" onchange="location = this.options[this.selectedIndex].value;">';
    	echo '<option value="">Location</option>';
    	while ($row = mysql_fetch_assoc($teamCityResult)) {
    		echo '<option value="./modules.php?name=Team&op=team&tid='.$row["teamid"].'">'.$row["team_city"].'	'.$row["team_name"].'</option>';
    	}
    	echo '</select>';

    	echo '<select name="teamSelectName" onchange="location = this.options[this.selectedIndex].value;">';
    	echo '<option value="">Namesake</option>';
    	while ($row = mysql_fetch_assoc($teamNameResult)) {
    		echo '<option value="./modules.php?name=Team&op=team&tid='.$row["teamid"].'">'.$row["team_name"].'</option>';
    	}
    	echo '</select>';

    	echo '<select name="teamSelectID" onchange="location = this.options[this.selectedIndex].value;">';
    	echo '<option value="">ID#</option>';
    	while ($row = mysql_fetch_assoc($teamIDResult)) {
    		echo '<option value="./modules.php?name=Team&op=team&tid='.$row["teamid"].'">'.$row["teamid"].'	'.$row["team_city"].'	'.$row["team_name"].'</option>';
    	}
    	echo '</select>';

    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=$tid\">Team Page</a></td>";
    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=drafthistory&tid=$tid\">Draft History</a></td>";
    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=schedule&tid=$tid\">Schedule</a></td>";
    	echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Chart_Entry\">Depth Chart Entry</a></td>";
    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Record\">Depth Chart Status</a></td>";
    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Trading&op=reviewtrade\">Trades/Waiver Moves</a></td>";
    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=seteditor\">Offensive Set Editor</a></td>";
    	echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
    	echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=0\">Free Agent List</a></td>";
    	//echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=injuries&tid=$tid\">Injuries</a></td></tr>";
    	echo "</tr></table>";
    	echo "<hr>";
    }
}
