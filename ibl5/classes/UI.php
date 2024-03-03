<?php

class UI
{
    public static function decoratePlayerName($name, $tid, $ordinal, $currentContractYear, $totalYearsOnContract)
    {
        if ($tid == 0) {
            $playerNameDecorated = "$name";
        } elseif ($ordinal >= 960) { // on waivers
            $playerNameDecorated = "($name)*";
        } elseif ($currentContractYear == $totalYearsOnContract) { // eligible for Free Agency at the end of this season
            $playerNameDecorated = "$name^";
        } else {
            $playerNameDecorated = "$name";
        }
        return $playerNameDecorated;
    }

    public static function playerMenu()
    {
        echo "<center><b>
            <a href=\"modules.php?name=Player_Search\">Player Search</a>  |
            <a href=\"modules.php?name=Player_Awards\">Awards Search</a> |
            <a href=\"modules.php?name=One-on-One\">One-on-One Game</a> |
            <a href=\"modules.php?name=Leaderboards\">Career Leaderboards</a> (All Types)
        </b><center>
        <hr>";
    }

    public static function contracts($db, $result, $team, $isFreeAgencyModuleActive, $currentSeasonEndingYear)
    {
        if ($isFreeAgencyModuleActive == 1) {
            $currentSeasonEndingYear++;
        }
        
        $table_contracts = "<table align=\"center\" class=\"sortable\">
            <thead>
                <tr bgcolor=$team->color1>
                    <th><font color=$team->color2>Pos</font></th>
                    <th colspan=2><font color=$team->color2>Player</font></th>
                    <th><font color=$team->color2>Exp</font></th>
                    <th><font color=$team->color2>Bird</font></th>
                    <td bgcolor=$team->color1 width=0></td>
                    <th><font color=$team->color2>" . ($currentSeasonEndingYear + -1) . "-<br>" . ($currentSeasonEndingYear + 0) . "</font></th>
                    <th><font color=$team->color2>" . ($currentSeasonEndingYear + 0) . "-<br>" . ($currentSeasonEndingYear + 1) . "</font></th>
                    <th><font color=$team->color2>" . ($currentSeasonEndingYear + 1) . "-<br>" . ($currentSeasonEndingYear + 2) . "</font></th>
                    <th><font color=$team->color2>" . ($currentSeasonEndingYear + 2) . "-<br>" . ($currentSeasonEndingYear + 3) . "</font></th>
                    <th><font color=$team->color2>" . ($currentSeasonEndingYear + 3) . "-<br>" . ($currentSeasonEndingYear + 4) . "</font></th>
                    <th><font color=$team->color2>" . ($currentSeasonEndingYear + 4) . "-<br>" . ($currentSeasonEndingYear + 5) . "</font></th>
                    <td bgcolor=$team->color1 width=0></td>
                    <th><font color=$team->color2>Tal</font></th>
                    <th><font color=$team->color2>Skl</font></th>
                    <th><font color=$team->color2>Int</font></th>
                    <td bgcolor=$team->color1 width=0></td>
                    <th><font color=$team->color2>Loy</font></th>
                    <th><font color=$team->color2>PFW</font></th>
                    <th><font color=$team->color2>PT</font></th>
                    <th><font color=$team->color2>Sec</font></th>
                    <th><font color=$team->color2>Trad</font></th>
                </tr>
            </thead>
        <tbody>";
    
        $cap1 = 0;
        $cap2 = 0;
        $cap3 = 0;
        $cap4 = 0;
        $cap5 = 0;
        $cap6 = 0;
    
        $i = 0;
        foreach ($result as $plrRow) {
            $player = Player::withPlrRow($db, $plrRow);
    
            $playerNameDecorated = UI::decoratePlayerName($player->name, $team->teamID, $player->ordinal, $player->contractCurrentYear, $player->contractTotalYears);
    
            if ($isFreeAgencyModuleActive == 0) {
                $year1 = $player->contractCurrentYear;
                $year2 = $player->contractCurrentYear + 1;
                $year3 = $player->contractCurrentYear + 2;
                $year4 = $player->contractCurrentYear + 3;
                $year5 = $player->contractCurrentYear + 4;
                $year6 = $player->contractCurrentYear + 5;
            } else {
                $year1 = $player->contractCurrentYear + 1;
                $year2 = $player->contractCurrentYear + 2;
                $year3 = $player->contractCurrentYear + 3;
                $year4 = $player->contractCurrentYear + 4;
                $year5 = $player->contractCurrentYear + 5;
                $year6 = $player->contractCurrentYear + 6;
            }
            if ($player->contractCurrentYear == 0) {
                $year1 < 7 ? $con1 = $player->contractYear1Salary : $con1 = 0;
                $year2 < 7 ? $con2 = $player->contractYear2Salary : $con2 = 0;
                $year3 < 7 ? $con3 = $player->contractYear3Salary : $con3 = 0;
                $year4 < 7 ? $con4 = $player->contractYear4Salary : $con4 = 0;
                $year5 < 7 ? $con5 = $player->contractYear5Salary : $con5 = 0;
                $year6 < 7 ? $con6 = $player->contractYear6Salary : $con6 = 0;
            } else {
                $year1 < 7 ? $con1 = $player->{'contractYear' . $year1 . 'Salary'} : $con1 = 0;
                $year2 < 7 ? $con2 = $player->{'contractYear' . $year2 . 'Salary'} : $con2 = 0;
                $year3 < 7 ? $con3 = $player->{'contractYear' . $year3 . 'Salary'} : $con3 = 0;
                $year4 < 7 ? $con4 = $player->{'contractYear' . $year4 . 'Salary'} : $con4 = 0;
                $year5 < 7 ? $con5 = $player->{'contractYear' . $year5 . 'Salary'} : $con5 = 0;
                $year6 < 7 ? $con6 = $player->{'contractYear' . $year6 . 'Salary'} : $con6 = 0;
            }
    
            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
    
            $table_contracts .= "
                <tr bgcolor=$bgcolor>
                <td align=center>$player->position</td>
                <td colspan=2><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$playerNameDecorated</a></td>
                <td align=center>$player->yearsOfExperience</td>
                <td align=center>$player->birdYears</td>
                <td bgcolor=$team->color1></td>
                <td>$con1</td>
                <td>$con2</td>
                <td>$con3</td>
                <td>$con4</td>
                <td>$con5</td>
                <td>$con6</td>
                <td bgcolor=$team->color1></td>
                <td align=center>$player->ratingTalent</td>
                <td align=center>$player->ratingSkill</td>
                <td align=center>$player->ratingIntangibles</td>
                <td bgcolor=$team->color1></td>
                <td align=center>$player->freeAgencyLoyalty</td>
                <td align=center>$player->freeAgencyPlayForWinner</td>
                <td align=center>$player->freeAgencyPlayingTime</td>
                <td align=center>$player->freeAgencySecurity</td>
                <td align=center>$player->freeAgencyTradition</td>
            </tr>";
    
            $cap1 += $con1;
            $cap2 += $con2;
            $cap3 += $con3;
            $cap4 += $con4;
            $cap5 += $con5;
            $cap6 += $con6;
            $i++;
        }
    
        $table_contracts .= "</tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td colspan=2><b>Cap Totals</td>
                    <td></td>
                    <td></td>
                    <td bgcolor=$team->color1></td>
                    <td><b>$cap1</td>
                    <td><b>$cap2</td>
                    <td><b>$cap3</td>
                    <td><b>$cap4</td>
                    <td><b>$cap5</td>
                    <td><b>$cap6</td>
                    <td bgcolor=$team->color1></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan=19><i>Note:</i> Players whose names appear in parenthesis and with a trailing asterisk are waived players that still count against the salary cap.</td>
                </tr>
            </tfoot>
        </table>";
    
        return $table_contracts;
    }

