<?php

declare(strict_types=1);

namespace Player;

use BaseMysqliRepository;
use Player\Contracts\PlayerStatsRepositoryInterface;

/**
 * PlayerStatsRepository - Database operations for player statistics
 * 
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * 
 * @see PlayerStatsRepositoryInterface
 */
class PlayerStatsRepository extends BaseMysqliRepository implements PlayerStatsRepositoryInterface
{
    /**
     * @see PlayerStatsRepositoryInterface::getPlayerStats
     */
    public function getPlayerStats(int $playerID): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHistoricalStats
     */
    public function getHistoricalStats(int $playerID, string $statsType = 'regular'): array
    {
        $table = $this->getHistoricalTableName($statsType);
        
        $results = $this->fetchAll(
            "SELECT * FROM {$table} WHERE pid = ? ORDER BY year DESC",
            "i",
            $playerID
        );

        return $this->normalizeHistoricalStats($results, $statsType);
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayerBoxScores
     */
    public function getPlayerBoxScores(int $playerID, string $startDate, string $endDate): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_box_scores WHERE pid = ? AND Date BETWEEN ? AND ? ORDER BY Date ASC",
            "iss",
            $playerID,
            $startDate,
            $endDate
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSimDateRanges
     */
    public function getSimDateRanges(int $limit = 20): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_sim_dates ORDER BY sim DESC LIMIT ?",
            "i",
            $limit
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSimAggregatedStats
     */
    public function getSimAggregatedStats(int $playerID, string $startDate, string $endDate): array
    {
        $boxScores = $this->getPlayerBoxScores($playerID, $startDate, $endDate);
        
        $aggregated = [
            'games' => count($boxScores),
            'minutes' => 0,
            'fg2Made' => 0,
            'fg2Attempted' => 0,
            'ftMade' => 0,
            'ftAttempted' => 0,
            'fg3Made' => 0,
            'fg3Attempted' => 0,
            'offRebounds' => 0,
            'defRebounds' => 0,
            'assists' => 0,
            'steals' => 0,
            'turnovers' => 0,
            'blocks' => 0,
            'fouls' => 0,
            'points' => 0
        ];

        foreach ($boxScores as $row) {
            $aggregated['minutes'] += (int) $row['gameMIN'];
            $aggregated['fg2Made'] += (int) $row['game2GM'];
            $aggregated['fg2Attempted'] += (int) $row['game2GA'];
            $aggregated['ftMade'] += (int) $row['gameFTM'];
            $aggregated['ftAttempted'] += (int) $row['gameFTA'];
            $aggregated['fg3Made'] += (int) $row['game3GM'];
            $aggregated['fg3Attempted'] += (int) $row['game3GA'];
            $aggregated['offRebounds'] += (int) $row['gameORB'];
            $aggregated['defRebounds'] += (int) $row['gameDRB'];
            $aggregated['assists'] += (int) $row['gameAST'];
            $aggregated['steals'] += (int) $row['gameSTL'];
            $aggregated['turnovers'] += (int) $row['gameTOV'];
            $aggregated['blocks'] += (int) $row['gameBLK'];
            $aggregated['fouls'] += (int) $row['gamePF'];
            $aggregated['points'] += (2 * (int) $row['game2GM']) + (int) $row['gameFTM'] + (3 * (int) $row['game3GM']);
        }

        return $aggregated;
    }

    /**
     * @see PlayerStatsRepositoryInterface::getCareerTotals
     */
    public function getCareerTotals(int $playerID): ?array
    {
        $row = $this->fetchOne(
            "SELECT car_gm, car_min, car_fgm, car_fga, car_ftm, car_fta, 
                    car_tgm, car_tga, car_orb, car_drb, car_reb,
                    car_ast, car_stl, car_to, car_blk, car_pf
             FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerID
        );

        return $row;
    }

    private function getHistoricalTableName(string $statsType): string
    {
        return match ($statsType) {
            'playoff' => 'ibl_hist_playoffs',
            'heat' => 'ibl_hist_heat',
            'olympic' => 'ibl_hist_olympics',
            default => 'ibl_hist'
        };
    }

    private function normalizeHistoricalStats(array $results, string $statsType): array
    {
        $normalized = [];
        
        foreach ($results as $row) {
            $normalized[] = [
                'year' => $row['year'] ?? '',
                'team' => $row['team'] ?? '',
                'games' => (int) ($row['gm'] ?? 0),
                'minutes' => (int) ($row['min'] ?? 0),
                'fgm' => (int) ($row['fgm'] ?? 0),
                'fga' => (int) ($row['fga'] ?? 0),
                'ftm' => (int) ($row['ftm'] ?? 0),
                'fta' => (int) ($row['fta'] ?? 0),
                'tgm' => (int) ($row['3gm'] ?? 0),
                'tga' => (int) ($row['3ga'] ?? 0),
                'orb' => (int) ($row['orb'] ?? 0),
                'drb' => (int) (($row['reb'] ?? 0) - ($row['orb'] ?? 0)),
                'reb' => (int) ($row['reb'] ?? 0),
                'ast' => (int) ($row['ast'] ?? 0),
                'stl' => (int) ($row['stl'] ?? 0),
                'blk' => (int) ($row['blk'] ?? 0),
                'tovr' => (int) ($row['tvr'] ?? 0),
                'pf' => (int) ($row['pf'] ?? 0),
                'pts' => $this->calculatePoints(
                    (int) ($row['fgm'] ?? 0),
                    (int) ($row['ftm'] ?? 0),
                    (int) ($row['3gm'] ?? 0)
                )
            ];
        }

        return $normalized;
    }

    private function calculatePoints(int $fgm, int $ftm, int $tgm): int
    {
        return (2 * $fgm) + $ftm + $tgm;
    }
}
