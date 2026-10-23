<?php

declare(strict_types=1);

namespace SeasonLeaderboards;

use BasketballStats\StatsFormatter;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsServiceInterface;

/**
 * @see SeasonLeaderboardsServiceInterface
 *
 * @phpstan-import-type HistRow from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardFilters from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardResult from SeasonLeaderboardsRepositoryInterface
 * @phpstan-import-type ProcessedStats from SeasonLeaderboardsServiceInterface
 */
class SeasonLeaderboardsService implements SeasonLeaderboardsServiceInterface
{
    private SeasonLeaderboardsRepositoryInterface $repository;

    public function __construct(SeasonLeaderboardsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see SeasonLeaderboardsServiceInterface::getFilteredLeaderboard()
     *
     * @param LeaderboardFilters $filters
     * @return LeaderboardResult
     */
    public function getFilteredLeaderboard(array $filters, int $limit = 0): array
    {
        $result = $this->repository->getSeasonLeaders([], 0);
        /** @var list<HistRow> $rows */
        $rows = $result['results'];

        // Filter by year
        $yearFilter = (string) ($filters['year'] ?? '');
        if ($yearFilter !== '') {
            $yearInt = (int) $yearFilter;
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['year'] === $yearInt
            ));
        }

        // Filter by team
        $teamId = (int) ($filters['team'] ?? 0);
        if ($teamId !== 0) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['teamid'] === $teamId
            ));
        }

        // Sort by the requested stat DESC.
        // Decorate-sort-undecorate: compute each row's sort key once (O(n)) instead
        // of recomputing it for both operands inside the comparator (O(n log n) calls).
        // On the full ibl_hist set this cuts the sort from ~33ms to ~8ms per load.
        $sortBy = (string) ($filters['sortby'] ?? 'PPG');

        /** @var list<array{key: float, pid: int, row: HistRow}> $decorated */
        $decorated = array_map(
            static fn (array $row): array => [
                'key' => self::getSortValue($row, $sortBy),
                'pid' => $row['pid'],
                'row' => $row,
            ],
            $rows
        );

        usort($decorated, static function (array $a, array $b): int {
            $cmp = $b['key'] <=> $a['key'];
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['pid'] <=> $b['pid'];
        });

        /** @var list<HistRow> $rows */
        $rows = array_column($decorated, 'row');

        // Apply limit
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'results' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * @see SeasonLeaderboardsServiceInterface::processPlayerRow()
     *
     * @param HistRow $row Database row from `ibl_hist` table
     * @return ProcessedStats Formatted player statistics
     */
    public function processPlayerRow(array $row): array
    {
        $games = $row['games'];
        $points = StatsFormatter::calculatePoints($row['fgm'], $row['ftm'], $row['tgm']);

        return [
            'pid' => $row['pid'],
            'name' => $row['name'],
            'year' => $row['year'],
            'teamname' => $row['team'],
            'teamid' => $row['teamid'],
            'team_city' => $row['team_city'] ?? '',
            'color1' => $row['color1'] ?? 'FFFFFF',
            'color2' => $row['color2'] ?? '000000',
            'games' => $games,
            'minutes' => $row['minutes'],
            'fgm' => $row['fgm'],
            'fga' => $row['fga'],
            'ftm' => $row['ftm'],
            'fta' => $row['fta'],
            'tgm' => $row['tgm'],
            'tga' => $row['tga'],
            'orb' => $row['orb'],
            'reb' => $row['reb'],
            'ast' => $row['ast'],
            'stl' => $row['stl'],
            'tvr' => $row['tvr'],
            'blk' => $row['blk'],
            'pf' => $row['pf'],
            'points' => $points,
            'fgp' => StatsFormatter::formatPercentage($row['fgm'], $row['fga']),
            'ftp' => StatsFormatter::formatPercentage($row['ftm'], $row['fta']),
            'tgp' => StatsFormatter::formatPercentage($row['tgm'], $row['tga']),
            'mpg' => StatsFormatter::formatPerGameAverage($row['minutes'], $games),
            'fgmpg' => StatsFormatter::formatPerGameAverage($row['fgm'], $games),
            'fgapg' => StatsFormatter::formatPerGameAverage($row['fga'], $games),
            'ftmpg' => StatsFormatter::formatPerGameAverage($row['ftm'], $games),
            'ftapg' => StatsFormatter::formatPerGameAverage($row['fta'], $games),
            'tgmpg' => StatsFormatter::formatPerGameAverage($row['tgm'], $games),
            'tgapg' => StatsFormatter::formatPerGameAverage($row['tga'], $games),
            'orbpg' => StatsFormatter::formatPerGameAverage($row['orb'], $games),
            'drebpg' => StatsFormatter::formatPerGameAverage($row['reb'] - $row['orb'], $games), // mutation-pr probe
            'rpg' => StatsFormatter::formatPerGameAverage($row['reb'], $games),
            'apg' => StatsFormatter::formatPerGameAverage($row['ast'], $games),
            'spg' => StatsFormatter::formatPerGameAverage($row['stl'], $games),
            'tpg' => StatsFormatter::formatPerGameAverage($row['tvr'], $games),
            'bpg' => StatsFormatter::formatPerGameAverage($row['blk'], $games),
            'fpg' => StatsFormatter::formatPerGameAverage($row['pf'], $games),
            'ppg' => StatsFormatter::formatPerGameAverage($points, $games),
            'qa' => $this->calculateQualityAssessment(
                $games,
                $points,
                $row['reb'],
                $row['ast'],
                $row['stl'],
                $row['blk'],
                $row['fga'],
                $row['fgm'],
                $row['fta'],
                $row['ftm'],
                $row['tvr'],
                $row['pf'],
            ),
        ];
    }

    /**
     * Calculate Quality Assessment metric
     *
     * @return string Formatted QA value (1 decimal place)
     */
    private function calculateQualityAssessment(
        int $games,
        int $points,
        int $reb,
        int $ast,
        int $stl,
        int $blk,
        int $fga,
        int $fgm,
        int $fta,
        int $ftm,
        int $tvr,
        int $pf
    ): string {
        if ($games === 0) {
            return "0.0";
        }

        $positives = $points + $reb + (2 * $ast) + (2 * $stl) + (2 * $blk);
        $negatives = ($fga - $fgm) + ($fta - $ftm) + $tvr + $pf;
        $qa = ($positives - $negatives) / $games;

        return \BasketballStats\StatsFormatter::formatWithDecimals($qa, 1);
    }

    /**
     * @see SeasonLeaderboardsServiceInterface::getSortOptions()
     *
     * @return array<string, string>
     */
    public function getSortOptions(): array
    {
        return [
            'PPG' => 'PPG', 'REB' => 'REB', 'OREB' => 'OREB', 'DREB' => 'DREB',
            'AST' => 'AST', 'STL' => 'STL', 'BLK' => 'BLK', 'TO' => 'TO',
            'FOUL' => 'FOUL', 'QA' => 'QA', 'FGM' => 'FGM', 'FGA' => 'FGA',
            'FGP' => 'FG%', 'FTM' => 'FTM', 'FTA' => 'FTA', 'FTP' => 'FT%',
            'TGM' => 'TGM', 'TGA' => 'TGA', 'TGP' => 'TG%', 'GAMES' => 'GAMES',
            'MIN' => 'MIN',
        ];
    }

    /**
     * @param HistRow $row
     */
    private static function getSortValue(array $row, string $sortBy): float
    {
        $games = $row['games'];
        if ($games === 0 && $sortBy !== 'GAMES') {
            return 0.0;
        }

        $fgm = $row['fgm'];
        $fga = $row['fga'];
        $ftm = $row['ftm'];
        $fta = $row['fta'];
        $tgm = $row['tgm'];
        $tga = $row['tga'];

        return match ($sortBy) {
            'PPG' => StatsFormatter::calculatePoints($fgm, $ftm, $tgm) / $games,
            'REB' => $row['reb'] / $games,
            'OREB' => $row['orb'] / $games,
            'DREB' => ($row['reb'] - $row['orb']) / $games,
            'AST' => $row['ast'] / $games,
            'STL' => $row['stl'] / $games,
            'BLK' => $row['blk'] / $games,
            'TO' => $row['tvr'] / $games,
            'FOUL' => $row['pf'] / $games,
            'QA' => self::computeQaPerGame($row, $games),
            'FGM' => $fgm / $games,
            'FGA' => $fga / $games,
            'FGP' => $fga > 0 ? $fgm / $fga : 0.0,
            'FTM' => $ftm / $games,
            'FTA' => $fta / $games,
            'FTP' => $fta > 0 ? $ftm / $fta : 0.0,
            'TGM' => $tgm / $games,
            'TGA' => $tga / $games,
            'TGP' => $tga > 0 ? $tgm / $tga : 0.0,
            'GAMES' => $games,
            'MIN' => $row['minutes'] / $games,
            default => StatsFormatter::calculatePoints($fgm, $ftm, $tgm) / $games,
        };
    }

    /**
     * @param HistRow $row
     */
    private static function computeQaPerGame(array $row, int $games): float
    {
        $fgm = $row['fgm'];
        $fga = $row['fga'];
        $ftm = $row['ftm'];
        $fta = $row['fta'];
        $tgm = $row['tgm'];

        $pts = StatsFormatter::calculatePoints($fgm, $ftm, $tgm);
        $positive = $pts + $row['reb'] + (2 * $row['ast']) + (2 * $row['stl']) + (2 * $row['blk']);
        $negative = ($fga - $fgm) + ($fta - $ftm) + $row['tvr'] + $row['pf'];

        return ($positive - $negative) / $games;
    }
}
