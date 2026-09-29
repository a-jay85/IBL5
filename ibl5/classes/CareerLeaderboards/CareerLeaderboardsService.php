<?php

declare(strict_types=1);

namespace CareerLeaderboards;

use BasketballStats\StatsFormatter;
use CareerLeaderboards\Contracts\CareerLeaderboardsServiceInterface;

/**
 * @see CareerLeaderboardsServiceInterface
 *
 * @phpstan-import-type CareerStatsRow from Contracts\CareerLeaderboardsRepositoryInterface
 * @phpstan-import-type FormattedPlayerStats from Contracts\CareerLeaderboardsServiceInterface
 */
class CareerLeaderboardsService implements CareerLeaderboardsServiceInterface
{
    /** @var array<string, array{totals: string, averages: string|null}> */
    private const PHASE_TABLES = [
        'regular' => ['totals' => 'ibl_hist', 'averages' => 'ibl_season_career_avgs'],
        'playoffs' => ['totals' => 'ibl_playoff_career_totals', 'averages' => 'ibl_playoff_career_avgs'],
        'heat' => ['totals' => 'ibl_heat_career_totals', 'averages' => 'ibl_heat_career_avgs'],
        'olympics' => ['totals' => 'ibl_olympics_career_totals', 'averages' => 'ibl_olympics_career_avgs'],
        'rookie' => ['totals' => 'ibl_rookie_career_totals', 'averages' => null],
        'sophomore' => ['totals' => 'ibl_sophomore_career_totals', 'averages' => null],
        'allstar' => ['totals' => 'ibl_allstar_career_totals', 'averages' => 'ibl_allstar_career_avgs'],
    ];

    /**
     * Season-tab sort keys and order (minus QA), mapped to career columns.
     * PPG is labelled PTS on totals. On totals tables the repository derives the
     * percentage columns from made/attempted totals.
     *
     * @var array<string, array{label: string, column: string}>
     */
    private const SORT_OPTIONS = [
        'PPG' => ['label' => 'PPG', 'column' => 'pts'],
        'REB' => ['label' => 'REB', 'column' => 'reb'],
        'OREB' => ['label' => 'OREB', 'column' => 'orb'],
        'DREB' => ['label' => 'DREB', 'column' => 'drb'],
        'AST' => ['label' => 'AST', 'column' => 'ast'],
        'STL' => ['label' => 'STL', 'column' => 'stl'],
        'BLK' => ['label' => 'BLK', 'column' => 'blk'],
        'TO' => ['label' => 'TO', 'column' => 'tvr'],
        'FOUL' => ['label' => 'FOUL', 'column' => 'pf'],
        'FGM' => ['label' => 'FGM', 'column' => 'fgm'],
        'FGA' => ['label' => 'FGA', 'column' => 'fga'],
        'FGP' => ['label' => 'FG%', 'column' => 'fgpct'],
        'FTM' => ['label' => 'FTM', 'column' => 'ftm'],
        'FTA' => ['label' => 'FTA', 'column' => 'fta'],
        'FTP' => ['label' => 'FT%', 'column' => 'ftpct'],
        'TGM' => ['label' => 'TGM', 'column' => 'tgm'],
        'TGA' => ['label' => 'TGA', 'column' => 'tga'],
        'TGP' => ['label' => 'TG%', 'column' => 'tpct'],
        'GAMES' => ['label' => 'GAMES', 'column' => 'games'],
        'MIN' => ['label' => 'MIN', 'column' => 'minutes'],
    ];