    public static function per36Minutes($db, $result, $color1, $color2, $tid, $yr)
    {
        $table_per36Minutes = "<table align=\"center\" class=\"sortable\">
            <thead>
                <tr bgcolor=$color1>
                    <th><font color=$color2>Pos</font></th>
                    <th colspan=3><font color=$color2>Player</font></th>
                    <th><font color=$color2>g</font></th>
                    <th><font color=$color2>gs</font></th>
                    <th><font color=$color2>mpg</font></th>
                    <th><font color=$color2>36min</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>fgm</font></th>
                    <th><font color=$color2>fga</font></th>
                    <th><font color=$color2>fgp</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
                    <th><font color=$color2>ftm</font></th>
                    <th><font color=$color2>fta</font></th>
                    <th><font color=$color2>ftp</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
                    <th><font color=$color2>3gm</font></th>
                    <th><font color=$color2>3ga</font></th>
                    <th><font color=$color2>3gp</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>orb</font></th>
                    <th><font color=$color2>reb</font></th>
                    <th><font color=$color2>ast</font></th>
                    <th><font color=$color2>stl</font></th>
                    <th><font color=$color2>to</font></th>
                    <th><font color=$color2>blk</font></th>
                    <th><font color=$color2>pf</font></th>
                    <th><font color=$color2>pts</font></th>
                </tr>
            </thead>
        <tbody>";
    
        /* =======================AVERAGES */
    
        $i = 0;
        $num = $db->sql_numrows($result);
        while ($i < $num) {
            $name = $db->sql_result($result, $i, "name");
            $pos = $db->sql_result($result, $i, "pos");
            $p_ord = $db->sql_result($result, $i, "ordinal");
            $pid = $db->sql_result($result, $i, "pid");
            $cy = $db->sql_result($result, $i, "cy");
            $cyt = $db->sql_result($result, $i, "cyt");
    
            $firstCharacterOfPlayerName = substr($name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
            if ($firstCharacterOfPlayerName !== '|') {
                $playerNameDecorated = UI::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);
    
                if ($yr == "") {
                    $stats_gm = $db->sql_result($result, $i, "stats_gm");
                    $stats_gs = $db->sql_result($result, $i, "stats_gs");
                    $stats_min = $db->sql_result($result, $i, "stats_min");
                    $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
                    $stats_fga = $db->sql_result($result, $i, "stats_fga");
                    $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
                    $stats_fta = $db->sql_result($result, $i, "stats_fta");
                    $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
                    $stats_tga = $db->sql_result($result, $i, "stats_3ga");
                    $stats_orb = $db->sql_result($result, $i, "stats_orb");
                    $stats_drb = $db->sql_result($result, $i, "stats_drb");
                    $stats_ast = $db->sql_result($result, $i, "stats_ast");
                    $stats_stl = $db->sql_result($result, $i, "stats_stl");
                    $stats_to = $db->sql_result($result, $i, "stats_to");
                    $stats_blk = $db->sql_result($result, $i, "stats_blk");
                    $stats_pf = $db->sql_result($result, $i, "stats_pf");
                    $stats_reb = $stats_orb + $stats_drb;
                    $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
                } else {
                    $stats_gm = $db->sql_result($result, $i, "gm");
                    $stats_min = $db->sql_result($result, $i, "min");
                    $stats_fgm = $db->sql_result($result, $i, "fgm");
                    $stats_fga = $db->sql_result($result, $i, "fga");
                    $stats_ftm = $db->sql_result($result, $i, "ftm");
                    $stats_fta = $db->sql_result($result, $i, "fta");
                    $stats_tgm = $db->sql_result($result, $i, "3gm");
                    $stats_tga = $db->sql_result($result, $i, "3ga");
                    $stats_orb = $db->sql_result($result, $i, "orb");
                    $stats_ast = $db->sql_result($result, $i, "ast");
                    $stats_stl = $db->sql_result($result, $i, "stl");
                    $stats_to = $db->sql_result($result, $i, "tvr");
                    $stats_blk = $db->sql_result($result, $i, "blk");
                    $stats_pf = $db->sql_result($result, $i, "pf");
                    $stats_reb = $db->sql_result($result, $i, "reb");
                    $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
                }
                @$stats_fgm = number_format((36 / $stats_min * $stats_fgm), 1);
                @$stats_fga = number_format((36 / $stats_min * $stats_fga), 1);
                @$stats_fgp = number_format(($stats_fgm / $stats_fga), 3);
                @$stats_ftm = number_format((36 / $stats_min * $stats_ftm), 1);
                @$stats_fta = number_format((36 / $stats_min * $stats_fta), 1);
                @$stats_ftp = number_format(($stats_ftm / $stats_fta), 3);
                @$stats_tgm = number_format((36 / $stats_min * $stats_tgm), 1);
                @$stats_tga = number_format((36 / $stats_min * $stats_tga), 1);
                @$stats_tgp = number_format(($stats_tgm / $stats_tga), 3);
                @$stats_mpg = number_format(($stats_min / $stats_gm), 1);
                @$stats_per36Min = number_format((36 / $stats_min * $stats_min), 1);
                @$stats_opg = number_format((36 / $stats_min * $stats_orb), 1);
                @$stats_rpg = number_format((36 / $stats_min * $stats_reb), 1);
                @$stats_apg = number_format((36 / $stats_min * $stats_ast), 1);
                @$stats_spg = number_format((36 / $stats_min * $stats_stl), 1);
                @$stats_tpg = number_format((36 / $stats_min * $stats_to), 1);
                @$stats_bpg = number_format((36 / $stats_min * $stats_blk), 1);
                @$stats_fpg = number_format((36 / $stats_min * $stats_pf), 1);
                @$stats_ppg = number_format((36 / $stats_min * $stats_pts), 1);
        
                (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
        
                $table_per36Minutes .= "<tr bgcolor=$bgcolor>
                    <td>$pos</td>
                    <td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
                    <td><center>$stats_gm</center></td>
                    <td><center>$stats_gs</center></td>
                    <td><center>$stats_mpg</center></td>
                    <td><center>$stats_per36Min</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$stats_fgm</center></td>
                    <td><center>$stats_fga</center></td>
                    <td><center>$stats_fgp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$stats_ftm</center></td>
                    <td><center>$stats_fta</center></td>
                    <td><center>$stats_ftp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$stats_tgm</center></td>
                    <td><center>$stats_tga</center></td>
                    <td><center>$stats_tgp</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$stats_opg</center></td>
                    <td><center>$stats_rpg</center></td>
                    <td><center>$stats_apg</center></td>
                    <td><center>$stats_spg</center></td>
                    <td><center>$stats_tpg</center></td>
                    <td><center>$stats_bpg</center></td>
                    <td><center>$stats_fpg</center></td>
                    <td><center>$stats_ppg</center></td>
                </tr>";
            }
    
            $i++;
        }
    
        $table_per36Minutes .= "</tbody>
            </table>";
    
        return $table_per36Minutes;
    }
    
    public static function ratings($db, $result, $color1, $color2, $tid, $yr)
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

            $firstCharacterOfPlayerName = substr($name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
            if ($firstCharacterOfPlayerName !== '|') {
                $playerNameDecorated = UI::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);
    
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
            }
    
            $i++;
        }
    
