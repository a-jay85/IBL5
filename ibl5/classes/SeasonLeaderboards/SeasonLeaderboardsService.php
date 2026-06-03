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

        // Sort by the requested stat DESC
        $sortBy = (string) ($filters['sortby'] ?? 'PPG');
        usort($rows, static function (array $a, array $b) use ($sortBy): int {
            $aVal = self::getSortValue($a, $sortBy);
            $bVal = self::getSortValue($b, $sortBy);
            $cmp = $bVal <=> $aVal;
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['pid'] <=> $b['pid'];
        });

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
        $pid = $row['pid'];
        $name = $row['name'];
        $year = $row['year'];
        $teamname = $row['team'];
        $teamid = $row['teamid'];
        $teamCity = $row['team_city'] ?? '';
        $color1 = $row['color1'] ?? 'FFFFFF';
        $color2 = $row['color2'] ?? '000000';

        $games = $row['games'];
        $minutes = $row['minutes'];
        $fgm = $row['fgm'];
        $fga = $row['fga'];
        $ftm = $row['ftm'];
        $fta = $row['fta'];
        $tgm = $row['tgm'];
        $tga = $row['tga'];
        $orb = $row['orb'];
        $reb = $row['reb'];
        $ast = $row['ast'];
        $stl = $row['stl'];
        $tvr = $row['tvr'];
        $blk = $row['blk'];
        $pf = $row['pf'];

        // Calculate totals
        $points = StatsFormatter::calculatePoints($fgm, $ftm, $tgm);

        // Format percentages
        $fgp = StatsFormatter::formatPercentage($fgm, $fga);
        $ftp = StatsFormatter::formatPercentage($ftm, $fta);
        $tgp = StatsFormatter::formatPercentage($tgm, $tga);

        // Format per-game averages
        $mpg = StatsFormatter::formatPerGameAverage($minutes, $games);
        $fgmpg = StatsFormatter::formatPerGameAverage($fgm, $games);
        $fgapg = StatsFormatter::formatPerGameAverage($fga, $games);
        $ftmpg = StatsFormatter::formatPerGameAverage($ftm, $games);
        $ftapg = StatsFormatter::formatPerGameAverage($fta, $games);
        $tgmpg = StatsFormatter::formatPerGameAverage($tgm, $games);
        $tgapg = StatsFormatter::formatPerGameAverage($tga, $games);
        $orbpg = StatsFormatter::formatPerGameAverage($orb, $games);
        $drebpg = StatsFormatter::formatPerGameAverage($reb - $orb, $games);
        $rpg = StatsFormatter::formatPerGameAverage($reb, $games);
        $apg = StatsFormatter::formatPerGameAverage($ast, $games);
        $spg = StatsFormatter::formatPerGameAverage($stl, $games);
        $tpg = StatsFormatter::formatPerGameAverage($tvr, $games);
        $bpg = StatsFormatter::formatPerGameAverage($blk, $games);
        $fpg = StatsFormatter::formatPerGameAverage($pf, $games);
        $ppg = StatsFormatter::formatPerGameAverage($points, $games);

        // Calculate Quality Assessment (QA)
        $qa = $this->calculateQualityAssessment($games, $points, $reb, $ast, $stl, $blk, $fga, $fgm, $fta, $ftm, $tvr, $pf);

        return [
            'pid' => $pid,
            'name' => $name,
            'year' => $year,
            'teamname' => $teamname,
            'teamid' => $teamid,
            'team_city' => $teamCity,
            'color1' => $color1,
            'color2' => $color2,
            'games' => $games,
            'minutes' => $minutes,
            'fgm' => $fgm,
            'fga' => $fga,
            'ftm' => $ftm,
            'fta' => $fta,
            'tgm' => $tgm,
            'tga' => $tga,
            'orb' => $orb,
            'reb' => $reb,
            'ast' => $ast,
            'stl' => $stl,
            'tvr' => $tvr,
            'blk' => $blk,
            'pf' => $pf,
            'points' => $points,
            'fgp' => $fgp,
            'ftp' => $ftp,
            'tgp' => $tgp,
            'mpg' => $mpg,
            'fgmpg' => $fgmpg,
            'fgapg' => $fgapg,
            'ftmpg' => $ftmpg,
            'ftapg' => $ftapg,
            'tgmpg' => $tgmpg,
            'tgapg' => $tgapg,
            'orbpg' => $orbpg,
            'drebpg' => $drebpg,
            'rpg' => $rpg,
            'apg' => $apg,
            'spg' => $spg,
            'tpg' => $tpg,
            'bpg' => $bpg,
            'fpg' => $fpg,
            'ppg' => $ppg,
            'qa' => $qa,
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
