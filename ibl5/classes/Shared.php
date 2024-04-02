<?php

class Shared
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getDiscordIDFromTeamname($teamname)
    {
        $queryDiscordIDFromTeamname = $this->db->sql_query("SELECT discordID
            FROM ibl_team_info
            WHERE team_name = '$teamname'
            LIMIT 1;");

        return $this->db->sql_result($queryDiscordIDFromTeamname, 0);
    }

    public function getDiscordIDFromUsername($username)
    {
        $queryDiscordIDFromUsername = $this->db->sql_query("SELECT discordID
            FROM ibl_team_info
            INNER JOIN nuke_users ON ibl_team_info.team_name = nuke_users.user_ibl_team
            WHERE username = '$username'
            LIMIT 1;");

        return $this->db->sql_result($queryDiscordIDFromUsername, 0);
    }

    public function getNumberOfTitles($teamname, $titleName)
    {
        $queryNumberOfTitles = $this->db->sql_query("SELECT COUNT(name)
        	FROM ibl_team_awards
        	WHERE name = '$teamname'
        	AND Award LIKE '%$titleName%';");

        return $this->db->sql_result($queryNumberOfTitles, 0);
    }

    public function getCurrentOwnerOfDraftPick($draftYear, $draftRound, $teamNameOfDraftPickOrigin)
    {
        $queryCurrentOwnerOfDraftPick = $this->db->sql_query("SELECT ownerofpick
            FROM ibl_draft_picks
            WHERE year = '$draftYear'
            AND round = '$draftRound'
            AND teampick = '$teamNameOfDraftPickOrigin'
            LIMIT 1;");

        return $this->db->sql_result($queryCurrentOwnerOfDraftPick, 0);
    }
    
    public function getPlayerIDFromPlayerName($playerName)
    {
        $queryPlayerIDFromPlayerName = $this->db->sql_query("SELECT pid
            FROM ibl_plr
            WHERE name = '$playerName'
            LIMIT 1;");
    
        return $this->db->sql_result($queryPlayerIDFromPlayerName, 0);
    }

    public function getTeamnameFromTid($tid)
    {
        $queryTeamnameFromTid = $this->db->sql_query("SELECT team_name
            FROM ibl_team_info
            WHERE teamid = $tid
            LIMIT 1;");

        return $this->db->sql_result($queryTeamnameFromTid, 0);
    }

    public function getTeamnameFromUsername($username)
    {
        $queryTeamnameFromUsername = $this->db->sql_query("SELECT user_ibl_team
            FROM nuke_users
            WHERE username = '$username'
            LIMIT 1;");

        return $this->db->sql_result($queryTeamnameFromUsername, 0);
    }

    public function getTidFromTeamname($teamname)
    {
        $queryTidFromTeamname = $this->db->sql_query("SELECT teamid
            FROM ibl_team_info
            WHERE team_name = '$teamname'
            LIMIT 1;");

        return $this->db->sql_result($queryTidFromTeamname, 0);
    }

    public function isFreeAgencyModuleActive()
    {
        $queryIsFreeAgencyModuleActive = $this->db->sql_query("SELECT title, active
            FROM nuke_modules
            WHERE title = 'Free_Agency'
            LIMIT 1");

        return $this->db->sql_result($queryIsFreeAgencyModuleActive, 0, "active");
    }

    public function displaytopmenu($tid)
    {
        $queryteam = "SELECT * FROM ibl_team_info WHERE teamid = '$tid' ";
        $resultteam = $this->db->sql_query($queryteam);
        $color1 = $this->db->sql_result($resultteam, 0, "color1");
        $color2 = $this->db->sql_result($resultteam, 0, "color2");

        echo "<table width=600 border=0><tr>";

        $teamCityQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_city` ASC";
        $teamCityResult = $this->db->sql_query($teamCityQuery);
        $teamNameQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_name` ASC";
        $teamNameResult = $this->db->sql_query($teamNameQuery);
        $teamIDQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `teamid` ASC";
        $teamIDResult = $this->db->sql_query($teamIDQuery);

        echo '<p>';
        echo '<b> Team Pages: </b>';
        echo '<select name="teamSelectCity" onchange="location = this.options[this.selectedIndex].value;">';
        echo '<option value="">Location</option>';
        while ($row = $this->db->sql_fetch_assoc($teamCityResult)) {
            echo '<option value="./modules.php?name=Team&op=team&tid=' . $row["teamid"] . '">' . $row["team_city"] . '	' . $row["team_name"] . '</option>';
        }
        echo '</select>';

        echo '<select name="teamSelectName" onchange="location = this.options[this.selectedIndex].value;">';
        echo '<option value="">Namesake</option>';
        while ($row = $this->db->sql_fetch_assoc($teamNameResult)) {
            echo '<option value="./modules.php?name=Team&op=team&tid=' . $row["teamid"] . '">' . $row["team_name"] . '</option>';
        }
        echo '</select>';

        echo '<select name="teamSelectID" onchange="location = this.options[this.selectedIndex].value;">';
        echo '<option value="">ID#</option>';
        while ($row = $this->db->sql_fetch_assoc($teamIDResult)) {
            echo '<option value="./modules.php?name=Team&op=team&tid=' . $row["teamid"] . '">' . $row["teamid"] . '	' . $row["team_city"] . '	' . $row["team_name"] . '</option>';
        }
        echo '</select>';

        echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=$tid\">Team Page</a></td>";
        echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=drafthistory&tid=$tid\">Draft History</a></td>";
        echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=schedule&tid=$tid\">Schedule</a></td>";
        echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
        echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Chart_Entry\">Depth Chart Entry</a></td>";
        echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Record\">Depth Chart Status</a></td>";
        echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Trading&op=reviewtrade\">Trades/Waiver Moves</a></td>";
        echo "<td nowrap=\"nowrap\" valign=center><font style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </td>";
        echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&tid=0\">Free Agent List</a></td>";
        //echo "<td nowrap=\"nowrap\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=injuries&tid=$tid\">Injuries</a></td></tr>";
        echo "</tr></table>";
        echo "<hr>";
    }
}
