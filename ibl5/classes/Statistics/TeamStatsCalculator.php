<?php

declare(strict_types=1);

namespace Statistics;

/**
 * TeamStatsCalculator - Calculate team statistics from game data
 *
 * Computes wins, losses, home/away splits, streaks, and ranking scores
 * from game result data for power rankings updates.
 *
 * @phpstan-type GameRow array{Visitor: int, VScore: int, Home: int, HScore: int}
 * @phpstan-type NormalizedGame array{awayTeam: int, awayScore: int, homeTeam: int, homeScore: int}
 * @phpstan-type TeamStats array{wins: int, losses: int, homeWins: int, homeLosses: int, awayWins: int, awayLosses: int, winPoints: int, lossPoints: int, winsInLast10Games: int, lossesInLast10Games: int, streak: int, streakType: string}
 */
class TeamStatsCalculator
{
    private object $db;

    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * Calculate team statistics from an array of games
     *
     * @param list<GameRow> $games Array of game data with Visitor, VScore, Home, HScore
     * @param int $tid Team ID to calculate stats for
     * @return TeamStats
     */
    public function calculate(array $games, int $tid): array
    {
        $stats = $this->initializeStats();

        $totalGames = count($games);
        foreach ($games as $index => $gameData) {
            $game = $this->normalizeGameData($gameData);

            if ($game['awayScore'] !== $game['homeScore']) {
                $this->updateGameStats($stats, $game, $index, $totalGames, $tid);
            }
        }

        return $stats;
    }

    /**
     * Initialize empty stats array
     *
     * @return TeamStats
     */
    private function initializeStats(): array
    {
        return [
            'wins' => 0,
            'losses' => 0,
            'homeWins' => 0,
            'homeLosses' => 0,
            'awayWins' => 0,
            'awayLosses' => 0,
            'winPoints' => 0,
            'lossPoints' => 0,
            'winsInLast10Games' => 0,
            'lossesInLast10Games' => 0,
            'streak' => 0,
            'streakType' => '',
        ];
    }

    /**
     * Normalize game data to standard format
     *
     * @param GameRow $gameData
     * @return NormalizedGame
     */
    private function normalizeGameData(array $gameData): array
    {
        return [
            'awayTeam' => $gameData['Visitor'] ?? 0,
            'awayScore' => $gameData['VScore'] ?? 0,
            'homeTeam' => $gameData['Home'] ?? 0,
            'homeScore' => $gameData['HScore'] ?? 0,
        ];
    }

    /**
     * Update stats based on a single game result
     *
     * @param TeamStats $stats
     * @param NormalizedGame $game
     */
    private function updateGameStats(array &$stats, array $game, int $currentGame, int $totalGames, int $tid): void
    {
        if ($tid === $game['awayTeam']) {
            $opponentTeam = $game['homeTeam'];
            $isWin = $game['awayScore'] > $game['homeScore'];
            $isHome = false;
        } else {
            $opponentTeam = $game['awayTeam'];
            $isWin = $game['homeScore'] > $game['awayScore'];
            $isHome = true;
        }

        $opponentRecord = $this->getOpponentRecord($opponentTeam);
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
            $stats['streak'] = ($stats['streakType'] === "W") ? $stats['streak'] + 1 : 1;
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
            $stats['streak'] = ($stats['streakType'] === "L") ? $stats['streak'] + 1 : 1;
            $stats['streakType'] = "L";
        }
    }

    /**
     * Get opponent's record from database
     *
     * @return array{win: int, loss: int}
     */
    private function getOpponentRecord(int $teamId): array
    {
        // Use method_exists for duck-typing compatibility with MockDatabase and real db
        if (method_exists($this->db, 'fetchOne')) {
            /** @var array{win: int, loss: int}|null $result */
            $result = $this->db->fetchOne(
                "SELECT win, loss FROM ibl_power WHERE TeamID = ?",
                "i",
                $teamId
            );
            return $result ?? ['win' => 0, 'loss' => 0];
        }

        return ['win' => 0, 'loss' => 0];
    }

    /**
     * Calculate ranking score from win/loss points
     */
    public static function calculateRankingScore(int $wins, int $losses, int $winPoints, int $lossPoints): float
    {
        $totalWinPoints = $winPoints + $wins;
        $totalLossPoints = $lossPoints + $losses;
        $total = $totalWinPoints + $totalLossPoints;

        if ($total === 0) {
            return 0.0;
        }

        return round(($totalWinPoints / $total) * 100, 1);
    }

    /**
     * Calculate games back from first place
     */
    public static function calculateGamesBack(int $wins, int $losses): float
    {
        return ($wins / 2) - ($losses / 2);
    }
}
