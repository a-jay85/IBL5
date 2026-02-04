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
        $isRetired = ($retired !== '0' && $retired !== 0);
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
     * @see CareerLeaderboardsServiceInterface::getBoardTypes()
     *
     * @return array<string, string>
     */
    public function getBoardTypes(): array
    {
        return [
            'ibl_hist' => 'Regular Season Totals',
            'ibl_season_career_avgs' => 'Regular Season Averages',
            'ibl_playoff_career_totals' => 'Playoff Totals',
            'ibl_playoff_career_avgs' => 'Playoff Averages',
            'ibl_heat_career_totals' => 'H.E.A.T. Totals',
            'ibl_heat_career_avgs' => 'H.E.A.T. Averages',
            'ibl_olympics_career_totals' => 'Olympic Totals',
            'ibl_olympics_career_avgs' => 'Olympic Averages',
        ];
    }

    /**
     * @see CareerLeaderboardsServiceInterface::getSortCategories()
     *
     * @return array<string, string>
     */
    public function getSortCategories(): array
    {
        return [
            'pts' => 'Points',
            'games' => 'Games',
            'minutes' => 'Minutes',
            'fgm' => 'Field Goals Made',
            'fga' => 'Field Goals Attempted',
            'fgpct' => 'FG Percentage (avgs only)',
            'ftm' => 'Free Throws Made',
            'fta' => 'Free Throws Attempted',
            'ftpct' => 'FT Percentage (avgs only)',
            'tgm' => 'Three-Pointers Made',
            'tga' => 'Three-Pointers Attempted',
            'tpct' => '3P Percentage (avgs only)',
            'orb' => 'Offensive Rebounds',
            'reb' => 'Total Rebounds',
            'ast' => 'Assists',
            'stl' => 'Steals',
            'tvr' => 'Turnovers',
            'blk' => 'Blocked Shots',
            'pf' => 'Personal Fouls',
        ];
    }
}
