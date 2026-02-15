<?php

declare(strict_types=1);

namespace Updater;

use Statistics\TeamStatsCalculator;
use Utilities\SeasonPhaseHelper;

/**
 * @phpstan-import-type TeamStats from TeamStatsCalculator
 */
class PowerRankingsUpdater extends \BaseMysqliRepository {
    private \Season $season;
    private TeamStatsCalculator $statsCalculator;

    public function __construct(\mysqli $db, \Season $season, ?TeamStatsCalculator $statsCalculator = null) {
        parent::__construct($db);
        $this->season = $season;
        $this->statsCalculator = $statsCalculator ?? new TeamStatsCalculator($db);
    }

    public function update(): void {
        echo '<p>Updating the ibl_power database table...<p>';

        $log = '';

        $teams = $this->fetchAll(
            "SELECT TeamID, Team, streak_type, streak
            FROM ibl_power
            WHERE TeamID BETWEEN 1 AND " . \League::MAX_REAL_TEAMID . "
            ORDER BY TeamID ASC",
            ""
        );

        $month = $this->determineMonth();

        // Pre-load all team records to avoid N+1 queries in opponent record lookups
        $this->statsCalculator->preloadTeamRecords();

        // Fetch ALL played games once and partition by team
        $allGames = $this->fetchAllPlayedGames($month);
        $gamesByTeam = $this->partitionGamesByTeam($allGames);

        for ($i = 0; $i < count($teams); $i++) {
            /** @var array{TeamID: int, Team: string, streak_type: string, streak: int} $teamRow */
            $teamRow = $teams[$i];
            $tid = $teamRow['TeamID'];
            $teamName = $teamRow['Team'];

            $games = $gamesByTeam[$tid] ?? [];

            $stats = $this->calculateTeamStats($games, $tid);
            $log .= $this->updateTeamStats($tid, $teamName, $stats);
        }

        \UI::displayDebugOutput($log, 'Power Rankings Update Log');

        // Reset the sim's Depth Chart sent status
        $this->execute(
            "UPDATE ibl_team_info SET sim_depth = 'No Depth Chart'",
            ""
        );

        echo '<p>The ibl_power table has been updated.<p>';
    }

    protected function determineMonth(): int {
        return SeasonPhaseHelper::getMonthForPhase($this->season->phase);
    }

    /**
     * Fetch all played games for the season in a single query
     *
     * @return list<array{Visitor: int, VScore: int, Home: int, HScore: int}>
     */
    private function fetchAllPlayedGames(int $month): array
    {
        $startDate = $this->season->beginningYear . "-$month-01";
        $endDate = $this->season->endingYear . "-05-30";

        /** @var list<array{Visitor: int, VScore: int, Home: int, HScore: int}> */
        return $this->fetchAll(
            "SELECT Visitor, VScore, Home, HScore
            FROM ibl_schedule
            WHERE VScore > 0 AND HScore > 0
            AND Date BETWEEN ? AND ?
            ORDER BY Date ASC",
            "ss",
            $startDate,
            $endDate
        );
    }

    /**
     * Partition games by team ID (each game appears under both participating teams)
     *
     * @param list<array{Visitor: int, VScore: int, Home: int, HScore: int}> $allGames
     * @return array<int, list<array{Visitor: int, VScore: int, Home: int, HScore: int}>>
     */
    private function partitionGamesByTeam(array $allGames): array
    {
        /** @var array<int, list<array{Visitor: int, VScore: int, Home: int, HScore: int}>> $byTeam */
        $byTeam = [];

        foreach ($allGames as $game) {
            $byTeam[$game['Visitor']][] = $game;
            $byTeam[$game['Home']][] = $game;
        }

        return $byTeam;
    }

    /**
     * @param list<array{Visitor: int, VScore: int, Home: int, HScore: int}> $games
     * @return TeamStats
     */
    protected function calculateTeamStats(array $games, int $tid): array {
        return $this->statsCalculator->calculate($games, $tid);
    }

    /**
     * @param TeamStats $stats
     */
    private function updateTeamStats(int $tid, string $teamName, array $stats): string {
        $log = '';

        $gb = $stats['wins'] / 2 - $stats['losses'] / 2;
        $winPoints = $stats['winPoints'] + $stats['wins'];
        $lossPoints = $stats['lossPoints'] + $stats['losses'];
        $totalPoints = $winPoints + $lossPoints;
        $ranking = ($totalPoints > 0)
            ? round(($winPoints / $totalPoints) * 100, 1)
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

        $log .= "Updating {$teamName}: {$stats['wins']} wins, {$stats['losses']} losses, {$gb} games back,
            {$stats['homeWins']} home wins, {$stats['homeLosses']} home losses,
            {$stats['awayWins']} away wins, {$stats['awayLosses']} away losses,
            streak = {$stats['streakType']}{$stats['streak']},
            last 10 = {$stats['winsInLast10Games']}-{$stats['lossesInLast10Games']},
            ranking score = {$ranking}<br>";

        return $log;
    }
}
