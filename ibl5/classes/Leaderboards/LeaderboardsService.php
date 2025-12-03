<?php

declare(strict_types=1);

namespace Leaderboards;

use Statistics\StatsFormatter;
use Leaderboards\Contracts\LeaderboardsServiceInterface;

/**
 * @see LeaderboardsServiceInterface
 */
class LeaderboardsService implements LeaderboardsServiceInterface
{
    /**
     * @see LeaderboardsServiceInterface::processPlayerRow()
     */
    public function processPlayerRow(array $row, string $tableType): array
    {
        $stats = [];
        
        // Basic info
        $stats['pid'] = $row['pid'];
        $stats['name'] = $row['name'] . ($row['retired'] ? '*' : '');
        
        // Process based on table type
        if ($tableType === 'averages') {
            $stats['games'] = round((float)$row['games']);
            $stats['minutes'] = StatsFormatter::formatAverage($row['minutes']);
            $stats['fgm'] = StatsFormatter::formatAverage($row['fgm']);
            $stats['fga'] = StatsFormatter::formatAverage($row['fga']);
            $stats['fgp'] = StatsFormatter::formatPercentageWithDecimals($row['fgpct'], 1, 3);
            $stats['ftm'] = StatsFormatter::formatAverage($row['ftm']);
            $stats['fta'] = StatsFormatter::formatAverage($row['fta']);
            $stats['ftp'] = StatsFormatter::formatPercentageWithDecimals($row['ftpct'], 1, 3);
            $stats['tgm'] = StatsFormatter::formatAverage($row['tgm']);
            $stats['tga'] = StatsFormatter::formatAverage($row['tga']);
            $stats['tgp'] = StatsFormatter::formatPercentageWithDecimals($row['tpct'], 1, 3);
            $stats['orb'] = StatsFormatter::formatAverage($row['orb']);
            $stats['reb'] = StatsFormatter::formatAverage($row['reb']);
            $stats['ast'] = StatsFormatter::formatAverage($row['ast']);
            $stats['stl'] = StatsFormatter::formatAverage($row['stl']);
            $stats['tvr'] = StatsFormatter::formatAverage($row['tvr']);
            $stats['blk'] = StatsFormatter::formatAverage($row['blk']);
            $stats['pf'] = StatsFormatter::formatAverage($row['pf']);
            $stats['pts'] = StatsFormatter::formatAverage($row['pts']);
        } else {
            // Totals
            $stats['games'] = StatsFormatter::formatTotal($row['games']);
            $stats['minutes'] = StatsFormatter::formatTotal($row['minutes']);
            $stats['fgm'] = StatsFormatter::formatTotal($row['fgm']);
            $stats['fga'] = StatsFormatter::formatTotal($row['fga']);
            $stats['fgp'] = StatsFormatter::formatPercentage($row['fgm'], $row['fga']);
            $stats['ftm'] = StatsFormatter::formatTotal($row['ftm']);
            $stats['fta'] = StatsFormatter::formatTotal($row['fta']);
            $stats['ftp'] = StatsFormatter::formatPercentage($row['ftm'], $row['fta']);
            $stats['tgm'] = StatsFormatter::formatTotal($row['tgm']);
            $stats['tga'] = StatsFormatter::formatTotal($row['tga']);
            $stats['tgp'] = StatsFormatter::formatPercentage($row['tgm'], $row['tga']);
            $stats['orb'] = StatsFormatter::formatTotal($row['orb']);
            $stats['reb'] = StatsFormatter::formatTotal($row['reb']);
            $stats['ast'] = StatsFormatter::formatTotal($row['ast']);
            $stats['stl'] = StatsFormatter::formatTotal($row['stl']);
            $stats['tvr'] = StatsFormatter::formatTotal($row['tvr']);
            $stats['blk'] = StatsFormatter::formatTotal($row['blk']);
            $stats['pf'] = StatsFormatter::formatTotal($row['pf']);
            $stats['pts'] = StatsFormatter::formatTotal($row['pts']);
        }
        
        return $stats;
    }

    /**
     * @see LeaderboardsServiceInterface::getBoardTypes()
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
     * @see LeaderboardsServiceInterface::getSortCategories()
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
