<?php

declare(strict_types=1);

namespace Updater;

use League\LeagueContext;
use Statistics\TeamStatsCalculator;
use StrengthOfSchedule\StrengthOfScheduleCalculator;
use Utilities\SeasonPhaseHelper;

/**
 * @phpstan-import-type TeamStats from TeamStatsCalculator
 */
class PowerRankingsUpdater extends \BaseMysqliRepository {
    private \Season $season;
    private TeamStatsCalculator $statsCalculator;
    private ?LeagueContext $leagueContext;

    public function __construct(\mysqli $db, \Season $season, ?TeamStatsCalculator $statsCalculator = null, ?LeagueContext $leagueContext = null) {
        parent::__construct($db);
        $this->season = $season;
        $this->statsCalculator = $statsCalculator ?? new TeamStatsCalculator($db, $leagueContext);
        $this->leagueContext = $leagueContext;
    }

    /**
     * Resolve a table name through LeagueContext (if set), else return as-is
     */
    private function resolveTable(string $iblTableName): string
    {
        return $this->leagueContext !== null
            ? $this->leagueContext->getTableName($iblTableName)
            : $iblTableName;
    }

    public function update(): void {
        $powerTable = $this->resolveTable('ibl_power');
        $teamInfoTable = $this->resolveTable('ibl_team_info');
        $isOlympics = $this->leagueContext !== null && $this->leagueContext->isOlympics();

        echo "<p>Updating the {$powerTable} database table...<p>";

        $log = '';

        $teams = $this->fetchAll(
            "SELECT TeamID, streak_type, streak
            FROM {$powerTable}
            ORDER BY TeamID ASC",
            ""
        );

        // Load team names for log messages
        /** @var list<array{teamid: int, team_name: string}> $teamInfoRows */
        $teamInfoRows = $this->fetchAll(
            "SELECT teamid, team_name FROM {$teamInfoTable}",
            ""
        );
        /** @var array<int, string> $teamNames */
        $teamNames = [];
        foreach ($teamInfoRows as $infoRow) {
            $teamNames[$infoRow['teamid']] = $infoRow['team_name'];
        }

        $month = $this->determineMonth();

        // Pre-load all team records to avoid N+1 queries in opponent record lookups
        $this->statsCalculator->preloadTeamRecords();

        // Fetch ALL played games once and partition by team
        $allPlayedGames = $this->fetchAllPlayedGames($month);
        $gamesByTeam = $this->partitionGamesByTeam($allPlayedGames);

        for ($i = 0; $i < count($teams); $i++) {
            /** @var array{TeamID: int, streak_type: string, streak: int} $teamRow */
            $teamRow = $teams[$i];
            $tid = $teamRow['TeamID'];
            $teamName = $teamNames[$tid] ?? 'Unknown';

            $games = $gamesByTeam[$tid] ?? [];

            $stats = $this->calculateTeamStats($games, $tid);
            $log .= $this->updateTeamStats($tid, $teamName, $stats);
        }

        \UI::displayDebugOutput($log, 'Power Rankings Update Log');

        // Calculate and store Strength of Schedule
        $this->calculateAndStoreSos($allPlayedGames, $month);

        // Reset the sim's Depth Chart sent status (IBL-only — Olympics doesn't have sim depth)
        if (!$isOlympics) {
            $this->execute(
                "UPDATE {$teamInfoTable} SET sim_depth = 'No Depth Chart'",
                ""
            );
        }

        echo "<p>The {$powerTable} table has been updated.<p>";
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
        $scheduleTable = $this->resolveTable('ibl_schedule');
        $startDate = $this->season->beginningYear . "-$month-01";
        $endDate = $this->season->endingYear . "-05-30";

        /** @var list<array{Visitor: int, VScore: int, Home: int, HScore: int}> */
        return $this->fetchAll(
            "SELECT Visitor, VScore, Home, HScore
            FROM {$scheduleTable}
            WHERE VScore > 0 AND HScore > 0
            AND Date BETWEEN ? AND ?
            ORDER BY Date ASC",
            "ss",
            $startDate,
            $endDate
        );
    }

