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

        $teamCityQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_city` ASC";
        $teamCityResult = $db->sql_query($teamCityQuery);
        $teamNameQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `team_name` ASC";
        $teamNameResult = $db->sql_query($teamNameQuery);
        $teamIDQuery = "SELECT `team_city`,`team_name`,`teamid` FROM `ibl_team_info` ORDER BY `teamid` ASC";
        $teamIDResult = $db->sql_query($teamIDQuery);

        ob_start();
        ?>
<center><table width=400 border=0><tr>
<p>
<b> Team Pages: </b>
<select name="teamSelectCity" onchange="location = this.options[this.selectedIndex].value;">
<option value="">Location</option>
<?php while ($row = $db->sql_fetch_assoc($teamCityResult)): ?>
<option value="./modules.php?name=Team&op=team&teamID=<?= $row["teamid"] ?>"><?= $row["team_city"] ?>	<?= $row["team_name"] ?></option>
<?php endwhile; ?>
</select>

<select name="teamSelectName" onchange="location = this.options[this.selectedIndex].value;">
<option value="">Namesake</option>
<?php while ($row = $db->sql_fetch_assoc($teamNameResult)): ?>
<option value="./modules.php?name=Team&op=team&teamID=<?= $row["teamid"] ?>"><?= $row["team_name"] ?></option>
<?php endwhile; ?>
</select>

<select name="teamSelectID" onchange="location = this.options[this.selectedIndex].value;">
<option value="">ID#</option>
<?php while ($row = $db->sql_fetch_assoc($teamIDResult)): ?>
<option value="./modules.php?name=Team&op=team&teamID=<?= $row["teamid"] ?>"><?= $row["teamid"] ?>	<?= $row["team_city"] ?>	<?= $row["team_name"] ?></option>
<?php endwhile; ?>
</select>

<td nowrap="nowrap"><a style="font:bold 11px Helvetica;text-decoration: none;background-color: #<?= $team->color2 ?>;color: #<?= $team->color1 ?>;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;" href="modules.php?name=Team&op=team&teamID=<?= $teamID ?>">Team Page</a></td>
<td nowrap="nowrap"><a style="font:bold 11px Helvetica;text-decoration: none;background-color: #<?= $team->color2 ?>;color: #<?= $team->color1 ?>;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;" href="modules.php?name=Team_Schedule&teamID=<?= $teamID ?>">Team Schedule</a></td>
<td nowrap="nowrap"><a style="font:bold 11px Helvetica;text-decoration: none;background-color: #<?= $team->color2 ?>;color: #<?= $team->color1 ?>;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;" href="modules/Team/draftHistory.php?teamID=<?= $teamID ?>">Draft History</a></td>
<td nowrap="nowrap" valign=center><font style="font:bold 14px Helvetica;text-decoration: none;"> | </td>
<td nowrap="nowrap"><a style="font:bold 11px Helvetica;text-decoration: none;background-color: #<?= $team->color2 ?>;color: #<?= $team->color1 ?>;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;" href="modules.php?name=Depth_Chart_Entry">Depth Chart Entry</a></td>
<td nowrap="nowrap"><a style="font:bold 11px Helvetica;text-decoration: none;background-color: #<?= $team->color2 ?>;color: #<?= $team->color1 ?>;padding: 2px 6px 2px 6px;border-top: 1px solid #000000;border-right: 1px solid #000000;border-bottom: 1px solid #000000;border-left: 1px solid #000000;" href="modules.php?name=Trading&op=reviewtrade">Trades/Waivers</a></td>
</tr></table></center>
<hr>
        <?php
        echo ob_get_clean();
    }

    public static function playerMenu()
    {
        ob_start();
        ?>
<center><b>
    <a href="modules.php?name=Player_Search">Player Search</a>  |
    <a href="modules.php?name=Player_Awards">Awards Search</a> |
    <a href="modules.php?name=One-on-One">One-on-One Game</a> |
    <a href="modules.php?name=Leaderboards">Career Leaderboards</a> (All Types)
</b><center>
<hr>
        <?php
        echo ob_get_clean();
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
        
        $cap1 = $cap2 = $cap3 = $cap4 = $cap5 = $cap6 = 0;
        $playerRows = [];
    
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
    
            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
            
            $playerRows[] = [
                'player' => $player,
                'bgcolor' => $bgcolor,
                'con1' => $con1,
                'con2' => $con2,
                'con3' => $con3,
                'con4' => $con4,
                'con5' => $con5,
                'con6' => $con6,
            ];
    
            $cap1 += $con1;
            $cap2 += $con2;
            $cap3 += $con3;
            $cap4 += $con4;
            $cap5 += $con5;
            $cap6 += $con6;
            $i++;
        }
        
        ob_start();
        echo self::tableStyles('contracts', $team->color1, $team->color2);
        ?>
<table align="center" class="sortable contracts">
    <thead>
        <tr bgcolor=<?= $team->color1 ?>>
            <th>Pos</th>
            <th colspan=2>Player</th>
            <th>Age</th>
            <th>Exp</th>
            <th>Bird</th>
            <th class="sep-team"></th>
            <th><?= substr(($season->endingYear + -1), -2) ?>-<?= substr(($season->endingYear + 0), -2) ?></th>
            <th><?= substr(($season->endingYear + 0), -2) ?>-<?= substr(($season->endingYear + 1), -2) ?></th>
            <th><?= substr(($season->endingYear + 1), -2) ?>-<?= substr(($season->endingYear + 2), -2) ?></th>
            <th><?= substr(($season->endingYear + 2), -2) ?>-<?= substr(($season->endingYear + 3), -2) ?></th>
            <th><?= substr(($season->endingYear + 3), -2) ?>-<?= substr(($season->endingYear + 4), -2) ?></th>
            <th class="sep-team"><?= substr(($season->endingYear + 4), -2) ?>-<?= substr(($season->endingYear + 5), -2) ?></th>
            <th class="sep-team"></th>
            <th>Tal</th>
            <th>Skl</th>
            <th>Int</th>
            <th class="sep-team"></th>
            <th>Loy</th>
            <th>PFW</th>
            <th>PT</th>
            <th>Sec</th>
            <th>Trad</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row): 
    $player = $row['player'];
    $bgcolor = $row['bgcolor'];
?>
        <tr bgcolor=<?= $bgcolor ?>>
            <td align=center><?= $player->position ?></td>
            <td colspan=2><a href="./modules.php?name=Player&pa=showpage&pid=<?= $player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td align=center><?= $player->age ?></td>
            <td align=center><?= $player->yearsOfExperience ?></td>
            <td align=center><?= $player->birdYears ?></td>
            <td class="sep-team"></td>
            <td><?= $row['con1'] ?></td>
            <td><?= $row['con2'] ?></td>
            <td><?= $row['con3'] ?></td>
            <td><?= $row['con4'] ?></td>
            <td><?= $row['con5'] ?></td>
            <td><?= $row['con6'] ?></td>
            <td class="sep-team"></td>
            <td align=center><?= $player->ratingTalent ?></td>
            <td align=center><?= $player->ratingSkill ?></td>
            <td align=center><?= $player->ratingIntangibles ?></td>
            <td class="sep-team"></td>
            <td align=center><?= $player->freeAgencyLoyalty ?></td>
            <td align=center><?= $player->freeAgencyPlayForWinner ?></td>
            <td align=center><?= $player->freeAgencyPlayingTime ?></td>
            <td align=center><?= $player->freeAgencySecurity ?></td>
            <td align=center><?= $player->freeAgencyTradition ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td colspan=2><b>Cap Totals</td>
            <td></td>
            <td></td>
            <td></td>
            <td class="sep-team"></td>
            <td><b><?= $cap1 ?></td>
            <td><b><?= $cap2 ?></td>
            <td><b><?= $cap3 ?></td>
            <td><b><?= $cap4 ?></td>
            <td><b><?= $cap5 ?></td>
            <td><b><?= $cap6 ?></td>
            <td class="sep-team"></td>
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
</table>
        <?php
        return ob_get_clean();
    }

    public static function per36Minutes($db, $result, $team, $yr)
    {
        $playerRows = [];
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
    
            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
            
            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
                'bgcolor' => $bgcolor,
                'stats_fgm' => StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsMade, $playerStats->seasonMinutes),
                'stats_fga' => StatsFormatter::formatPer36Stat($playerStats->seasonFieldGoalsAttempted, $playerStats->seasonMinutes),
                'stats_fgp' => StatsFormatter::formatPercentage($playerStats->seasonFieldGoalsMade, $playerStats->seasonFieldGoalsAttempted),
                'stats_ftm' => StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsMade, $playerStats->seasonMinutes),
                'stats_fta' => StatsFormatter::formatPer36Stat($playerStats->seasonFreeThrowsAttempted, $playerStats->seasonMinutes),
                'stats_ftp' => StatsFormatter::formatPercentage($playerStats->seasonFreeThrowsMade, $playerStats->seasonFreeThrowsAttempted),
                'stats_tgm' => StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersMade, $playerStats->seasonMinutes),
                'stats_tga' => StatsFormatter::formatPer36Stat($playerStats->seasonThreePointersAttempted, $playerStats->seasonMinutes),
                'stats_tgp' => StatsFormatter::formatPercentage($playerStats->seasonThreePointersMade, $playerStats->seasonThreePointersAttempted),
                'stats_mpg' => StatsFormatter::formatPerGameAverage($playerStats->seasonMinutes, $playerStats->seasonGamesPlayed),
                'stats_per36Min' => StatsFormatter::formatPer36Stat($playerStats->seasonMinutes, $playerStats->seasonMinutes),
                'stats_opg' => StatsFormatter::formatPer36Stat($playerStats->seasonOffensiveRebounds, $playerStats->seasonMinutes),
                'stats_rpg' => StatsFormatter::formatPer36Stat($playerStats->seasonTotalRebounds, $playerStats->seasonMinutes),
                'stats_apg' => StatsFormatter::formatPer36Stat($playerStats->seasonAssists, $playerStats->seasonMinutes),
                'stats_spg' => StatsFormatter::formatPer36Stat($playerStats->seasonSteals, $playerStats->seasonMinutes),
                'stats_tpg' => StatsFormatter::formatPer36Stat($playerStats->seasonTurnovers, $playerStats->seasonMinutes),
                'stats_bpg' => StatsFormatter::formatPer36Stat($playerStats->seasonBlocks, $playerStats->seasonMinutes),
                'stats_fpg' => StatsFormatter::formatPer36Stat($playerStats->seasonPersonalFouls, $playerStats->seasonMinutes),
                'stats_ppg' => StatsFormatter::formatPer36Stat($playerStats->seasonPoints, $playerStats->seasonMinutes),
            ];

            $i++;
        }
        
        ob_start();
        echo self::tableStyles('per36', $team->color1, $team->color2);
        ?>
<table align="center" class="sortable per36">
    <thead>
        <tr bgcolor=<?= $team->color1 ?>>
            <th>Pos</th>
            <th colspan=3>Player</th>
            <th>g</th>
            <th>gs</th>
            <th>mpg</th>
            <th>36min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th class="sep-weak"></th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th class="sep-weak"></th>
            <th>3gm</th>
            <th>3ga</th>
            <th>3gp</th>
            <th class="sep-team"></th>
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
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
    $playerStats = $row['playerStats'];
?>
        <tr bgcolor=<?= $row['bgcolor'] ?>>
            <td><?= $player->position ?></td>
            <td colspan=3><a href="modules.php?name=Player&pa=showpage&pid=<?= $player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td><center><?= $playerStats->seasonGamesPlayed ?></center></td>
            <td><center><?= $playerStats->seasonGamesStarted ?></center></td>
            <td><center><?= $row['stats_mpg'] ?></center></td>
            <td><center><?= $row['stats_per36Min'] ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $row['stats_fgm'] ?></center></td>
            <td><center><?= $row['stats_fga'] ?></center></td>
            <td><center><?= $row['stats_fgp'] ?></center></td>
            <td class="sep-weak"></td>
            <td><center><?= $row['stats_ftm'] ?></center></td>
            <td><center><?= $row['stats_fta'] ?></center></td>
            <td><center><?= $row['stats_ftp'] ?></center></td>
            <td class="sep-weak"></td>
            <td><center><?= $row['stats_tgm'] ?></center></td>
            <td><center><?= $row['stats_tga'] ?></center></td>
            <td><center><?= $row['stats_tgp'] ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $row['stats_opg'] ?></center></td>
            <td><center><?= $row['stats_rpg'] ?></center></td>
            <td><center><?= $row['stats_apg'] ?></center></td>
            <td><center><?= $row['stats_spg'] ?></center></td>
            <td><center><?= $row['stats_tpg'] ?></center></td>
            <td><center><?= $row['stats_bpg'] ?></center></td>
            <td><center><?= $row['stats_fpg'] ?></center></td>
            <td><center><?= $row['stats_ppg'] ?></center></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }
    
    public static function ratings($db, $data, $team, $yr, $season, $moduleName = "")
    {
        $playerRows = [];
        $i = 0;
        
        foreach ($data as $plrRow) {
            if ($yr == "") {
                if (is_object($data)) {
                    $player = Player::withPlrRow($db, $plrRow);
                    $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
                } elseif ($plrRow instanceof Player) {
                    $player = $plrRow;
                    if ($moduleName == "Next_Sim") {
                        $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "FFFFAA";
                    } elseif ($moduleName == "League_Starters") {
                        $bgcolor = ($player->teamID == $team->teamID) ? "FFFFAA" : "FFFFFF";
                    } else {
                        $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
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
                $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
            }
    
            $injuryInfo = $player->getInjuryReturnDate($season->lastSimEndDate);
            if ($injuryInfo != "") {
                $injuryInfo .= " ($player->daysRemainingForInjury days)";
            }
            
            $playerRows[] = [
                'player' => $player,
                'bgcolor' => $bgcolor,
                'injuryInfo' => $injuryInfo,
                'addSeparator' => (($i % 2) == 0 AND $moduleName == "Next_Sim"),
            ];

            $i++;
        }
        
        ob_start();
        echo self::tableStyles('ratings', $team->color1, $team->color2);
        ?>
<table align="center" class="sortable ratings">
<colgroup span=2><colgroup span=2><colgroup span=6><colgroup span=6><colgroup span=4><colgroup span=4><colgroup span=1>
    <thead bgcolor=<?= $team->color1 ?>>
        <tr bgcolor=<?= $team->color1 ?>>
<?php if ($moduleName == "League_Starters"): ?>
            <th>Team</th>
<?php endif; ?>
            <th>Pos</th>
            <th>Player</th>
            <th>Age</th>
            <th class="sep-team"></th>
            <th>2ga</th>
            <th>2g%</th>
            <th class="sep-team"></th>
            <th>fta</th>
            <th>ft%</th>
            <th class="sep-team"></th>
            <th>3ga</th>
            <th>3g%</th>
            <th class="sep-team"></th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th>foul</th>
            <th class="sep-team"></th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th class="sep-team"></th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th>td</th>
            <th class="sep-team"></th>
            <th>Clu</th>
            <th>Con</th>
            <th class="sep-team"></th>
            <th>Injury Return Date</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
    if ($row['addSeparator']): ?>
        <tr>
        <td colspan=55 bgcolor=<?= $team->color1 ?>>
        </td>
        </tr>
<?php endif; ?>
        <tr bgcolor=<?= $row['bgcolor'] ?>>
<?php if ($moduleName == "League_Starters"): ?>
            <td><?= $player->teamName ?></td>
<?php endif; ?>
            <td align=center><?= $player->position ?></td>
            <td><a href="./modules.php?name=Player&pa=showpage&pid=<?= $player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td align=center><?= $player->age ?></td>
            <td class="sep-team"></td>
            <td align=center><?= $player->ratingFieldGoalAttempts ?></td>
            <td align=center><?= $player->ratingFieldGoalPercentage ?></td>
            <td class="sep-weak"></td>
            <td align=center><?= $player->ratingFreeThrowAttempts ?></td>
            <td align=center><?= $player->ratingFreeThrowPercentage ?></td>
            <td class="sep-weak"></td>
            <td align=center><?= $player->ratingThreePointAttempts ?></td>
            <td align=center><?= $player->ratingThreePointPercentage ?></td>
            <td class="sep-team"></td>
            <td align=center><?= $player->ratingOffensiveRebounds ?></td>
            <td align=center><?= $player->ratingDefensiveRebounds ?></td>
            <td align=center><?= $player->ratingAssists ?></td>
            <td align=center><?= $player->ratingSteals ?></td>
            <td align=center><?= $player->ratingTurnovers ?></td>
            <td align=center><?= $player->ratingBlocks ?></td>
            <td align=center><?= $player->ratingFouls ?></td>
            <td class="sep-team"></td>
            <td align=center><?= $player->ratingOutsideOffense ?></td>
            <td align=center><?= $player->ratingDriveOffense ?></td>
            <td align=center><?= $player->ratingPostOffense ?></td>
            <td align=center><?= $player->ratingTransitionOffense ?></td>
            <td class="sep-weak"></td>
            <td align=center><?= $player->ratingOutsideDefense ?></td>
            <td align=center><?= $player->ratingDriveDefense ?></td>
            <td align=center><?= $player->ratingPostDefense ?></td>
            <td align=center><?= $player->ratingTransitionDefense ?></td>
            <td class="sep-team"></td>
            <td align=center><?= $player->ratingClutch ?></td>
            <td align=center><?= $player->ratingConsistency ?></td>
            <td class="sep-team"></td>
            <td align=center><?= $row['injuryInfo'] ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }

    public static function seasonAverages($db, $result, $team, $yr)
    {
        $playerRows = [];
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
        
            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
            
            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
                'bgcolor' => $bgcolor,
            ];

            $i++;
        }

        $teamStats = TeamStats::withTeamName($db, $team->name);
        
        ob_start();
        echo self::tableStyles('season-avg', $team->color1, $team->color2);
        ?>
<table align="center" class="sortable season-avg">
    <thead>
        <tr bgcolor=<?= $team->color1 ?>>
            <th>Pos</th>
            <th colspan=3>Player</th>
            <th>g</th>
            <th>gs</th>
            <th>min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th class="sep-weak"></th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th class="sep-weak"></th>
            <th>3gm</th>
            <th>3ga</th>
            <th>3gp</th>
            <th class="sep-team"></th>
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
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
    $playerStats = $row['playerStats'];
?>
        <tr bgcolor=<?= $row['bgcolor'] ?>>
            <td><?= $player->position ?></td>
            <td colspan=3><a href="modules.php?name=Player&pa=showpage&pid=<?= $player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td><center><?= $playerStats->seasonGamesPlayed ?></center></td>
            <td><center><?= $playerStats->seasonGamesStarted ?></center></td>
            <td><center><?= $playerStats->seasonMinutesPerGame ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $playerStats->seasonFieldGoalsMadePerGame ?></center></td>
            <td><center><?= $playerStats->seasonFieldGoalsAttemptedPerGame ?></center></td>
            <td><center><?= $playerStats->seasonFieldGoalPercentage ?></center></td>
            <td class="sep-weak"></td>
            <td><center><?= $playerStats->seasonFreeThrowsMadePerGame ?></center></td>
            <td><center><?= $playerStats->seasonFreeThrowsAttemptedPerGame ?></center></td>
            <td><center><?= $playerStats->seasonFreeThrowPercentage ?></center></td>
            <td class="sep-weak"></td>
            <td><center><?= $playerStats->seasonThreePointersMadePerGame ?></center></td>
            <td><center><?= $playerStats->seasonThreePointersAttemptedPerGame ?></center></td>
            <td><center><?= $playerStats->seasonThreePointPercentage ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $playerStats->seasonOffensiveReboundsPerGame ?></center></td>
            <td><center><?= $playerStats->seasonTotalReboundsPerGame ?></center></td>
            <td><center><?= $playerStats->seasonAssistsPerGame ?></center></td>
            <td><center><?= $playerStats->seasonStealsPerGame ?></center></td>
            <td><center><?= $playerStats->seasonTurnoversPerGame ?></center></td>
            <td><center><?= $playerStats->seasonBlocksPerGame ?></center></td>
            <td><center><?= $playerStats->seasonPersonalFoulsPerGame ?></center></td>
            <td><center><?= $playerStats->seasonPointsPerGame ?></center></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
<?php if ($yr == ""): ?>
        <tr>
            <td colspan=4><b><?= $team->name ?> Offense</td>
            <td><b><center><?= $teamStats->seasonOffenseGamesPlayed ?></center></td>
            <td><b><center><?= $teamStats->seasonOffenseGamesPlayed ?></center></td>
            <td></td>
            <td class="sep-team"></td>
            <td><center><b><?= $teamStats->seasonOffenseFieldGoalsMadePerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseFieldGoalsAttemptedPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseFieldGoalPercentage ?></center></td>
            <td class="sep-weak"></td>
            <td><center><b><?= $teamStats->seasonOffenseFreeThrowsMadePerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseFreeThrowsAttemptedPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseFreeThrowPercentage ?></center></td>
            <td class="sep-weak"></td>
            <td><center><b><?= $teamStats->seasonOffenseThreePointersMadePerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseThreePointersAttemptedPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseThreePointPercentage ?></center></td>
            <td class="sep-team"></td>
            <td><center><b><?= $teamStats->seasonOffenseOffensiveReboundsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalReboundsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseAssistsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseStealsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTurnoversPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffenseBlocksPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffensePersonalFoulsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonOffensePointsPerGame ?></center></td>
        </tr>
        <tr>
            <td colspan=4><b><?= $team->name ?> Defense</td>
            <td><center><b><?= $teamStats->seasonDefenseGamesPlayed ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseGamesPlayed ?></center></td>
            <td></td>
            <td class="sep-team"></td>
            <td><center><b><?= $teamStats->seasonDefenseFieldGoalsMadePerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseFieldGoalsAttemptedPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseFieldGoalPercentage ?></center></td>
            <td class="sep-weak"></td>
            <td><center><b><?= $teamStats->seasonDefenseFreeThrowsMadePerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseFreeThrowsAttemptedPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseFreeThrowPercentage ?></center></td>
            <td class="sep-weak"></td>
            <td><center><b><?= $teamStats->seasonDefenseThreePointersMadePerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseThreePointersAttemptedPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseThreePointPercentage ?></center></td>
            <td class="sep-team"></td>
            <td><center><b><?= $teamStats->seasonDefenseOffensiveReboundsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalReboundsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseAssistsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseStealsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTurnoversPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseBlocksPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefensePersonalFoulsPerGame ?></center></td>
            <td><center><b><?= $teamStats->seasonDefensePointsPerGame ?></center></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
    }

    public static function seasonTotals($db, $result, $team, $yr)
    {
        $playerRows = [];
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
        
            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
            
            $playerRows[] = [
                'player' => $player,
                'playerStats' => $playerStats,
                'bgcolor' => $bgcolor,
            ];

            $i++;
        }

        $teamStats = TeamStats::withTeamName($db, $team->name);
        
        ob_start();
        echo self::tableStyles('season-totals', $team->color1, $team->color2);
        ?>
<table align="center" class="sortable season-totals">
    <thead>
        <tr bgcolor=<?= $team->color1 ?>>
            <th>Pos</th>
            <th colspan=3>Player</th>
            <th>g</th>
            <th>gs</th>
            <th>min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th class="sep-weak"></th>
            <th>ftm</th>
            <th>fta</th>
            <th class="sep-weak"></th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-team"></th>
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
    <tbody>
<?php foreach ($playerRows as $row):
    $player = $row['player'];
    $playerStats = $row['playerStats'];
?>
        <tr bgcolor=<?= $row['bgcolor'] ?>>
            <td><?= $player->position ?></td>
            <td colspan=3><a href="./modules.php?name=Player&pa=showpage&pid=<?= $player->playerID ?>"><?= $player->decoratedName ?></a></td>
            <td><center><?= $playerStats->seasonGamesPlayed ?></center></td>
            <td><center><?= $playerStats->seasonGamesStarted ?></center></td>
            <td><center><?= $playerStats->seasonMinutes ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $playerStats->seasonFieldGoalsMade ?></center></td>
            <td><center><?= $playerStats->seasonFieldGoalsAttempted ?></center></td>
                <td class="sep-weak"></td>
            <td><center><?= $playerStats->seasonFreeThrowsMade ?></center></td>
            <td><center><?= $playerStats->seasonFreeThrowsAttempted ?></center></td>
                <td class="sep-weak"></td>
            <td><center><?= $playerStats->seasonThreePointersMade ?></center></td>
            <td><center><?= $playerStats->seasonThreePointersAttempted ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $playerStats->seasonOffensiveRebounds ?></center></td>
            <td><center><?= $playerStats->seasonTotalRebounds ?></center></td>
            <td><center><?= $playerStats->seasonAssists ?></center></td>
            <td><center><?= $playerStats->seasonSteals ?></center></td>
            <td><center><?= $playerStats->seasonTurnovers ?></center></td>
            <td><center><?= $playerStats->seasonBlocks ?></center></td>
            <td><center><?= $playerStats->seasonPersonalFouls ?></center></td>
            <td><center><?= $playerStats->seasonPoints ?></center></td>
            </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
<?php if ($yr == ""): ?>
        <tr>
            <td colspan=4><b><?= $team->name ?> Offense</td>
            <td><center><b><?= $teamStats->seasonOffenseGamesPlayed ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseGamesPlayed ?></b></center></td>
            <td></td>
            <td class='sep-team'></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalFieldGoalsMade ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalFieldGoalsAttempted ?></b></center></td>
            <td class="sep-weak"></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalFreeThrowsMade ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalFreeThrowsAttempted ?></b></center></td>
            <td class='sep-weak'></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalThreePointersMade ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalThreePointersAttempted ?></b></center></td>
            <td class="sep-team"></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalOffensiveRebounds ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalRebounds ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalAssists ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalSteals ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalTurnovers ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalBlocks ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalPersonalFouls ?></b></center></td>
            <td><center><b><?= $teamStats->seasonOffenseTotalPoints ?></center></td>
        </tr>
        <tr>
            <td colspan=4><b><?= $team->name ?> Defense</td>
            <td><center><b><?= $teamStats->seasonDefenseGamesPlayed ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseGamesPlayed ?></center></td>
            <td></td>
            <td class="sep-team"></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalFieldGoalsMade ?></b></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalFieldGoalsAttempted ?></b></center></td>
            <td class="sep-weak"></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalFreeThrowsMade ?></b></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalFreeThrowsAttempted ?></b></center></td>
            <td class="sep-weak"></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalThreePointersMade ?></b></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalThreePointersAttempted ?></b></center></td>
            <td class="sep-team"></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalOffensiveRebounds ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalRebounds ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalAssists ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalSteals ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalTurnovers ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalBlocks ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalPersonalFouls ?></center></td>
            <td><center><b><?= $teamStats->seasonDefenseTotalPoints ?></center></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return ob_get_clean();
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

        $playerRows = [];
        $i = 0;

        while ($row = $db->sql_fetch_assoc($resultPlayerSimBoxScores)) {
            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";
            
            $playerRows[] = [
                'name' => DatabaseService::safeHtmlOutput($row['name']),
                'pos' => $row['pos'],
                'pid' => $row['pid'],
                'games' => $row['games'],
                'min' => $row['gameMINavg'],
                'fgm' => $row['gameFGMavg'],
                'fga' => $row['gameFGAavg'],
                'fgp' => $row['gameFGPavg'] ?? '0.000',
                'ftm' => $row['gameFTMavg'],
                'fta' => $row['gameFTAavg'],
                'ftp' => $row['gameFTPavg'] ?? '0.000',
                'tgm' => $row['game3GMavg'],
                'tga' => $row['game3GAavg'],
                'tgp' => $row['game3GPavg'] ?? '0.000',
                'orb' => $row['gameORBavg'],
                'reb' => $row['gameREBavg'],
                'ast' => $row['gameASTavg'],
                'stl' => $row['gameSTLavg'],
                'tov' => $row['gameTOVavg'],
                'blk' => $row['gameBLKavg'],
                'pf' => $row['gamePFavg'],
                'pts' => $row['gamePTSavg'],
                'bgcolor' => $bgcolor,
            ];

            $i++;
        }
        
        ob_start();
        echo self::tableStyles('sim-avg', $team->color1, $team->color2);
        ?>
<table align="center" class="sortable sim-avg">
    <thead>
        <tr bgcolor=<?= $team->color1 ?>>
            <th>Pos</th>
            <th colspan=3>Player</th>
            <th>g</th>
            <th>min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th class="sep-weak"></th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th class="sep-weak"></th>
            <th>3gm</th>
            <th>3ga</th>
            <th>3gp</th>
            <th class="sep-team"></th>
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
    <tbody>
<?php foreach ($playerRows as $row): ?>
        <tr bgcolor=<?= $row['bgcolor'] ?>>
            <td><?= $row['pos'] ?></td>
            <td colspan=3><a href="./modules.php?name=Player&pa=showpage&pid=<?= $row['pid'] ?>"><?= $row['name'] ?></a></td>
            <td><center><?= $row['games'] ?></center></td>
            <td><center><?= $row['min'] ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $row['fgm'] ?></center></td>
            <td><center><?= $row['fga'] ?></center></td>
            <td><center><?= $row['fgp'] ?></center></td>
            <td class="sep-weak"></td>
            <td><center><?= $row['ftm'] ?></center></td>
            <td><center><?= $row['fta'] ?></center></td>
            <td><center><?= $row['ftp'] ?></center></td>
            <td class="sep-weak"></td>
            <td><center><?= $row['tgm'] ?></center></td>
            <td><center><?= $row['tga'] ?></center></td>
            <td><center><?= $row['tgp'] ?></center></td>
            <td class="sep-team"></td>
            <td><center><?= $row['orb'] ?></center></td>
            <td><center><?= $row['reb'] ?></center></td>
            <td><center><?= $row['ast'] ?></center></td>
            <td><center><?= $row['stl'] ?></center></td>
            <td><center><?= $row['tov'] ?></center></td>
            <td><center><?= $row['blk'] ?></center></td>
            <td><center><?= $row['pf'] ?></center></td>
            <td><center><?= $row['pts'] ?></center></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return ob_get_clean();
    }
}