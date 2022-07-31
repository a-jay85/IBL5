<?php

class Shared
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getLastSimDatesArray()
    {
        $queryLastSimDates = $this->db->sql_query("SELECT *
            FROM ibl_sim_dates
            ORDER BY sim DESC
            LIMIT 1");

        return $this->db->sql_fetch_assoc($queryLastSimDates);
    }

    public function getCurrentSeasonEndingYear()
    {
        $queryCurrentSeasonEndingYear = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Current Season Ending Year'
            LIMIT 1");

        return $this->db->sql_result($queryCurrentSeasonEndingYear, 0);
    }

    public function getCurrentSeasonPhase()
    {
        $queryCurrentSeasonPhase = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Current Season Phase'
            LIMIT 1");

        return $this->db->sql_result($queryCurrentSeasonPhase, 0);
    }

    public function getNumberOfTitles($teamname, $titleName)
    {
        $queryNumberOfTitles = $this->db->sql_query("SELECT COUNT(name)
        	FROM ibl_team_awards
        	WHERE name = '$teamname'
        	AND Award LIKE '%$titleName%';");

        return $this->db->sql_result($queryNumberOfTitles, 0);
    }

    public function getTeamnameFromTid($tid)
    {
        $queryTeamnameFromTid = $this->db->sql_query("SELECT team_name
            FROM ibl_team_info
            WHERE teamid = $tid
            LIMIT 1;");

        return $this->db->sql_result($queryTeamnameFromTid, 0);
    }

    public function getTidFromTeamname($teamname)
    {
        $queryTidFromTeamname = $this->db->sql_query("SELECT teamid
            FROM ibl_team_info
            WHERE team_name = '$teamname'
            LIMIT 1;");

        return $this->db->sql_result($queryTidFromTeamname, 0);
    }

    public function getWaiverWireStatus()
    {
        $queryWaiverWireStatus = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Allow Waiver Moves'
            LIMIT 1");

        return $this->db->sql_result($queryWaiverWireStatus, 0);
    }

    public function getAllowTradesStatus()
    {
        $queryAllowTradesStatus = $this->db->sql_query("SELECT value
            FROM ibl_settings
            WHERE name = 'Allow Trades'
            LIMIT 1");

        return $this->db->sql_result($queryAllowTradesStatus, 0);
    }

    public static function decoratePlayerName($name, $tid, $ordinal, $currentContractYear, $totalYearsOnContract)
    {
        if ($tid == 0) {
            $playerNameDecorated = "$name";
        } elseif ($$ordinal >= 960) { // on waivers
            $playerNameDecorated = "($name)*";
        } elseif ($currentContractYear == $totalYearsOnContract) { // eligible for Free Agency at the end of this season
            $playerNameDecorated = "$name^";
        } else {
            $playerNameDecorated = "$name";
        }
        return $playerNameDecorated;
    }

    public function ratings($db, $result, $color1, $color2, $tid, $yr)
    {
        $table_ratings = "<table align=\"center\" class=\"sortable\">
            <colgroup span=2><colgroup span=2><colgroup span=6><colgroup span=6><colgroup span=4><colgroup span=4><colgroup span=1>
            <thead bgcolor=$color1>
                <tr bgcolor=$color1>
                    <th><font color=$color2>Pos</font></th>
                    <th><font color=$color2>Player</font></th>
                    <th><font color=$color2>Age</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>2ga</font></th>
                    <th><font color=$color2>2g%</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
                    <th><font color=$color2>fta</font></th>
                    <th><font color=$color2>ft%</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
                    <th><font color=$color2>3ga</font></th>
                    <th><font color=$color2>3g%</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>orb</font></th>
                    <th><font color=$color2>drb</font></th>
                    <th><font color=$color2>ast</font></th>
                    <th><font color=$color2>stl</font></th>
                    <th><font color=$color2>tvr</font></th>
                    <th><font color=$color2>blk</font></th>
                    <th><font color=$color2>foul</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>oo</font></th>
                    <th><font color=$color2>do</font></th>
                    <th><font color=$color2>po</font></th>
                    <th><font color=$color2>to</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
                    <th><font color=$color2>od</font></th>
                    <th><font color=$color2>dd</font></th>
                    <th><font color=$color2>pd</font></th>
                    <th><font color=$color2>td</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>Clu</font></th>
                    <th><font color=$color2>Con</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>Inj</font></th>
                </tr>
            </thead>
        <tbody>";
    
        $i = 0;
        $num = $db->sql_numrows($result);
        while ($i < $num) {
            if ($yr == "") {
                $name = $db->sql_result($result, $i, "name");
                $pid = $db->sql_result($result, $i, "pid");
                $pos = $db->sql_result($result, $i, "pos");
                $p_ord = $db->sql_result($result, $i, "ordinal");
                $age = $db->sql_result($result, $i, "age");
                $inj = $db->sql_result($result, $i, "injured");
    
                $r_2ga = $db->sql_result($result, $i, "r_fga");
                $r_2gp = $db->sql_result($result, $i, "r_fgp");
                $r_fta = $db->sql_result($result, $i, "r_fta");
                $r_ftp = $db->sql_result($result, $i, "r_ftp");
                $r_3ga = $db->sql_result($result, $i, "r_tga");
                $r_3gp = $db->sql_result($result, $i, "r_tgp");
                $r_orb = $db->sql_result($result, $i, "r_orb");
                $r_drb = $db->sql_result($result, $i, "r_drb");
                $r_ast = $db->sql_result($result, $i, "r_ast");
                $r_stl = $db->sql_result($result, $i, "r_stl");
                $r_blk = $db->sql_result($result, $i, "r_blk");
                $r_tvr = $db->sql_result($result, $i, "r_to");
                $r_foul = $db->sql_result($result, $i, "r_foul");
                $r_oo = $db->sql_result($result, $i, "oo");
                $r_do = $db->sql_result($result, $i, "do");
                $r_po = $db->sql_result($result, $i, "po");
                $r_to = $db->sql_result($result, $i, "to");
                $r_od = $db->sql_result($result, $i, "od");
                $r_dd = $db->sql_result($result, $i, "dd");
                $r_pd = $db->sql_result($result, $i, "pd");
                $r_td = $db->sql_result($result, $i, "td");
                $clutch = $db->sql_result($result, $i, "Clutch");
                $consistency = $db->sql_result($result, $i, "Consistency");
    
                $cy = $db->sql_result($result, $i, "cy");
                $cyt = $db->sql_result($result, $i, "cyt");
            } else {
                $name = $db->sql_result($result, $i, "name");
                $pid = $db->sql_result($result, $i, "pid");
    
                $r_2ga = $db->sql_result($result, $i, "r_2ga");
                $r_2gp = $db->sql_result($result, $i, "r_2gp");
                $r_fta = $db->sql_result($result, $i, "r_fta");
                $r_ftp = $db->sql_result($result, $i, "r_ftp");
                $r_3ga = $db->sql_result($result, $i, "r_3ga");
                $r_3gp = $db->sql_result($result, $i, "r_3gp");
                $r_orb = $db->sql_result($result, $i, "r_orb");
                $r_drb = $db->sql_result($result, $i, "r_drb");
                $r_ast = $db->sql_result($result, $i, "r_ast");
                $r_stl = $db->sql_result($result, $i, "r_stl");
                $r_blk = $db->sql_result($result, $i, "r_blk");
                $r_tvr = $db->sql_result($result, $i, "r_tvr");
                $r_oo = $db->sql_result($result, $i, "r_oo");
                $r_do = $db->sql_result($result, $i, "r_do");
                $r_po = $db->sql_result($result, $i, "r_po");
                $r_to = $db->sql_result($result, $i, "r_to");
                $r_od = $db->sql_result($result, $i, "r_od");
                $r_dd = $db->sql_result($result, $i, "r_dd");
                $r_pd = $db->sql_result($result, $i, "r_pd");
                $r_td = $db->sql_result($result, $i, "r_td");
                $clutch = $db->sql_result($result, $i, "Clutch");
                $consistency = $db->sql_result($result, $i, "Consistency");
            }
    
            $playerNameDecorated = Shared::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);
    
            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
    
            $table_ratings .= "<tr bgcolor=$bgcolor>
                <td align=center>$pos</td>
                <td><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
                <td align=center>$age</td>
                <td bgcolor=$color1></td>
                <td align=center>$r_2ga</td>
                <td align=center>$r_2gp</td>
                <td bgcolor=#CCCCCC width=0></td>
                <td align=center>$r_fta</td>
                <td align=center>$r_ftp</td>
                <td bgcolor=#CCCCCC width=0></td>
                <td align=center>$r_3ga</td>
                <td align=center>$r_3gp</td>
                <td bgcolor=$color1></td>
                <td align=center>$r_orb</td>
                <td align=center>$r_drb</td>
                <td align=center>$r_ast</td>
                <td align=center>$r_stl</td>
                <td align=center>$r_tvr</td>
                <td align=center>$r_blk</td>
                <td align=center>$r_foul</td>
                <td bgcolor=$color1></td>
                <td align=center>$r_oo</td>
                <td align=center>$r_do</td>
                <td align=center>$r_po</td>
                <td align=center>$r_to</td>
                <td bgcolor=#CCCCCC width=0></td>
                <td align=center>$r_od</td>
                <td align=center>$r_dd</td>
                <td align=center>$r_pd</td>
                <td align=center>$r_td</td>
                <td bgcolor=$color1></td>
                <td align=center>$clutch</td>
                <td align=center>$consistency</td>
                <td bgcolor=$color1></td>
                <td align=center>$inj</td>
            </tr>";
    
            $i++;
        }
    
        $table_ratings .= "</tbody></table>";
    
        return $table_ratings;
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