    /**
     * Fetch all unplayed games for the season
     *
     * @return list<array{Visitor: int, Home: int}>
     */
    private function fetchAllUnplayedGames(int $month): array
    {
        $scheduleTable = $this->resolveTable('ibl_schedule');
        $startDate = $this->season->beginningYear . "-$month-01";
        $endDate = $this->season->endingYear . "-05-30";

        /** @var list<array{Visitor: int, Home: int}> */
        return $this->fetchAll(
            "SELECT Visitor, Home
            FROM {$scheduleTable}
            WHERE VScore = 0 AND HScore = 0
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
     * Partition unplayed games by team ID
     *
     * @param list<array{Visitor: int, Home: int}> $allGames
     * @return array<int, list<array{Visitor: int, Home: int}>>
     */
    private function partitionUnplayedGamesByTeam(array $allGames): array
    {
        /** @var array<int, list<array{Visitor: int, Home: int}>> $byTeam */
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
        $powerTable = $this->resolveTable('ibl_power');
        $log = '';

        $winPoints = $stats['winPoints'] + $stats['wins'];
        $lossPoints = $stats['lossPoints'] + $stats['losses'];
        $totalPoints = $winPoints + $lossPoints;
        $ranking = ($totalPoints > 0)
            ? round(($winPoints / $totalPoints) * 100, 1)
            : 0;

        // Update power table (slim schema: only ranking, last 10, streak)
        $this->execute(
            "UPDATE {$powerTable} SET
            last_win = ?,
            last_loss = ?,
            streak_type = ?,
            streak = ?,
            ranking = ?
            WHERE TeamID = ?",
            "iisidi",
            $stats['winsInLast10Games'],
            $stats['lossesInLast10Games'],
            $stats['streakType'],
            $stats['streak'],
            $ranking,
            $tid
        );

        $log .= "Updating {$teamName}: ranking={$ranking}, "
            . "streak={$stats['streakType']}{$stats['streak']}, "
            . "last 10={$stats['winsInLast10Games']}-{$stats['lossesInLast10Games']}<br>";

        return $log;
    }

    /**
     * Calculate and store SOS and remaining SOS for all teams
     *
     * @param list<array{Visitor: int, VScore: int, Home: int, HScore: int}> $allPlayedGames
     */
    private function calculateAndStoreSos(array $allPlayedGames, int $month): void
    {
        $standingsTable = $this->resolveTable('ibl_standings');
        $powerTable = $this->resolveTable('ibl_power');

        echo '<p>Calculating Strength of Schedule...<p>';

        // Load team win percentages from standings
        /** @var list<array{tid: int, wins: int, losses: int}> $standingsRows */
        $standingsRows = $this->fetchAll(
            "SELECT tid, wins, losses FROM {$standingsTable}",
            ""
        );

        /** @var array<int, float> $teamWinPcts */
        $teamWinPcts = [];
        foreach ($standingsRows as $row) {
            $totalGames = $row['wins'] + $row['losses'];
            $teamWinPcts[$row['tid']] = $totalGames > 0
                ? round($row['wins'] / $totalGames, 3)
                : 0.0;
        }

        // Partition played games by team (reuse format for SOS calc)
        /** @var array<int, list<array{Visitor: int, Home: int}>> $playedByTeam */
        $playedByTeam = [];
        foreach ($allPlayedGames as $game) {
            $simplified = ['Visitor' => $game['Visitor'], 'Home' => $game['Home']];
            $playedByTeam[$game['Visitor']][] = $simplified;
            $playedByTeam[$game['Home']][] = $simplified;
        }

        // Fetch and partition unplayed games
        $allUnplayedGames = $this->fetchAllUnplayedGames($month);
        $unplayedByTeam = $this->partitionUnplayedGamesByTeam($allUnplayedGames);

        // Calculate SOS and remaining SOS per team (data-driven from standings)
        $teamIds = array_keys($teamWinPcts);
        /** @var array<int, float> $sosValues */
        $sosValues = [];
        /** @var array<int, float> $remainingSosValues */
        $remainingSosValues = [];

        foreach ($teamIds as $tid) {
            $teamPlayedGames = $playedByTeam[$tid] ?? [];
            $teamUnplayedGames = $unplayedByTeam[$tid] ?? [];

            $sosValues[$tid] = StrengthOfScheduleCalculator::calculateAverageOpponentWinPct(
                $teamPlayedGames,
                $tid,
                $teamWinPcts
            );
            $remainingSosValues[$tid] = StrengthOfScheduleCalculator::calculateAverageOpponentWinPct(
                $teamUnplayedGames,
                $tid,
                $teamWinPcts
            );
        }

        // Rank teams by SOS and remaining SOS
        $sosRanks = StrengthOfScheduleCalculator::rankTeams($sosValues);
        $remainingSosRanks = StrengthOfScheduleCalculator::rankTeams($remainingSosValues);

        // Store SOS values in power table
        $sosLog = '';
        foreach ($teamIds as $tid) {
            $sos = $sosValues[$tid] ?? 0.0;
            $remainingSos = $remainingSosValues[$tid] ?? 0.0;
            $sosRank = $sosRanks[$tid] ?? 0;
            $remainingSosRank = $remainingSosRanks[$tid] ?? 0;

            $this->execute(
                "UPDATE {$powerTable} SET
                sos = ?,
                remaining_sos = ?,
                sos_rank = ?,
                remaining_sos_rank = ?
                WHERE TeamID = ?",
                "ddiii",
                $sos,
                $remainingSos,
                $sosRank,
                $remainingSosRank,
                $tid
            );

            $sosLog .= "TeamID {$tid}: SOS={$sos} (rank {$sosRank}), "
                . "RSOS={$remainingSos} (rank {$remainingSosRank})<br>";
        }

        \UI::displayDebugOutput($sosLog, 'Strength of Schedule Update Log');
        echo '<p>Strength of Schedule values have been calculated and stored.<p>';
    }
}
