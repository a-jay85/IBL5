<?php
namespace Updater;

use League\LeagueContext;

class PowerRankingsUpdater extends \BaseMysqliRepository {
    private $season;
    private ?LeagueContext $leagueContext;

    public function __construct(object $db, $season, ?LeagueContext $leagueContext = null) {
        parent::__construct($db);
        $this->season = $season;
        $this->leagueContext = $leagueContext;
    }

    public function update() {
        echo '<p>Updating the ibl_power database table...<p>';

        $log = '';

        $teams = $this->fetchAll(
            "SELECT TeamID, Team, streak_type, streak
            FROM ibl_power
            WHERE TeamID BETWEEN 1 AND 32
            ORDER BY TeamID ASC",
            ""
        );

        for ($i = 0; $i < count($teams); $i++) {
            $tid = $teams[$i]['TeamID'];
            $teamName = $teams[$i]['Team'];

            $month = $this->determineMonth();
            $games = $this->buildGamesQuery($tid, $month);

            $stats = $this->calculateTeamStats($games, $tid);
            $log .= $this->updateTeamStats($tid, $teamName, $stats);
        }

        \UI::displayDebugOutput($log, 'Power Rankings Update Log');

        // Reset the sim's Depth Chart sent status
        $this->execute(
            "UPDATE ibl_team_history SET sim_depth = 'No Depth Chart'",
            ""
        );

        echo '<p>The ibl_power table has been updated.<p>';
    }

    private function determineMonth() {
        if ($this->season->phase == "HEAT") {
            return \Season::IBL_HEAT_MONTH;
        } else {
            return \Season::IBL_REGULAR_SEASON_STARTING_MONTH;
        }
    }

    private function buildGamesQuery($tid, $month) {
        $startDate = $this->season->beginningYear . "-$month-01";
        $endDate = $this->season->endingYear . "-05-30";
        $scheduleTable = $this->leagueContext ? $this->leagueContext->getTableName('ibl_schedule') : 'ibl_schedule';
        
        return $this->fetchAll(
            "SELECT Visitor, VScore, Home, HScore
            FROM {$scheduleTable}
            WHERE (Visitor = ? OR Home = ?)
            AND (VScore > 0 AND HScore > 0)
            AND Date BETWEEN ? AND ?
            ORDER BY Date ASC",
            "iiss",
            $tid,
            $tid,
            $startDate,
            $endDate
        );
    }

    private function calculateTeamStats($games, $tid) {
        $stats = array(
            'wins' => 0, 'losses' => 0,
            'homeWins' => 0, 'homeLosses' => 0,
            'awayWins' => 0, 'awayLosses' => 0,
            'winPoints' => 0, 'lossPoints' => 0,
            'winsInLast10Games' => 0, 'lossesInLast10Games' => 0,
            'streak' => 0, 'streakType' => ''
        );

        for ($j = 0; $j < count($games); $j++) {
            $game = array(
                'awayTeam' => $games[$j]['Visitor'],
                'awayScore' => $games[$j]['VScore'],
                'homeTeam' => $games[$j]['Home'],
                'homeScore' => $games[$j]['HScore']
            );

            if ($game['awayScore'] !== $game['homeScore']) {
                $this->updateGameStats($stats, $game, $j, count($games), $tid);
            }
        }

        return $stats;
    }

    private function updateGameStats(&$stats, $game, $currentGame, $totalGames, $tid) {
        if ($tid == $game['awayTeam']) {
            $opponentTeam = $game['homeTeam'];
            $isWin = $game['awayScore'] > $game['homeScore'];
            $isHome = false;
        } else {
            $opponentTeam = $game['awayTeam'];
            $isWin = $game['homeScore'] > $game['awayScore'];
            $isHome = true;
        }

        $opponentRecord = $this->fetchOne(
            "SELECT win, loss FROM ibl_power WHERE TeamID = ?",
            "i",
            $opponentTeam
        );
        $opponentWins = $opponentRecord['win'] ?? 0;
        $opponentLosses = $opponentRecord['loss'] ?? 0;

        if ($isWin) {
            $stats['wins']++;
            $stats['winPoints'] += $opponentWins;
            if ($isHome) {
                $stats['homeWins']++;
            } else {
                $stats['awayWins']++;
            }
            if ($currentGame >= $totalGames - 10) {
                $stats['winsInLast10Games']++;
            }
            $stats['streak'] = ($stats['streakType'] == "W") ? ++$stats['streak'] : 1;
            $stats['streakType'] = "W";
        } else {
            $stats['losses']++;
            $stats['lossPoints'] += $opponentLosses;
            if ($isHome) {
                $stats['homeLosses']++;
            } else {
                $stats['awayLosses']++;
            }
            if ($currentGame >= $totalGames - 10) {
                $stats['lossesInLast10Games']++;
            }
            $stats['streak'] = ($stats['streakType'] == "L") ? ++$stats['streak'] : 1;
            $stats['streakType'] = "L";
        }
    }