        $table_ratings .= "</tbody></table>";
    
        return $table_ratings;
    }

    public static function seasonAverages($db, $result, $color1, $color2, $tid, $yr, $team_name)
    {
        $table_averages = "<table align=\"center\" class=\"sortable\">
                <thead>
                    <tr bgcolor=$color1>
                        <th><font color=$color2>Pos</font></th>
                        <th colspan=3><font color=$color2>Player</font></th>
                        <th><font color=$color2>g</font></th>
                        <th><font color=$color2>gs</font></th>
                        <th><font color=$color2>min</font></th>
                        <td bgcolor=$color1 width=0></td>
                        <th><font color=$color2>fgm</font></th>
                        <th><font color=$color2>fga</font></th>
                        <th><font color=$color2>fgp</font></th>
                        <td bgcolor=#CCCCCC width=0></td>
                        <th><font color=$color2>ftm</font></th>
                        <th><font color=$color2>fta</font></th>
                        <th><font color=$color2>ftp</font></th>
                        <td bgcolor=#CCCCCC width=0></td>
                        <th><font color=$color2>3gm</font></th>
                        <th><font color=$color2>3ga</font></th>
                        <th><font color=$color2>3gp</font></th>
                        <td bgcolor=$color1 width=0></td>
                        <th><font color=$color2>orb</font></th>
                        <th><font color=$color2>reb</font></th>
                        <th><font color=$color2>ast</font></th>
                        <th><font color=$color2>stl</font></th>
                        <th><font color=$color2>to</font></th>
                        <th><font color=$color2>blk</font></th>
                        <th><font color=$color2>pf</font></th>
                        <th><font color=$color2>pts</font></th>
                    </tr>
                </thead>
            <tbody>";
    
        /* =======================AVERAGES */
    
        $i = 0;
        $num = $db->sql_numrows($result);
        while ($i < $num) {
            $name = $db->sql_result($result, $i, "name");
            $pos = $db->sql_result($result, $i, "pos");
            $p_ord = $db->sql_result($result, $i, "ordinal");
            $pid = $db->sql_result($result, $i, "pid");
            $cy = $db->sql_result($result, $i, "cy");
            $cyt = $db->sql_result($result, $i, "cyt");
    
            $firstCharacterOfPlayerName = substr($name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
            if ($firstCharacterOfPlayerName !== '|') {
                $playerNameDecorated = UI::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);
    
                if ($yr == "") {
                    $stats_gm = $db->sql_result($result, $i, "stats_gm");
                    $stats_gs = $db->sql_result($result, $i, "stats_gs");
                    $stats_min = $db->sql_result($result, $i, "stats_min");
                    $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
                    $stats_fga = $db->sql_result($result, $i, "stats_fga");
                    $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
                    $stats_fta = $db->sql_result($result, $i, "stats_fta");
                    $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
                    $stats_tga = $db->sql_result($result, $i, "stats_3ga");
                    $stats_orb = $db->sql_result($result, $i, "stats_orb");
                    $stats_drb = $db->sql_result($result, $i, "stats_drb");
                    $stats_ast = $db->sql_result($result, $i, "stats_ast");
                    $stats_stl = $db->sql_result($result, $i, "stats_stl");
                    $stats_to = $db->sql_result($result, $i, "stats_to");
                    $stats_blk = $db->sql_result($result, $i, "stats_blk");
                    $stats_pf = $db->sql_result($result, $i, "stats_pf");
                    $stats_reb = $stats_orb + $stats_drb;
                    $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
                } else {
                    $stats_gm = $db->sql_result($result, $i, "gm");
                    $stats_min = $db->sql_result($result, $i, "min");
                    $stats_fgm = $db->sql_result($result, $i, "fgm");
                    $stats_fga = $db->sql_result($result, $i, "fga");
                    $stats_ftm = $db->sql_result($result, $i, "ftm");
                    $stats_fta = $db->sql_result($result, $i, "fta");
                    $stats_tgm = $db->sql_result($result, $i, "3gm");
                    $stats_tga = $db->sql_result($result, $i, "3ga");
                    $stats_orb = $db->sql_result($result, $i, "orb");
                    $stats_ast = $db->sql_result($result, $i, "ast");
                    $stats_stl = $db->sql_result($result, $i, "stl");
                    $stats_to = $db->sql_result($result, $i, "tvr");
                    $stats_blk = $db->sql_result($result, $i, "blk");
                    $stats_pf = $db->sql_result($result, $i, "pf");
                    $stats_reb = $db->sql_result($result, $i, "reb");
                    $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
                }
                @$stats_fgm = number_format(($stats_fgm / $stats_gm), 1);
                @$stats_fga = number_format(($stats_fga / $stats_gm), 1);
                @$stats_fgp = number_format(($stats_fgm / $stats_fga), 3);
                @$stats_ftm = number_format(($stats_ftm / $stats_gm), 1);
                @$stats_fta = number_format(($stats_fta / $stats_gm), 1);
                @$stats_ftp = number_format(($stats_ftm / $stats_fta), 3);
                @$stats_tgm = number_format(($stats_tgm / $stats_gm), 1);
                @$stats_tga = number_format(($stats_tga / $stats_gm), 1);
                @$stats_tgp = number_format(($stats_tgm / $stats_tga), 3);
                @$stats_mpg = number_format(($stats_min / $stats_gm), 1);
                @$stats_opg = number_format(($stats_orb / $stats_gm), 1);
                @$stats_rpg = number_format(($stats_reb / $stats_gm), 1);
                @$stats_apg = number_format(($stats_ast / $stats_gm), 1);
                @$stats_spg = number_format(($stats_stl / $stats_gm), 1);
                @$stats_tpg = number_format(($stats_to / $stats_gm), 1);
                @$stats_bpg = number_format(($stats_blk / $stats_gm), 1);
                @$stats_fpg = number_format(($stats_pf / $stats_gm), 1);
                @$stats_ppg = number_format(($stats_pts / $stats_gm), 1);
        
                (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
        
                $table_averages .= "<tr bgcolor=$bgcolor>
                    <td>$pos</td>
                    <td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
                    <td><center>$stats_gm</center></td>
                    <td><center>$stats_gs</center></td>
                    <td><center>$stats_mpg</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$stats_fgm</center></td>
                    <td><center>$stats_fga</center></td>
                    <td><center>$stats_fgp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$stats_ftm</center></td>
                    <td><center>$stats_fta</center></td>
                    <td><center>$stats_ftp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$stats_tgm</center></td>
                    <td><center>$stats_tga</center></td>
                    <td><center>$stats_tgp</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$stats_opg</center></td>
                    <td><center>$stats_rpg</center></td>
                    <td><center>$stats_apg</center></td>
                    <td><center>$stats_spg</center></td>
                    <td><center>$stats_tpg</center></td>
                    <td><center>$stats_bpg</center></td>
                    <td><center>$stats_fpg</center></td>
                    <td><center>$stats_ppg</center></td>
                </tr>";
            }
    
            $i++;
        }
    
        // ========= TEAM AVERAGES DISPLAY
    
        $table_averages = $table_averages . "</tbody><tfoot>";
    
        $queryTeamOffenseTotals = "SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '1989'";
        $resultTeamOffenseTotals = $db->sql_query($queryTeamOffenseTotals);
        $numTeamOffenseTotals = $db->sql_numrows($resultTeamOffenseTotals);
    
        $t = 0;
    
        while ($t < $numTeamOffenseTotals) {
            $team_off_games = $db->sql_result($resultTeamOffenseTotals, $t, "games");
            $team_off_minutes = $db->sql_result($resultTeamOffenseTotals, $t, "minutes");
            $team_off_fgm = $db->sql_result($resultTeamOffenseTotals, $t, "fgm");
            $team_off_fga = $db->sql_result($resultTeamOffenseTotals, $t, "fga");
            @$team_off_fgp = number_format(($team_off_fgm / $team_off_fga), 3);
            $team_off_ftm = $db->sql_result($resultTeamOffenseTotals, $t, "ftm");
            $team_off_fta = $db->sql_result($resultTeamOffenseTotals, $t, "fta");
            @$team_off_ftp = number_format(($team_off_ftm / $team_off_fta), 3);
            $team_off_tgm = $db->sql_result($resultTeamOffenseTotals, $t, "tgm");
            $team_off_tga = $db->sql_result($resultTeamOffenseTotals, $t, "tga");
            @$team_off_tgp = number_format(($team_off_tgm / $team_off_tga), 3);
            $team_off_orb = $db->sql_result($resultTeamOffenseTotals, $t, "orb");
            $team_off_reb = $db->sql_result($resultTeamOffenseTotals, $t, "reb");
            $team_off_ast = $db->sql_result($resultTeamOffenseTotals, $t, "ast");
            $team_off_stl = $db->sql_result($resultTeamOffenseTotals, $t, "stl");
            $team_off_tvr = $db->sql_result($resultTeamOffenseTotals, $t, "tvr");
            $team_off_blk = $db->sql_result($resultTeamOffenseTotals, $t, "blk");
            $team_off_pf = $db->sql_result($resultTeamOffenseTotals, $t, "pf");
            $team_off_pts = $team_off_fgm + $team_off_fgm + $team_off_ftm + $team_off_tgm;
    
            @$team_off_avgfgm = number_format(($team_off_fgm / $team_off_games), 1);
            @$team_off_avgfga = number_format(($team_off_fga / $team_off_games), 1);
            @$team_off_avgftm = number_format(($team_off_ftm / $team_off_games), 1);
            @$team_off_avgfta = number_format(($team_off_fta / $team_off_games), 1);
            @$team_off_avgtgm = number_format(($team_off_tgm / $team_off_games), 1);
            @$team_off_avgtga = number_format(($team_off_tga / $team_off_games), 1);
            @$team_off_avgmin = number_format(($team_off_minutes / $team_off_games), 1);
            @$team_off_avgorb = number_format(($team_off_orb / $team_off_games), 1);
            @$team_off_avgreb = number_format(($team_off_reb / $team_off_games), 1);
            @$team_off_avgast = number_format(($team_off_ast / $team_off_games), 1);
            @$team_off_avgstl = number_format(($team_off_stl / $team_off_games), 1);
            @$team_off_avgtvr = number_format(($team_off_tvr / $team_off_games), 1);
            @$team_off_avgblk = number_format(($team_off_blk / $team_off_games), 1);
            @$team_off_avgpf = number_format(($team_off_pf / $team_off_games), 1);
            @$team_off_avgpts = number_format(($team_off_pts / $team_off_games), 1);
    
            if ($yr == "") {
                $table_averages .= "<tr>
                    <td colspan=4><b>$team_name Offense</td>
                    <td><b><center>$team_off_games</center></td>
                    <td><b><center>$team_off_games</center></td>
                    <td><center><b>$team_off_avgmin</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_off_avgfgm</center></td>
                    <td><center><b>$team_off_avgfga</center></td>
                    <td><center><b>$team_off_fgp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_off_avgftm</center></td>
                    <td><center><b>$team_off_avgfta</center></td>
                    <td><center><b>$team_off_ftp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_off_avgtgm</center></td>
                    <td><center><b>$team_off_avgtga</center></td>
                    <td><center><b>$team_off_tgp</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_off_avgorb</center></td>
                    <td><center><b>$team_off_avgreb</center></td>
                    <td><center><b>$team_off_avgast</center></td>
                    <td><center><b>$team_off_avgstl</center></td>
                    <td><center><b>$team_off_avgtvr</center></td>
                    <td><center><b>$team_off_avgblk</center></td>
                    <td><center><b>$team_off_avgpf</center></td>
                    <td><center><b>$team_off_avgpts</center></td>
                </tr>";
            }
            $t++;
        }
    
        $queryTeamDefenseTotals = "SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '1989'";
        $resultTeamDefenseTotals = $db->sql_query($queryTeamDefenseTotals);
        $numTeamDefenseTotals = $db->sql_numrows($resultTeamDefenseTotals);
    
        $t = 0;
    
        while ($t < $numTeamDefenseTotals) {
            $team_def_games = $db->sql_result($resultTeamDefenseTotals, $t, "games");
            $team_def_minutes = $db->sql_result($resultTeamDefenseTotals, $t, "minutes");
            $team_def_fgm = $db->sql_result($resultTeamDefenseTotals, $t, "fgm");
            $team_def_fga = $db->sql_result($resultTeamDefenseTotals, $t, "fga");
            @$team_def_fgp = number_format(($team_def_fgm / $team_def_fga), 3);
            $team_def_ftm = $db->sql_result($resultTeamDefenseTotals, $t, "ftm");
            $team_def_fta = $db->sql_result($resultTeamDefenseTotals, $t, "fta");
            @$team_def_ftp = number_format(($team_def_ftm / $team_def_fta), 3);
            $team_def_tgm = $db->sql_result($resultTeamDefenseTotals, $t, "tgm");
            $team_def_tga = $db->sql_result($resultTeamDefenseTotals, $t, "tga");
            @$team_def_tgp = number_format(($team_def_tgm / $team_def_tga), 3);
            $team_def_orb = $db->sql_result($resultTeamDefenseTotals, $t, "orb");
            $team_def_reb = $db->sql_result($resultTeamDefenseTotals, $t, "reb");
            $team_def_ast = $db->sql_result($resultTeamDefenseTotals, $t, "ast");
            $team_def_stl = $db->sql_result($resultTeamDefenseTotals, $t, "stl");
            $team_def_tvr = $db->sql_result($resultTeamDefenseTotals, $t, "tvr");
            $team_def_blk = $db->sql_result($resultTeamDefenseTotals, $t, "blk");
            $team_def_pf = $db->sql_result($resultTeamDefenseTotals, $t, "pf");
            $team_def_pts = $team_def_fgm + $team_def_fgm + $team_def_ftm + $team_def_tgm;
    
            @$team_def_avgfgm = number_format(($team_def_fgm / $team_def_games), 1);
            @$team_def_avgfga = number_format(($team_def_fga / $team_def_games), 1);
            @$team_def_avgftm = number_format(($team_def_ftm / $team_def_games), 1);
            @$team_def_avgfta = number_format(($team_def_fta / $team_def_games), 1);
            @$team_def_avgtgm = number_format(($team_def_tgm / $team_def_games), 1);
            @$team_def_avgtga = number_format(($team_def_tga / $team_def_games), 1);
            @$team_def_avgmin = number_format(($team_def_minutes / $team_def_games), 1);
            @$team_def_avgorb = number_format(($team_def_orb / $team_def_games), 1);
            @$team_def_avgreb = number_format(($team_def_reb / $team_def_games), 1);
            @$team_def_avgast = number_format(($team_def_ast / $team_def_games), 1);
            @$team_def_avgstl = number_format(($team_def_stl / $team_def_games), 1);
            @$team_def_avgtvr = number_format(($team_def_tvr / $team_def_games), 1);
            @$team_def_avgblk = number_format(($team_def_blk / $team_def_games), 1);
            @$team_def_avgpf = number_format(($team_def_pf / $team_def_games), 1);
            @$team_def_avgpts = number_format(($team_def_pts / $team_def_games), 1);
    
            if ($yr == "") {
                $table_averages .= "<tr>
                    <td colspan=4><b>$team_name Defense</td>
                    <td><center><b>$team_def_games</center></td>
                    <td><b>$team_def_games</td>
                    <td><center><b>$team_def_avgmin</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_def_avgfgm</center></td>
                    <td><center><b>$team_def_avgfga</center></td>
                    <td><center><b>$team_def_fgp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_def_avgftm</center></td>
                    <td><center><b>$team_def_avgfta</center></td>
                    <td><center><b>$team_def_ftp</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_def_avgtgm</center></td>
                    <td><center><b>$team_def_avgtga</center></td>
                    <td><center><b>$team_def_tgp</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_def_avgorb</center></td>
                    <td><center><b>$team_def_avgreb</center></td>
                    <td><center><b>$team_def_avgast</center></td>
                    <td><center><b>$team_def_avgstl</center></td>
                    <td><center><b>$team_def_avgtvr</center></td>
                    <td><center><b>$team_def_avgblk</center></td>
                    <td><center><b>$team_def_avgpf</center></td>
                    <td><center><b>$team_def_avgpts</center></td>
                </tr>";
            }
            $t++;
        }
    
        $table_averages .= "</tfoot>
            </table>";
    
        return $table_averages;
    }

    public static function seasonTotals($db, $result, $color1, $color2, $tid, $yr, $team_name)
    {
        $table_totals = "<table align=\"center\" class=\"sortable\">
            <thead>
                <tr bgcolor=$color1>
                    <th><font color=$color2>Pos</font></th>
                    <th colspan=3><font color=$color2>Player</font></th>
                    <th><font color=$color2>g</font></th>
                    <th><font color=$color2>gs</font></th>
                    <th><font color=$color2>min</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>fgm</font></th>
                    <th><font color=$color2>fga</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
                    <th><font color=$color2>ftm</font></th>
                    <th><font color=$color2>fta</font></th>
                    <td bgcolor=#CCCCCC width=0></td>
                    <th><font color=$color2>3gm</font></th>
                    <th><font color=$color2>3ga</font></th>
                    <td bgcolor=$color1 width=0></td>
                    <th><font color=$color2>orb</font></th>
                    <th><font color=$color2>reb</font></th>
                    <th><font color=$color2>ast</font></th>
                    <th><font color=$color2>stl</font></th>
                    <th><font color=$color2>to</font></th>
                    <th><font color=$color2>blk</font></th>
                    <th><font color=$color2>pf</font></th>
                    <th><font color=$color2>pts</font></th>
                </tr>
            </thead>
        <tbody>";

        $i = 0;
        $num = $db->sql_numrows($result);
        while ($i < $num) {
            $name = $db->sql_result($result, $i, "name");
            $pos = $db->sql_result($result, $i, "pos");
            $p_ord = $db->sql_result($result, $i, "ordinal");
            $pid = $db->sql_result($result, $i, "pid");
            $cy = $db->sql_result($result, $i, "cy");
            $cyt = $db->sql_result($result, $i, "cyt");

            $firstCharacterOfPlayerName = substr($name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
            if ($firstCharacterOfPlayerName !== '|') {
                $playerNameDecorated = UI::decoratePlayerName($name, $tid, $p_ord, $cy, $cyt);

                if ($yr == "") {
                    $stats_gm = $db->sql_result($result, $i, "stats_gm");
                    $stats_gs = $db->sql_result($result, $i, "stats_gs");
                    $stats_min = $db->sql_result($result, $i, "stats_min");
                    $stats_fgm = $db->sql_result($result, $i, "stats_fgm");
                    $stats_fga = $db->sql_result($result, $i, "stats_fga");
                    $stats_ftm = $db->sql_result($result, $i, "stats_ftm");
                    $stats_fta = $db->sql_result($result, $i, "stats_fta");
                    $stats_tgm = $db->sql_result($result, $i, "stats_3gm");
                    $stats_tga = $db->sql_result($result, $i, "stats_3ga");
                    $stats_orb = $db->sql_result($result, $i, "stats_orb");
                    $stats_drb = $db->sql_result($result, $i, "stats_drb");
                    $stats_ast = $db->sql_result($result, $i, "stats_ast");
                    $stats_stl = $db->sql_result($result, $i, "stats_stl");
                    $stats_to = $db->sql_result($result, $i, "stats_to");
                    $stats_blk = $db->sql_result($result, $i, "stats_blk");
                    $stats_pf = $db->sql_result($result, $i, "stats_pf");
                    $stats_reb = $stats_orb + $stats_drb;
                    $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
                } else {
                    $stats_gm = $db->sql_result($result, $i, "gm");
                    $stats_min = $db->sql_result($result, $i, "min");
                    $stats_fgm = $db->sql_result($result, $i, "fgm");
                    $stats_fga = $db->sql_result($result, $i, "fga");
                    $stats_ftm = $db->sql_result($result, $i, "ftm");
                    $stats_fta = $db->sql_result($result, $i, "fta");
                    $stats_tgm = $db->sql_result($result, $i, "3gm");
                    $stats_tga = $db->sql_result($result, $i, "3ga");
                    $stats_orb = $db->sql_result($result, $i, "orb");
                    $stats_ast = $db->sql_result($result, $i, "ast");
                    $stats_stl = $db->sql_result($result, $i, "stl");
                    $stats_to = $db->sql_result($result, $i, "tvr");
                    $stats_blk = $db->sql_result($result, $i, "blk");
                    $stats_pf = $db->sql_result($result, $i, "pf");
                    $stats_reb = $db->sql_result($result, $i, "reb");
                    $stats_pts = 2 * $stats_fgm + $stats_ftm + $stats_tgm;
                }
        
                (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
        
                $table_totals .= "<tr bgcolor=$bgcolor>
                    <td>$pos</td>
                    <td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$playerNameDecorated</a></td>
                    <td><center>$stats_gm</center></td>
                    <td><center>$stats_gs</center></td>
                    <td><center>$stats_min</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$stats_fgm</center></td>
                    <td><center>$stats_fga</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$stats_ftm</center></td>
                    <td><center>$stats_fta</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$stats_tgm</center></td>
                    <td><center>$stats_tga</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$stats_orb</center></td>
                    <td><center>$stats_reb</center></td>
                    <td><center>$stats_ast</center></td>
                    <td><center>$stats_stl</center></td>
                    <td><center>$stats_to</center></td>
                    <td><center>$stats_blk</center></td>
                    <td><center>$stats_pf</center></td>
                    <td><center>$stats_pts</center></td>
                </tr>";    
            }

            $i++;
        }

        $table_totals .= "</tbody>
            <tfoot>";

        // ==== INSERT TEAM OFFENSE AND DEFENSE TOTALS ====

        $queryTeamOffenseTotals = "SELECT * FROM ibl_team_offense_stats WHERE team = '$team_name' AND year = '1989'";
        $resultTeamOffenseTotals = $db->sql_query($queryTeamOffenseTotals);
        $numTeamOffenseTotals = $db->sql_numrows($resultTeamOffenseTotals);

        $t = 0;

        while ($t < $numTeamOffenseTotals) {
            $team_off_games = $db->sql_result($resultTeamOffenseTotals, $t, "games");
            $team_off_minutes = $db->sql_result($resultTeamOffenseTotals, $t, "minutes");
            $team_off_fgm = $db->sql_result($resultTeamOffenseTotals, $t, "fgm");
            $team_off_fga = $db->sql_result($resultTeamOffenseTotals, $t, "fga");
            $team_off_ftm = $db->sql_result($resultTeamOffenseTotals, $t, "ftm");
            $team_off_fta = $db->sql_result($resultTeamOffenseTotals, $t, "fta");
            $team_off_tgm = $db->sql_result($resultTeamOffenseTotals, $t, "tgm");
            $team_off_tga = $db->sql_result($resultTeamOffenseTotals, $t, "tga");
            $team_off_orb = $db->sql_result($resultTeamOffenseTotals, $t, "orb");
            $team_off_reb = $db->sql_result($resultTeamOffenseTotals, $t, "reb");
            $team_off_ast = $db->sql_result($resultTeamOffenseTotals, $t, "ast");
            $team_off_stl = $db->sql_result($resultTeamOffenseTotals, $t, "stl");
            $team_off_tvr = $db->sql_result($resultTeamOffenseTotals, $t, "tvr");
            $team_off_blk = $db->sql_result($resultTeamOffenseTotals, $t, "blk");
            $team_off_pf = $db->sql_result($resultTeamOffenseTotals, $t, "pf");
            $team_off_pts = $team_off_fgm + $team_off_fgm + $team_off_ftm + $team_off_tgm;

            if ($yr == "") {
                $table_totals .= "<tr>
                    <td colspan=4><b>$team_name Offense</td>
                    <td><center><b>$team_off_games</center></td>
                    <td><center><b>$team_off_games</center></td>
                    <td><center><b>$team_off_minutes</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_off_fgm</center></td>
                    <td><center><b>$team_off_fga</b></center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_off_ftm</center></td>
                    <td><center><b>$team_off_fta</b></center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_off_tgm</center></td>
                    <td><center><b>$team_off_tga</b></center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_off_orb</center></td>
                    <td><center><b>$team_off_reb</center></td>
                    <td><center><b>$team_off_ast</center></td>
                    <td><center><b>$team_off_stl</center></td>
                    <td><center><b>$team_off_tvr</center></td>
                    <td><center><b>$team_off_blk</center></td>
                    <td><center><b>$team_off_pf</center></td>
                    <td><center><b>$team_off_pts</center></td>
                </tr>";
            }
            $t++;
        }

        $queryTeamDefenseTotals = "SELECT * FROM ibl_team_defense_stats WHERE team = '$team_name' AND year = '1989'";
        $resultTeamDefenseTotals = $db->sql_query($queryTeamDefenseTotals);
        $numTeamDefenseTotals = $db->sql_numrows($resultTeamDefenseTotals);

        $t = 0;

        while ($t < $numTeamDefenseTotals) {
            $team_def_games = $db->sql_result($resultTeamDefenseTotals, $t, "games");
            $team_def_minutes = $db->sql_result($resultTeamDefenseTotals, $t, "minutes");
            $team_def_fgm = $db->sql_result($resultTeamDefenseTotals, $t, "fgm");
            $team_def_fga = $db->sql_result($resultTeamDefenseTotals, $t, "fga");
            $team_def_ftm = $db->sql_result($resultTeamDefenseTotals, $t, "ftm");
            $team_def_fta = $db->sql_result($resultTeamDefenseTotals, $t, "fta");
            $team_def_tgm = $db->sql_result($resultTeamDefenseTotals, $t, "tgm");
            $team_def_tga = $db->sql_result($resultTeamDefenseTotals, $t, "tga");
            $team_def_orb = $db->sql_result($resultTeamDefenseTotals, $t, "orb");
            $team_def_reb = $db->sql_result($resultTeamDefenseTotals, $t, "reb");
            $team_def_ast = $db->sql_result($resultTeamDefenseTotals, $t, "ast");
            $team_def_stl = $db->sql_result($resultTeamDefenseTotals, $t, "stl");
            $team_def_tvr = $db->sql_result($resultTeamDefenseTotals, $t, "tvr");
            $team_def_blk = $db->sql_result($resultTeamDefenseTotals, $t, "blk");
            $team_def_pf = $db->sql_result($resultTeamDefenseTotals, $t, "pf");
            $team_def_pts = $team_def_fgm + $team_def_fgm + $team_def_ftm + $team_def_tgm;

            if ($yr == "") {
                $table_totals .= "<tr>
                    <td colspan=4><b>$team_name Defense</td>
                    <td><center><b>$team_def_games</center></td>
                    <td><center><b>$team_def_games</center></td>
                    <td><center><b>$team_def_minutes</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_def_fgm</center></td>
                    <td><center><b>$team_def_fga</b></center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_def_ftm</center></td>
                    <td><center><b>$team_def_fta</b></center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center><b>$team_def_tgm</b></center></td>
                    <td><center><b>$team_def_tga</b></center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center><b>$team_def_orb</center></td>
                    <td><center><b>$team_def_reb</center></td>
                    <td><center><b>$team_def_ast</center></td>
                    <td><center><b>$team_def_stl</center></td>
                    <td><center><b>$team_def_tvr</center></td>
                    <td><center><b>$team_def_blk</center></td>
                    <td><center><b>$team_def_pf</center></td>
                    <td><center><b>$team_def_pts</center></td>
                </tr>";
            }

            $t++;
        }

        $table_totals .= "</tfoot>
            </table>";

        return $table_totals;
    }

    public static function simAverages($db, $sharedFunctions, $color1, $color2, $tid)
    {
        $table_simAverages = "<table align=\"center\" class=\"sortable\"><thead><tr bgcolor=$color1>
            <th><font color=$color2>Pos</font></th>
            <th colspan=3><font color=$color2>Player</font></th>
            <th><font color=$color2>g</font></th>
            <th><font color=$color2>min</font></th>
            <td bgcolor=$color1 width=0></td>
            <th><font color=$color2>fgm</font></th>
            <th><font color=$color2>fga</font></th>
            <th><font color=$color2>fgp</font></th>
            <td bgcolor=#CCCCCC width=0></td>
            <th><font color=$color2>ftm</font></th>
            <th><font color=$color2>fta</font></th>
            <th><font color=$color2>ftp</font></th>
            <td bgcolor=#CCCCCC width=0></td>
            <th><font color=$color2>3gm</font></th>
            <th><font color=$color2>3ga</font></th>
            <th><font color=$color2>3gp</font></th>
            <td bgcolor=$color1 width=0></td>
            <th><font color=$color2>orb</font></th>
            <th><font color=$color2>reb</font></th>
            <th><font color=$color2>ast</font></th>
            <th><font color=$color2>stl</font></th>
            <th><font color=$color2>to</font></th>
            <th><font color=$color2>blk</font></th>
            <th><font color=$color2>pf</font></th>
            <th><font color=$color2>pts</font></th>
        </tr></thead><tbody>";
    
        $arrayLastSimDates = $sharedFunctions->getLastSimDatesArray();
    
        $simStartDate = $arrayLastSimDates['Start Date'];
        $simEndDate = $arrayLastSimDates['End Date'];
    
        $playersOnTeam = $db->sql_query("SELECT pid
            FROM ibl_plr
            WHERE tid = $tid
            ORDER BY name ASC");
        $numberOfPlayersOnTeam = $db->sql_numrows($playersOnTeam);
    
        $i = 0;
        while ($i < $numberOfPlayersOnTeam) {
            $pid = $db->sql_result($playersOnTeam, $i);
    
            // TODO: refactor this so that I'm not cutting and pasting the Player module's Sim Stats code
            $resultPlayerSimBoxScores = $db->sql_query("SELECT *
                FROM ibl_box_scores
                WHERE pid = $pid
                AND Date BETWEEN '$simStartDate' AND '$simEndDate'
                AND gameMIN > 0
                ORDER BY Date ASC");
    
            $numberOfGamesPlayedInSim = $db->sql_numrows($resultPlayerSimBoxScores);
            $simTotalMIN = 0;
            $simTotal2GM = 0;
            $simTotal2GA = 0;
            $simTotalFTM = 0;
            $simTotalFTA = 0;
            $simTotal3GM = 0;
            $simTotal3GA = 0;
            $simTotalORB = 0;
            $simTotalDRB = 0;
            $simTotalAST = 0;
            $simTotalSTL = 0;
            $simTotalTOV = 0;
            $simTotalBLK = 0;
            $simTotalPF = 0;
            $simTotalPTS = 0;
    
            if ($numberOfGamesPlayedInSim > 0) {
                while ($row = $db->sql_fetch_assoc($resultPlayerSimBoxScores)) {
                    $name = $row['name'];
                    $pos = $row['pos'];
    
                    $simTotalMIN += $row['gameMIN'];
                    $simTotal2GM += $row['game2GM'];
                    $simTotal2GA += $row['game2GA'];
                    $simTotalFTM += $row['gameFTM'];
                    $simTotalFTA += $row['gameFTA'];
                    $simTotal3GM += $row['game3GM'];
                    $simTotal3GA += $row['game3GA'];
                    $simTotalORB += $row['gameORB'];
                    $simTotalDRB += $row['gameDRB'];
                    $simTotalAST += $row['gameAST'];
                    $simTotalSTL += $row['gameSTL'];
                    $simTotalTOV += $row['gameTOV'];
                    $simTotalBLK += $row['gameBLK'];
                    $simTotalPF += $row['gamePF'];
                    $simTotalPTS += (2 * $row['game2GM']) + $row['gameFTM'] + (3 * $row['game3GM']);
                }
    
                @$simAverageMIN = number_format(($simTotalMIN / $numberOfGamesPlayedInSim), 1);
                @$simAverageFTM = number_format(($simTotalFTM / $numberOfGamesPlayedInSim), 1);
                @$simAverageFTA = number_format(($simTotalFTA / $numberOfGamesPlayedInSim), 1);
                @$simAverageFTP = number_format(($simTotalFTM / $simTotalFTA), 3);
                @$simAverage3GM = number_format(($simTotal3GM / $numberOfGamesPlayedInSim), 1);
                @$simAverage3GA = number_format(($simTotal3GA / $numberOfGamesPlayedInSim), 1);
                @$simAverage3GP = number_format(($simTotal3GM / $simTotal3GA), 3);
                @$simAverageFGM = number_format((($simTotal2GM + $simTotal3GM) / $numberOfGamesPlayedInSim), 1);
                @$simAverageFGA = number_format((($simTotal2GA + $simTotal3GA) / $numberOfGamesPlayedInSim), 1);
                @$simAverageFGP = number_format((($simTotal2GM + $simTotal3GM) / ($simTotal2GA + $simTotal3GA)), 3);
                @$simAverageORB = number_format(($simTotalORB / $numberOfGamesPlayedInSim), 1);
                @$simAverageREB = number_format((($simTotalORB + $simTotalDRB) / $numberOfGamesPlayedInSim), 1);
                @$simAverageAST = number_format(($simTotalAST / $numberOfGamesPlayedInSim), 1);
                @$simAverageSTL = number_format(($simTotalSTL / $numberOfGamesPlayedInSim), 1);
                @$simAverageTOV = number_format(($simTotalTOV / $numberOfGamesPlayedInSim), 1);
                @$simAverageBLK = number_format(($simTotalBLK / $numberOfGamesPlayedInSim), 1);
                @$simAveragePF = number_format(($simTotalPF / $numberOfGamesPlayedInSim), 1);
                @$simAveragePTS = number_format(($simTotalPTS / $numberOfGamesPlayedInSim), 1);
    
                (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
    
                $table_simAverages .= "<tr bgcolor=$bgcolor>
                    <td>$pos</td>
                    <td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
                    <td><center>$numberOfGamesPlayedInSim</center></td>
                    <td><center>$simAverageMIN</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$simAverageFGM</center></td>
                    <td><center>$simAverageFGA</center></td>
                    <td><center>$simAverageFGP</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$simAverageFTM</center></td>
                    <td><center>$simAverageFTA</center></td>
                    <td><center>$simAverageFTP</center></td>
                    <td bgcolor=#CCCCCC width=0></td>
                    <td><center>$simAverage3GM</center></td>
                    <td><center>$simAverage3GA</center></td>
                    <td><center>$simAverage3GP</center></td>
                    <td bgcolor=$color1 width=0></td>
                    <td><center>$simAverageORB</center></td>
                    <td><center>$simAverageREB</center></td>
                    <td><center>$simAverageAST</center></td>
                    <td><center>$simAverageSTL</center></td>
                    <td><center>$simAverageTOV</center></td>
                    <td><center>$simAverageBLK</center></td>
                    <td><center>$simAveragePF</center></td>
                    <td><center>$simAveragePTS</center></td>
                </tr>";
            }
    
            $i++;
        }
    
        $table_simAverages .= "</tbody>
            </table>";
    
        return $table_simAverages;
    }
}