    /**
     * @see CareerLeaderboardsServiceInterface::processPlayerRow()
     *
     * @param CareerStatsRow $row
     * @return FormattedPlayerStats
     */
    public function processPlayerRow(array $row, string $tableType): array
    {
        $pid = $row['pid'];
        $retired = $row['retired'];
        $isRetired = $retired !== 0;
        $name = $row['name'] . ($isRetired ? '*' : '');

        // Process based on table type
        if ($tableType === 'averages') {
            $games = round((float) $row['games']);
            $minutes = StatsFormatter::formatAverage($row['minutes']);
            $fgm = StatsFormatter::formatAverage($row['fgm']);
            $fga = StatsFormatter::formatAverage($row['fga']);
            $fgp = StatsFormatter::formatPercentageWithDecimals($row['fgpct'] ?? null, 1, 3);
            $ftm = StatsFormatter::formatAverage($row['ftm']);
            $fta = StatsFormatter::formatAverage($row['fta']);
            $ftp = StatsFormatter::formatPercentageWithDecimals($row['ftpct'] ?? null, 1, 3);
            $tgm = StatsFormatter::formatAverage($row['tgm']);
            $tga = StatsFormatter::formatAverage($row['tga']);
            $tgp = StatsFormatter::formatPercentageWithDecimals($row['tpct'] ?? null, 1, 3);
            $orb = StatsFormatter::formatAverage($row['orb']);
            $drb = StatsFormatter::formatAverage((float) $row['reb'] - (float) $row['orb']);
            $reb = StatsFormatter::formatAverage($row['reb']);
            $ast = StatsFormatter::formatAverage($row['ast']);
            $stl = StatsFormatter::formatAverage($row['stl']);
            $tvr = StatsFormatter::formatAverage($row['tvr']);
            $blk = StatsFormatter::formatAverage($row['blk']);
            $pf = StatsFormatter::formatAverage($row['pf']);
            $pts = StatsFormatter::formatAverage($row['pts']);
        } else {
            // Totals
            $games = StatsFormatter::formatTotal($row['games']);
            $minutes = StatsFormatter::formatTotal($row['minutes']);
            $fgm = StatsFormatter::formatTotal($row['fgm']);
            $fga = StatsFormatter::formatTotal($row['fga']);
            $fgp = StatsFormatter::formatPercentage($row['fgm'], $row['fga']);
            $ftm = StatsFormatter::formatTotal($row['ftm']);
            $fta = StatsFormatter::formatTotal($row['fta']);
            $ftp = StatsFormatter::formatPercentage($row['ftm'], $row['fta']);
            $tgm = StatsFormatter::formatTotal($row['tgm']);
            $tga = StatsFormatter::formatTotal($row['tga']);
            $tgp = StatsFormatter::formatPercentage($row['tgm'], $row['tga']);
            $orb = StatsFormatter::formatTotal($row['orb']);
            $drb = StatsFormatter::formatTotal((int) $row['reb'] - (int) $row['orb']);
            $reb = StatsFormatter::formatTotal($row['reb']);
            $ast = StatsFormatter::formatTotal($row['ast']);
            $stl = StatsFormatter::formatTotal($row['stl']);
            $tvr = StatsFormatter::formatTotal($row['tvr']);
            $blk = StatsFormatter::formatTotal($row['blk']);
            $pf = StatsFormatter::formatTotal($row['pf']);
            $pts = StatsFormatter::formatTotal($row['pts']);
        }

        return [
            'pid' => $pid,
            'name' => $name,
            'games' => $games,
            'minutes' => $minutes,
            'fgm' => $fgm,
            'fga' => $fga,
            'fgp' => $fgp,
            'ftm' => $ftm,
            'fta' => $fta,
            'ftp' => $ftp,
            'tgm' => $tgm,
            'tga' => $tga,
            'tgp' => $tgp,
            'orb' => $orb,
            'drb' => $drb,
            'reb' => $reb,
            'ast' => $ast,
            'stl' => $stl,
            'tvr' => $tvr,
            'blk' => $blk,
            'pf' => $pf,
            'pts' => $pts,
        ];
    }

    /**
     * @see CareerLeaderboardsServiceInterface::getPhases()
     *
     * @return array<string, string>
     */
    public function getPhases(): array
    {
        return [
            'regular' => 'Regular Season',
            'playoffs' => 'Playoffs',
            'heat' => 'H.E.A.T.',
            'olympics' => 'Olympics',
            'rookie' => 'Rookie Game',
            'sophomore' => 'Sophomore Game',
            'allstar' => 'All-Star Game',
        ];
    }

    /**
     * @see CareerLeaderboardsServiceInterface::phaseHasAverages()
     */
    public function phaseHasAverages(string $phase): bool
    {
        return self::PHASE_TABLES[$this->resolvePhase($phase)]['averages'] !== null;
    }

    /**
     * @see CareerLeaderboardsServiceInterface::resolvePhase()
     */
    public function resolvePhase(string $phase): string
    {
        return isset(self::PHASE_TABLES[$phase]) ? $phase : 'regular';
    }

    /**
     * @see CareerLeaderboardsServiceInterface::resolveMode()
     */
    public function resolveMode(string $phase, string $mode): string
    {
        return ($mode === 'averages' && $this->phaseHasAverages($phase)) ? 'averages' : 'totals';
    }

    /**
     * @see CareerLeaderboardsServiceInterface::resolveTableKey()
     */
    public function resolveTableKey(string $phase, string $mode): string
    {
        $tables = self::PHASE_TABLES[$this->resolvePhase($phase)];

        if ($this->resolveMode($phase, $mode) === 'averages' && $tables['averages'] !== null) {
            return $tables['averages'];
        }

        return $tables['totals'];
    }

    /**
     * @see CareerLeaderboardsServiceInterface::getSortOptions()
     *
     * @return array<string, string>
     */
    public function getSortOptions(string $mode): array
    {
        $options = [];
        foreach (self::SORT_OPTIONS as $key => $option) {
            $options[$key] = ($key === 'PPG' && $mode !== 'averages') ? 'PTS' : $option['label'];
        }

        return $options;
    }

    /**
     * @see CareerLeaderboardsServiceInterface::isSortAvailable()
     */
    public function isSortAvailable(string $key, string $mode): bool
    {
        return isset(self::SORT_OPTIONS[$key]);
    }

    /**
     * @see CareerLeaderboardsServiceInterface::resolveSortKey()
     */
    public function resolveSortKey(string $key, string $mode): string
    {
        return $this->isSortAvailable($key, $mode) ? $key : 'PPG';
    }

    /**
     * @see CareerLeaderboardsServiceInterface::resolveSortColumn()
     */
    public function resolveSortColumn(string $key, string $mode): string
    {
        return self::SORT_OPTIONS[$this->resolveSortKey($key, $mode)]['column'];
    }
}
