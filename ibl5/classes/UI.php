<?php

use Player\Player;
use Services\DatabaseService;
use Statistics\StatsFormatter;

class UI
{
    public static function displayDebugOutput($content, $title = 'Debug Output') 
    {
        static $debugId = 0;
        $debugId++;
        
        echo "<div style='margin: 10px 0; border: 1px solid #ccc; border-radius: 4px;'>
            <div style='padding: 8px; background-color: #f5f5f5; border-bottom: 1px solid #ccc; cursor: pointer;'
                 onclick='toggleDebug$debugId()'>
                <span id='debugIcon$debugId'>▶</span> $title
            </div>
            <pre id='debugContent$debugId' style='display: none; margin: 0; padding: 8px; background-color: #fff; overflow: auto;'>$content</pre>
        </div>
        <script>
            function toggleDebug$debugId() {
                var content = document.getElementById('debugContent$debugId');
                var icon = document.getElementById('debugIcon$debugId');
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    icon.textContent = '▼';
                } else {
                    content.style.display = 'none';
                    icon.textContent = '▶';
                }
            }
        </script>";
    }

    public static function displaytopmenu($db, $teamID = League::FREE_AGENTS_TEAMID)
    {
        $team = Team::initialize($db, $teamID);

        echo "<table style=\"width: 400px; margin: 0 auto; border: 0;\"><tr>";

        $teamCityQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_city` ASC";
        $teamCityResult = $db->sql_query($teamCityQuery);
        $teamNameQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_name` ASC";
        $teamNameResult = $db->sql_query($teamNameQuery);
        $teamIDQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `teamid` ASC";
        $teamIDResult = $db->sql_query($teamIDQuery);

        echo '<p>';
        echo '<b> Team Pages: </b>';
        echo '<select name="teamSelectCity" onchange="location = this.options[this.selectedIndex].value;">';
        echo '<option value="">Location</option>';
        while ($row = $db->sql_fetch_assoc($teamCityResult)) {
            echo '<option value="./modules.php?name=Team&op=team&teamID=' . $row["teamid"] . '">' . $row["team_city"] . '	' . $row["team_name"] . '</option>';
        }
        echo '</select>';

        echo '<select name="teamSelectName" onchange="location = this.options[this.selectedIndex].value;">';
        echo '<option value="">Namesake</option>';
        while ($row = $db->sql_fetch_assoc($teamNameResult)) {
            echo '<option value="./modules.php?name=Team&op=team&teamID=' . $row["teamid"] . '">' . $row["team_name"] . '</option>';
        }
        echo '</select>';

        echo '<select name="teamSelectID" onchange="location = this.options[this.selectedIndex].value;">';
        echo '<option value="">ID#</option>';
        while ($row = $db->sql_fetch_assoc($teamIDResult)) {
            echo '<option value="./modules.php?name=Team&op=team&teamID=' . $row["teamid"] . '">' . $row["teamid"] . '	' . $row["team_city"] . '	' . $row["team_name"] . '</option>';
        }
        echo '</select>';

        echo "<td style=\"white-space: nowrap;\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$team->color2;color: #$team->color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=team&teamID=$teamID\">Team Page</a></td>";
        echo "<td style=\"white-space: nowrap;\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$team->color2;color: #$team->color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team_Schedule&teamID=$teamID\">Team Schedule</a></td>";
        echo "<td style=\"white-space: nowrap;\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$team->color2;color: #$team->color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules/Team/draftHistory.php?teamID=$teamID\">Draft History</a></td>";
        echo "<td style=\"white-space: nowrap; vertical-align: middle;\"><span style=\"font:bold 14px Helvetica;text-decoration: none;\"> | </span></td>";
        echo "<td style=\"white-space: nowrap;\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$team->color2;color: #$team->color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Depth_Chart_Entry\">Depth Chart Entry</a></td>";
        echo "<td style=\"white-space: nowrap;\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$team->color2;color: #$team->color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Trading&op=reviewtrade\">Trades/Waivers</a></td>";
        //echo "<td style=\"white-space: nowrap;\"><a style=\"font:bold 11px Helvetica;text-decoration: none;background-color: #$color2;color: #$color1;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;\" href=\"modules.php?name=Team&op=injuries&teamID=$tid\">Injuries</a></td></tr>";
        echo "</tr></table>";
        echo "<hr>";
    }

    public static function playerMenu()
    {
        echo "<div style=\"text-align: center;\"><b>
            <a href=\"modules.php?name=Player_Search\">Player Search</a>  |
            <a href=\"modules.php?name=Player_Awards\">Awards Search</a> |
            <a href=\"modules.php?name=One-on-One\">One-on-One Game</a> |
            <a href=\"modules.php?name=Leaderboards\">Career Leaderboards</a> (All Types)
        </b></div>
        <hr>";
    }

    // Reusable CSS styles for tables with team-colored separators
    public static function tableStyles(string $tableClass, string $teamColor, string $teamColor2): string
    {
        ob_start();
        ?>
    <style>
    .<?= $tableClass ?> { --team-sep-color: #<?= $teamColor ?>; color: #<?= $teamColor2 ?>; border-collapse: collapse; }
    .<?= $tableClass ?> th { color: #<?= $teamColor2 ?>; }
    .<?= $tableClass ?> td { color: #000; }
    .<?= $tableClass ?> th.sep-team, .<?= $tableClass ?> td.sep-team { border-right: 3px solid var(--team-sep-color); }
    .<?= $tableClass ?> th.sep-weak, .<?= $tableClass ?> td.sep-weak { border-right: 1px solid #CCCCCC; }
    .<?= $tableClass ?> td.text-center { text-align: center; }
    </style>
        <?php
        return ob_get_clean();
    }

    public static function contracts($db, $result, $team, $sharedFunctions)
    {
        $season = new Season($db);

        if ($sharedFunctions->isFreeAgencyModuleActive() == 1) {
            $season->endingYear++;
        }
        
        $table_contracts = self::tableStyles('contracts', $team->color1, $team->color2) . "<table style=\"margin: 0 auto;\" class=\"sortable contracts\">
            <thead>
                <tr style=\"background-color: #$team->color1;\">
                    <th>Pos</th>
                    <th colspan=2>Player</th>
                    <th>Age</th>
                    <th>Exp</th>
                    <th>Bird</th>
                    <th class=\"sep-team\"></th>
                    <th>" . substr(($season->endingYear + -1), -2) . "-" . substr(($season->endingYear + 0), -2) . "</th>
                    <th>" . substr(($season->endingYear + 0), -2) . "-" . substr(($season->endingYear + 1), -2) . "</th>
                    <th>" . substr(($season->endingYear + 1), -2) . "-" . substr(($season->endingYear + 2), -2) . "</th>
                    <th>" . substr(($season->endingYear + 2), -2) . "-" . substr(($season->endingYear + 3), -2) . "</th>
                    <th>" . substr(($season->endingYear + 3), -2) . "-" . substr(($season->endingYear + 4), -2) . "</th>
                    <th class=\"sep-team\">" . substr(($season->endingYear + 4), -2) . "-" . substr(($season->endingYear + 5), -2) . "</th>
                    <th class=\"sep-team\"></th>
                    <th>Tal</th>
                    <th>Skl</th>
                    <th>Int</th>
                    <th class=\"sep-team\"></th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trad</th>
                </tr>
            </thead>
        <tbody>";
    
        $cap1 = $cap2 = $cap3 = $cap4 = $cap5 = $cap6 = 0;
    
        $i = 0;
        foreach ($result as $plrRow) {
            $player = Player::withPlrRow($db, $plrRow);
    
            if ($sharedFunctions->isFreeAgencyModuleActive() == 0) {
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
                <tr style=\"background-color: #$bgcolor;\">
                <td class=\"text-center\">$player->position</td>
                <td colspan=2><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->decoratedName</a></td>
                <td class=\"text-center\">$player->age</td>
                <td class=\"text-center\">$player->yearsOfExperience</td>
                <td class=\"text-center\">$player->birdYears</td>
                <td class=\"sep-team\"></td>
                <td>$con1</td>
                <td>$con2</td>
                <td>$con3</td>
                <td>$con4</td>
                <td>$con5</td>
                <td>$con6</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$player->ratingTalent</td>
                <td class=\"text-center\">$player->ratingSkill</td>
                <td class=\"text-center\">$player->ratingIntangibles</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$player->freeAgencyLoyalty</td>
                <td class=\"text-center\">$player->freeAgencyPlayForWinner</td>
                <td class=\"text-center\">$player->freeAgencyPlayingTime</td>
                <td class=\"text-center\">$player->freeAgencySecurity</td>
                <td class=\"text-center\">$player->freeAgencyTradition</td>
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
                    <td></td>
                    <td class=\"sep-team\"></td>
                    <td><b>$cap1</td>
                    <td><b>$cap2</td>
                    <td><b>$cap3</td>
                    <td><b>$cap4</td>
                    <td><b>$cap5</td>
                    <td><b>$cap6</td>
                    <td class=\"sep-team\"></td>
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

    public static function per36Minutes($db, $result, $team, $yr)
    {
        $table_per36Minutes = self::tableStyles('per36', $team->color1, $team->color2) . "<table style=\"margin: 0 auto;\" class=\"sortable per36\">
            <thead>
                <tr style=\"background-color: #$team->color1;\">
                    <th>Pos</th>
                    <th colspan=3>Player</th>
                    <th>g</th>
                    <th>gs</th>
                    <th>mpg</th>
                    <th>36min</th>
                    <th class=\"sep-team\"></th>
                    <th>fgm</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th class=\"sep-weak\"></th>
                    <th>ftm</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th class=\"sep-weak\"></th>
                    <th>3gm</th>
                    <th>3ga</th>
                    <th>3gp</th>
                    <th class=\"sep-team\"></th>
                    <th>orb</th>
                    <th>reb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>pf</th>
                    <th>pts</th>
                </tr>
            </thead>
        <tbody>";
    
        $i = 0;
        foreach ($result as $plrRow) {
            if ($yr == "") {
                $player = Player::withPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withPlrRow($db, $plrRow);

                $firstCharacterOfPlayerName = substr($player->name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }
    
            $stats_fgm = StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsMade, $playerStats->seasonMinutes);
            $stats_fga = StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsAttempted, $playerStats->seasonMinutes);
            $stats_fgp = StatsFormatter::formatPercentage($playerStats->seasonFieldGoalsMade, $playerStats->seasonFieldGoalsAttempted);
            $stats_ftm = StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsMade, $playerStats->seasonMinutes);
            $stats_fta = StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsAttempted, $playerStats->seasonMinutes);
            $stats_ftp = StatsFormatter::formatPercentage($playerStats->seasonFreeThrowsMade, $playerStats->seasonFreeThrowsAttempted);
            $stats_tgm = StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersMade, $playerStats->seasonMinutes);
            $stats_tga = StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersAttempted, $playerStats->seasonMinutes);
            $stats_tgp = StatsFormatter::formatPercentage($playerStats->seasonThreePointersMade, $playerStats->seasonThreePointersAttempted);
            $stats_mpg = StatsFormatter::formatPerGameAverage($playerStats->seasonMinutes, $playerStats->seasonGamesPlayed);
            $stats_per36Min = StatsFormatter::formatPer36Stat($playerStats->seasonMinutes, $playerStats->seasonMinutes);
            $stats_opg = StatsFormatter::formatPer36Stat($playerStats->seasonOffensiveRebounds, $playerStats->seasonMinutes);
            $stats_rpg = StatsFormatter::formatPer36Stat($playerStats->seasonTotalRebounds, $playerStats->seasonMinutes);
            $stats_apg = StatsFormatter::formatPer36Stat($playerStats->seasonAssists, $playerStats->seasonMinutes);
            $stats_spg = StatsFormatter::formatPer36Stat($playerStats->seasonSteals, $playerStats->seasonMinutes);
            $stats_tpg = StatsFormatter::formatPer36Stat($playerStats->seasonTurnovers, $playerStats->seasonMinutes);
            $stats_bpg = StatsFormatter::formatPer36Stat($playerStats->seasonBlocks, $playerStats->seasonMinutes);
            $stats_fpg = StatsFormatter::formatPer36Stat($playerStats->seasonPersonalFouls, $playerStats->seasonMinutes);
            $stats_ppg = StatsFormatter::formatPer36Stat($playerStats->seasonPoints, $playerStats->seasonMinutes);
        
            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
        
            $table_per36Minutes .= "<tr style=\"background-color: #$bgcolor;\">
                <td>$player->position</td>
                <td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->decoratedName</a></td>
                <td class=\"text-center\">$playerStats->seasonGamesPlayed</td>
                <td class=\"text-center\">$playerStats->seasonGamesStarted</td>
                <td class=\"text-center\">$stats_mpg</td>
                <td class=\"text-center\">$stats_per36Min</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$stats_fgm</td>
                <td class=\"text-center\">$stats_fga</td>
                <td class=\"text-center\">$stats_fgp</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$stats_ftm</td>
                <td class=\"text-center\">$stats_fta</td>
                <td class=\"text-center\">$stats_ftp</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$stats_tgm</td>
                <td class=\"text-center\">$stats_tga</td>
                <td class=\"text-center\">$stats_tgp</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$stats_opg</td>
                <td class=\"text-center\">$stats_rpg</td>
                <td class=\"text-center\">$stats_apg</td>
                <td class=\"text-center\">$stats_spg</td>
                <td class=\"text-center\">$stats_tpg</td>
                <td class=\"text-center\">$stats_bpg</td>
                <td class=\"text-center\">$stats_fpg</td>
                <td class=\"text-center\">$stats_ppg</td>
            </tr>";

            $i++;
        }
    
        $table_per36Minutes .= "</tbody>
            </table>";
    
        return $table_per36Minutes;
    }
    
    public static function ratings($db, $data, $team, $yr, $season, $moduleName = "")
    {
        $table_ratings = self::tableStyles('ratings', $team->color1, $team->color2) . "<table style=\"margin: 0 auto;\" class=\"sortable ratings\">
        <colgroup span=2><colgroup span=2><colgroup span=6><colgroup span=6><colgroup span=4><colgroup span=4><colgroup span=1>
            <thead style=\"background-color: #$team->color1;\">
                <tr style=\"background-color: #$team->color1;\">";


        if ($moduleName == "League_Starters") {
            $table_ratings .= "<th>Team</th>";
        }

        $table_ratings .= "
                    <th>Pos</th>
                    <th>Player</th>
                    <th>Age</th>
                    <th class=\"sep-team\"></th>
                    <th>2ga</th>
                    <th>2g%</th>
                    <th class=\"sep-team\"></th>
                    <th>fta</th>
                    <th>ft%</th>
                    <th class=\"sep-team\"></th>
                    <th>3ga</th>
                    <th>3g%</th>
                    <th class=\"sep-team\"></th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>tvr</th>
                    <th>blk</th>
                    <th>foul</th>
                    <th class=\"sep-team\"></th>
                    <th>oo</th>
                    <th>do</th>
                    <th>po</th>
                    <th>to</th>
                    <th class=\"sep-team\"></th>
                    <th>od</th>
                    <th>dd</th>
                    <th>pd</th>
                    <th>td</th>
                    <th class=\"sep-team\"></th>
                    <th>Clu</th>
                    <th>Con</th>
                    <th class=\"sep-team\"></th>
                    <th>Injury Return Date</th>
                </tr>
            </thead>
        <tbody>";

        $i = 0;
        foreach ($data as $plrRow) {
            if ($yr == "") {
                if (is_object($data)) {
                    $player = Player::withPlrRow($db, $plrRow);
                    (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
                } elseif ($plrRow instanceof Player) {
                    $player = $plrRow;
                    if ($moduleName == "Next_Sim") {
                        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "FFFFAA";
                    } elseif ($moduleName == "League_Starters") {
                        ($player->teamID == $team->teamID) ? $bgcolor = "FFFFAA" : $bgcolor = "FFFFFF";
                    } else {
                        (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
                    }
                } else {
                    continue;
                }

                $firstCharacterOfPlayerName = substr($player->name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
            }
    
            $injuryInfo = $player->getInjuryReturnDate($season->lastSimEndDate);
            if ($injuryInfo != "") {
                $injuryInfo .= " ($player->daysRemainingForInjury days)";
            }

            if (($i % 2) == 0 AND $moduleName == "Next_Sim") {
                $table_ratings .= "<tr>
                <td colspan=55 style=\"background-color: #$team->color1;\">
                </td>
                </tr>";
            }

            $table_ratings .= "<tr style=\"background-color: #$bgcolor;\">";

            if ($moduleName == "League_Starters") {
                $table_ratings .= "<td>$player->teamName</td>";
            }

            $table_ratings .= "
                <td class=\"text-center\">$player->position</td>
                <td><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->decoratedName</a></td>
                <td class=\"text-center\">$player->age</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$player->ratingFieldGoalAttempts</td>
                <td class=\"text-center\">$player->ratingFieldGoalPercentage</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$player->ratingFreeThrowAttempts</td>
                <td class=\"text-center\">$player->ratingFreeThrowPercentage</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$player->ratingThreePointAttempts</td>
                <td class=\"text-center\">$player->ratingThreePointPercentage</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$player->ratingOffensiveRebounds</td>
                <td class=\"text-center\">$player->ratingDefensiveRebounds</td>
                <td class=\"text-center\">$player->ratingAssists</td>
                <td class=\"text-center\">$player->ratingSteals</td>
                <td class=\"text-center\">$player->ratingTurnovers</td>
                <td class=\"text-center\">$player->ratingBlocks</td>
                <td class=\"text-center\">$player->ratingFouls</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$player->ratingOutsideOffense</td>
                <td class=\"text-center\">$player->ratingDriveOffense</td>
                <td class=\"text-center\">$player->ratingPostOffense</td>
                <td class=\"text-center\">$player->ratingTransitionOffense</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$player->ratingOutsideDefense</td>
                <td class=\"text-center\">$player->ratingDriveDefense</td>
                <td class=\"text-center\">$player->ratingPostDefense</td>
                <td class=\"text-center\">$player->ratingTransitionDefense</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$player->ratingClutch</td>
                <td class=\"text-center\">$player->ratingConsistency</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$injuryInfo</td>
            </tr>";

            $i++;
        }
    
        $table_ratings .= "</tbody></table>";
    
        return $table_ratings;
    }

    public static function seasonAverages($db, $result, $team, $yr)
    {
        $table_averages = self::tableStyles('season-avg', $team->color1, $team->color2) . "<table style=\"margin: 0 auto;\" class=\"sortable season-avg\">
                <thead>
                    <tr style=\"background-color: #$team->color1;\">
                        <th>Pos</th>
                        <th colspan=3>Player</th>
                        <th>g</th>
                        <th>gs</th>
                        <th>min</th>
                        <th class=\"sep-team\"></th>
                        <th>fgm</th>
                        <th>fga</th>
                        <th>fgp</th>
                        <th class=\"sep-weak\"></th>
                        <th>ftm</th>
                        <th>fta</th>
                        <th>ftp</th>
                        <th class=\"sep-weak\"></th>
                        <th>3gm</th>
                        <th>3ga</th>
                        <th>3gp</th>
                        <th class=\"sep-team\"></th>
                        <th>orb</th>
                        <th>reb</th>
                        <th>ast</th>
                        <th>stl</th>
                        <th>to</th>
                        <th>blk</th>
                        <th>pf</th>
                        <th>pts</th>
                    </tr>
                </thead>
            <tbody>";
    
        /* =======================AVERAGES */
    
        $i = 0;
        foreach ($result as $plrRow) {
            if ($yr == "") {
                $player = Player::withPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withPlrRow($db, $plrRow);

                $firstCharacterOfPlayerName = substr($player->name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }
        
            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
        
            $table_averages .= "<tr style=\"background-color: #$bgcolor;\">
                <td>$player->position</td>
                <td colspan=3><a href=\"modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->decoratedName</a></td>
                <td class=\"text-center\">$playerStats->seasonGamesPlayed</td>
                <td class=\"text-center\">$playerStats->seasonGamesStarted</td>
                <td class=\"text-center\">$playerStats->seasonMinutesPerGame</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$playerStats->seasonFieldGoalsMadePerGame</td>
                <td class=\"text-center\">$playerStats->seasonFieldGoalsAttemptedPerGame</td>
                <td class=\"text-center\">$playerStats->seasonFieldGoalPercentage</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$playerStats->seasonFreeThrowsMadePerGame</td>
                <td class=\"text-center\">$playerStats->seasonFreeThrowsAttemptedPerGame</td>
                <td class=\"text-center\">$playerStats->seasonFreeThrowPercentage</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$playerStats->seasonThreePointersMadePerGame</td>
                <td class=\"text-center\">$playerStats->seasonThreePointersAttemptedPerGame</td>
                <td class=\"text-center\">$playerStats->seasonThreePointPercentage</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$playerStats->seasonOffensiveReboundsPerGame</td>
                <td class=\"text-center\">$playerStats->seasonTotalReboundsPerGame</td>
                <td class=\"text-center\">$playerStats->seasonAssistsPerGame</td>
                <td class=\"text-center\">$playerStats->seasonStealsPerGame</td>
                <td class=\"text-center\">$playerStats->seasonTurnoversPerGame</td>
                <td class=\"text-center\">$playerStats->seasonBlocksPerGame</td>
                <td class=\"text-center\">$playerStats->seasonPersonalFoulsPerGame</td>
                <td class=\"text-center\">$playerStats->seasonPointsPerGame</td>
            </tr>";

            $i++;
        }

        // ========= TEAM AVERAGES DISPLAY
    
        $table_averages .= "</tbody>
            <tfoot>";
    
        $teamStats = TeamStats::withTeamName($db, $team->name);
    
        if ($yr == "") {
            $table_averages .= "<tr>
                <td colspan=4><b>$team->name Offense</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseGamesPlayed</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseGamesPlayed</b></td>
                <td></td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseFieldGoalsMadePerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseFieldGoalsAttemptedPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseFieldGoalPercentage</b></td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseFreeThrowsMadePerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseFreeThrowsAttemptedPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseFreeThrowPercentage</b></td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseThreePointersMadePerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseThreePointersAttemptedPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseThreePointPercentage</b></td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseOffensiveReboundsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalReboundsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseAssistsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseStealsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTurnoversPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseBlocksPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffensePersonalFoulsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffensePointsPerGame</b></td>
            </tr>
            <tr>
                <td colspan=4><b>$team->name Defense</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseGamesPlayed</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseGamesPlayed</b></td>
                <td></td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseFieldGoalsMadePerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseFieldGoalsAttemptedPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseFieldGoalPercentage</b></td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseFreeThrowsMadePerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseFreeThrowsAttemptedPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseFreeThrowPercentage</b></td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseThreePointersMadePerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseThreePointersAttemptedPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseThreePointPercentage</b></td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseOffensiveReboundsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalReboundsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseAssistsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseStealsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTurnoversPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseBlocksPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefensePersonalFoulsPerGame</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefensePointsPerGame</b></td>
            </tr>";
        }
    
        $table_averages .= "</tfoot>
            </table>";
    
        return $table_averages;
    }

    public static function seasonTotals($db, $result, $team, $yr)
    {
        $table_totals = self::tableStyles('season-totals', $team->color1, $team->color2) . "<table style=\"margin: 0 auto;\" class=\"sortable season-totals\">
            <thead>
                <tr style=\"background-color: #$team->color1;\">
                    <th>Pos</th>
                    <th colspan=3>Player</th>
                    <th>g</th>
                    <th>gs</th>
                    <th>min</th>
                    <th class=\"sep-team\"></th>
                    <th>fgm</th>
                    <th>fga</th>
                    <th class=\"sep-weak\"></th>
                    <th>ftm</th>
                    <th>fta</th>
                    <th class=\"sep-weak\"></th>
                    <th>3gm</th>
                    <th>3ga</th>
                    <th class=\"sep-team\"></th>
                    <th>orb</th>
                    <th>reb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>pf</th>
                    <th>pts</th>
                </tr>
            </thead>
        <tbody>";

        $i = 0;
        foreach ($result as $plrRow) {
            if ($yr == "") {
                $player = Player::withPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withPlrRow($db, $plrRow);

                $firstCharacterOfPlayerName = substr($player->name, 0, 1); // if player name starts with '|' (pipe symbol), then skip them
                if ($firstCharacterOfPlayerName == '|') {
                    continue;
                }
            } else {
                $player = Player::withHistoricalPlrRow($db, $plrRow);
                $playerStats = PlayerStats::withHistoricalPlrRow($db, $plrRow);
            }
        
            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";
    
            $table_totals .= "<tr style=\"background-color: #$bgcolor;\">
                <td>$player->position</td>
                <td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$player->playerID\">$player->decoratedName</a></td>
                <td class=\"text-center\">$playerStats->seasonGamesPlayed</td>
                <td class=\"text-center\">$playerStats->seasonGamesStarted</td>
                <td class=\"text-center\">$playerStats->seasonMinutes</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$playerStats->seasonFieldGoalsMade</td>
                <td class=\"text-center\">$playerStats->seasonFieldGoalsAttempted</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$playerStats->seasonFreeThrowsMade</td>
                <td class=\"text-center\">$playerStats->seasonFreeThrowsAttempted</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$playerStats->seasonThreePointersMade</td>
                <td class=\"text-center\">$playerStats->seasonThreePointersAttempted</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$playerStats->seasonOffensiveRebounds</td>
                <td class=\"text-center\">$playerStats->seasonTotalRebounds</td>
                <td class=\"text-center\">$playerStats->seasonAssists</td>
                <td class=\"text-center\">$playerStats->seasonSteals</td>
                <td class=\"text-center\">$playerStats->seasonTurnovers</td>
                <td class=\"text-center\">$playerStats->seasonBlocks</td>
                <td class=\"text-center\">$playerStats->seasonPersonalFouls</td>
                <td class=\"text-center\">$playerStats->seasonPoints</td>
                </tr>";    

            $i++;
        }

        $table_totals .= "</tbody>
            <tfoot>";

        // ==== INSERT TEAM OFFENSE AND DEFENSE TOTALS ====

        $teamStats = TeamStats::withTeamName($db, $team->name);

        if ($yr == "") {
            $table_totals .= "<tr>
                <td colspan=4><b>$team->name Offense</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseGamesPlayed</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseGamesPlayed</b></td>
                <td></td>
                <td class='sep-team'></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalFieldGoalsMade</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalFieldGoalsAttempted</b></td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalFreeThrowsMade</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalFreeThrowsAttempted</b></td>
                <td class='sep-weak'></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalThreePointersMade</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalThreePointersAttempted</b></td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalOffensiveRebounds</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalRebounds</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalAssists</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalSteals</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalTurnovers</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalBlocks</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalPersonalFouls</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonOffenseTotalPoints</b></td>
            </tr>";
            
            $table_totals .= "<tr>
                <td colspan=4><b>$team->name Defense</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseGamesPlayed</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseGamesPlayed</b></td>
                <td></td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalFieldGoalsMade</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalFieldGoalsAttempted</b></td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalFreeThrowsMade</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalFreeThrowsAttempted</b></td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalThreePointersMade</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalThreePointersAttempted</b></td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalOffensiveRebounds</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalRebounds</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalAssists</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalSteals</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalTurnovers</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalBlocks</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalPersonalFouls</b></td>
                <td class=\"text-center\"><b>$teamStats->seasonDefenseTotalPoints</b></td>
            </tr>";
        }

        $table_totals .= "</tfoot>
            </table>";

        return $table_totals;
    }

    public static function periodAverages($db, $team, $season, $startDate = NULL, $endDate = NULL)
    {
        if ($startDate == NULL AND $endDate == NULL) {
            // default to last simulated period
            $startDate = $season->lastSimStartDate;
            $endDate = $season->lastSimEndDate;
        }
        
        // convert to Y-m-d format if DateTime object
        if ($startDate instanceof DateTime) {
            $startDate = $startDate->format('Y-m-d');
        }
        if ($endDate instanceof DateTime) {
            $endDate = $endDate->format('Y-m-d');
        }

        $table_periodAverages = self::tableStyles('sim-avg', $team->color1, $team->color2) . "<table style=\"margin: 0 auto;\" class=\"sortable sim-avg\">
            <thead>
                <tr style=\"background-color: #$team->color1;\">
                    <th>Pos</th>
                    <th colspan=3>Player</th>
                    <th>g</th>
                    <th>min</th>
                    <th class=\"sep-team\"></th>
                    <th>fgm</th>
                    <th>fga</th>
                    <th>fgp</th>
                    <th class=\"sep-weak\"></th>
                    <th>ftm</th>
                    <th>fta</th>
                    <th>ftp</th>
                    <th class=\"sep-weak\"></th>
                    <th>3gm</th>
                    <th>3ga</th>
                    <th>3gp</th>
                    <th class=\"sep-team\"></th>
                    <th>orb</th>
                    <th>reb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>pf</th>
                    <th>pts</th>
                </tr>
            </thead>
        <tbody>";

        $resultPlayerSimBoxScores = $db->sql_query("SELECT name,
            pos,
            pid,
            COUNT(DISTINCT `Date`) as games,
            ROUND(SUM(gameMIN)/COUNT(DISTINCT `Date`), 1) as gameMINavg,
            ROUND(SUM(game2GM + game3GM)/COUNT(DISTINCT `Date`), 2) as gameFGMavg,
            ROUND(SUM(game2GA + game3GA)/COUNT(DISTINCT `Date`), 2) as gameFGAavg,
            ROUND((SUM(game2GM) + SUM(game3GM)) / (SUM(game2GA) + SUM(game3GA)), 3) as gameFGPavg,
            ROUND(SUM(gameFTM)/COUNT(DISTINCT `Date`), 2) as gameFTMavg,
            ROUND(SUM(gameFTA)/COUNT(DISTINCT `Date`), 2) as gameFTAavg,
            ROUND((SUM(gameFTM)) / (SUM(gameFTA)), 3) as gameFTPavg,
            ROUND(SUM(game3GM)/COUNT(DISTINCT `Date`), 2) as game3GMavg,
            ROUND(SUM(game3GA)/COUNT(DISTINCT `Date`), 2) as game3GAavg,
            ROUND((SUM(game3GM)) / (SUM(game3GA)), 3) as game3GPavg,
            ROUND(SUM(gameORB)/COUNT(DISTINCT `Date`), 1) as gameORBavg,
            ROUND((SUM(gameORB) + SUM(gameDRB))/COUNT(DISTINCT `Date`), 1) as gameREBavg,
            ROUND(SUM(gameAST)/COUNT(DISTINCT `Date`), 1) as gameASTavg,
            ROUND(SUM(gameSTL)/COUNT(DISTINCT `Date`), 1) as gameSTLavg,
            ROUND(SUM(gameTOV)/COUNT(DISTINCT `Date`), 1) as gameTOVavg,
            ROUND(SUM(gameBLK)/COUNT(DISTINCT `Date`), 1) as gameBLKavg,
            ROUND(SUM(gamePF)/COUNT(DISTINCT `Date`) , 1) as gamePFavg,
            ROUND(((2 * SUM(game2GM)) + SUM(gameFTM) + (3 * SUM(game3GM)))/COUNT(DISTINCT `Date`) , 1) as gamePTSavg
        FROM   ibl_box_scores
        WHERE  date BETWEEN '$startDate' AND '$endDate'
            AND ( hometid = $team->teamID
                OR visitortid = $team->teamID )
            AND gameMIN > 0
            AND pid IN (SELECT pid
                        FROM   ibl_plr
                        WHERE  tid = $team->teamID
                            AND retired = 0
                            AND `name` NOT LIKE '%|%')
        GROUP  BY name, pos, pid
        ORDER  BY name ASC;");

        $periodAverageMIN = $periodAverageFGM = $periodAverageFGA = $periodAverageFGP = $periodAverageFTM = $periodAverageFTA = $periodAverageFTP = 0;
        $periodAverage3GM = $periodAverage3GA = $periodAverage3GP = $periodAverageORB = $periodAverageREB = $periodAverageAST = $periodAverageSTL = 0;
        $periodAverageTOV = $periodAverageBLK = $periodAveragePF = $periodAveragePTS = $i = 0;

        while ($row = $db->sql_fetch_assoc($resultPlayerSimBoxScores)) {
            $name = DatabaseService::safeHtmlOutput($row['name']); // Safely escape for HTML output
            $pos = $row['pos'];
            $pid = $row['pid'];
            $numberOfGamesPlayedInSim = $row['games'];
            $periodAverageMIN = $row['gameMINavg'];
            $periodAverageFGM = $row['gameFGMavg'];
            $periodAverageFGA = $row['gameFGAavg'];
            $periodAverageFGP = $row['gameFGPavg'] ?? '0.000';
            $periodAverageFTM = $row['gameFTMavg'];
            $periodAverageFTA = $row['gameFTAavg'];
            $periodAverageFTP = $row['gameFTPavg'] ?? '0.000';
            $periodAverage3GM = $row['game3GMavg'];
            $periodAverage3GA = $row['game3GAavg'];
            $periodAverage3GP = $row['game3GPavg'] ?? '0.000';
            $periodAverageORB = $row['gameORBavg'];
            $periodAverageREB = $row['gameREBavg'];
            $periodAverageAST = $row['gameASTavg'];
            $periodAverageSTL = $row['gameSTLavg'];
            $periodAverageTOV = $row['gameTOVavg'];
            $periodAverageBLK = $row['gameBLKavg'];
            $periodAveragePF = $row['gamePFavg'];
            $periodAveragePTS = $row['gamePTSavg'];

            (($i % 2) == 0) ? $bgcolor = "FFFFFF" : $bgcolor = "EEEEEE";

            $table_periodAverages .= "<tr style=\"background-color: #$bgcolor;\">
                <td>$pos</td>
                <td colspan=3><a href=\"./modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
                <td class=\"text-center\">$numberOfGamesPlayedInSim</td>
                <td class=\"text-center\">$periodAverageMIN</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$periodAverageFGM</td>
                <td class=\"text-center\">$periodAverageFGA</td>
                <td class=\"text-center\">$periodAverageFGP</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$periodAverageFTM</td>
                <td class=\"text-center\">$periodAverageFTA</td>
                <td class=\"text-center\">$periodAverageFTP</td>
                <td class=\"sep-weak\"></td>
                <td class=\"text-center\">$periodAverage3GM</td>
                <td class=\"text-center\">$periodAverage3GA</td>
                <td class=\"text-center\">$periodAverage3GP</td>
                <td class=\"sep-team\"></td>
                <td class=\"text-center\">$periodAverageORB</td>
                <td class=\"text-center\">$periodAverageREB</td>
                <td class=\"text-center\">$periodAverageAST</td>
                <td class=\"text-center\">$periodAverageSTL</td>
                <td class=\"text-center\">$periodAverageTOV</td>
                <td class=\"text-center\">$periodAverageBLK</td>
                <td class=\"text-center\">$periodAveragePF</td>
                <td class=\"text-center\">$periodAveragePTS</td>
            </tr>";

            $i++;
        }
    
        $table_periodAverages .= "</tbody>
            </table>";
    
        return $table_periodAverages;
    }
}