    private function updateTeamStats($tid, $teamName, $stats) {
        $log = '';

        $gb = ($stats['wins'] / 2) - ($stats['losses'] / 2);
        $stats['winPoints'] += $stats['wins'];
        $stats['lossPoints'] += $stats['losses'];
        $ranking = ($stats['winPoints'] + $stats['lossPoints']) 
            ? round(($stats['winPoints'] / ($stats['winPoints'] + $stats['lossPoints'])) * 100, 1) 
            : 0;

        // Update ibl_power
        $this->execute(
            "UPDATE ibl_power SET
            win = ?,
            loss = ?,
            gb = ?,
            home_win = ?,
            home_loss = ?,
            road_win = ?,
            road_loss = ?,
            last_win = ?,
            last_loss = ?,
            streak_type = ?,
            streak = ?,
            ranking = ?
            WHERE TeamID = ?",
            "iidiiiiiisidi",
            $stats['wins'],
            $stats['losses'],
            $gb,
            $stats['homeWins'],
            $stats['homeLosses'],
            $stats['awayWins'],
            $stats['awayLosses'],
            $stats['winsInLast10Games'],
            $stats['lossesInLast10Games'],
            $stats['streakType'],
            $stats['streak'],
            $ranking,
            $tid
        );

        $log .= "Updating $teamName: {$stats['wins']} wins, {$stats['losses']} losses, $gb games back, 
            {$stats['homeWins']} home wins, {$stats['homeLosses']} home losses, 
            {$stats['awayWins']} away wins, {$stats['awayLosses']} away losses, 
            streak = {$stats['streakType']}{$stats['streak']}, 
            last 10 = {$stats['winsInLast10Games']}-{$stats['lossesInLast10Games']}, 
            ranking score = $ranking<br>";

        if ($this->season->phase == "HEAT" && $stats['wins'] != 0 && $stats['losses'] != 0) {
            $this->updateHeatRecords($teamName);
        } elseif ($this->season->phase == "Regular Season") {
            $this->updateSeasonRecords($teamName);
        }

        $this->updateHistoricalRecords();

        return $log;
    }

    private function updateSeasonRecords($teamName) {
        $this->execute(
            "UPDATE ibl_team_win_loss a, ibl_power b
            SET a.wins = b.win,
                a.losses = b.loss
            WHERE a.currentname = b.Team 
            AND a.year = ?",
            "i",
            $this->season->endingYear
        );
    }

    private function updateHeatRecords($teamName) {
        try {
            $this->execute(
                "UPDATE ibl_heat_win_loss a, ibl_power b
                SET a.wins = b.win,
                    a.losses = b.loss
                WHERE a.currentname = b.Team 
                AND a.year = ?",
                "i",
                $this->season->beginningYear
            );
            echo "Updated HEAT records for {$this->season->beginningYear}<p>";
        } catch (\Exception $e) {
            echo "<b>`ibl_heat_win_loss` update FAILED for $teamName! Have you <A HREF=\"leagueControlPanel.php\">inserted new database rows</A> for the new HEAT season?</b><p>";
        }
    }

    private function updateHistoricalRecords() {
        // Update total wins
        $this->execute(
            "UPDATE ibl_team_history a
            SET totwins = (
                SELECT SUM(b.wins)
                FROM ibl_team_win_loss AS b
                WHERE a.team_name = b.currentname
            )
            WHERE a.team_name != 'Free Agents'",
            ""
        );

        // Update total losses
        $this->execute(
            "UPDATE ibl_team_history a
            SET totloss = (
                SELECT SUM(b.losses)
                FROM ibl_team_win_loss AS b
                WHERE a.team_name = b.currentname
            )
            WHERE a.team_name != 'Free Agents'",
            ""
        );

        // Update win percentage
        $this->execute(
            "UPDATE ibl_team_history a 
            SET winpct = a.totwins / (a.totwins + a.totloss)
            WHERE a.team_name != 'Free Agents'",
            ""
        );
    }
}
