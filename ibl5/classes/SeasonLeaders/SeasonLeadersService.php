<?php

namespace SeasonLeaders;

use Statistics\StatsFormatter;

/**
 * SeasonLeadersService - Business logic for season leaders data processing
 * 
 * Handles data transformation and calculations for season statistics.
 */
class SeasonLeadersService
{
    /**
     * Process a player row from database into formatted statistics
     * 
     * @param array $row Database row from ibl_hist table
     * @return array Formatted player statistics
     */
    public function processPlayerRow(array $row): array
    {
        $stats = [];
        
        // Basic info
        $stats['pid'] = $row['pid'];
        $stats['name'] = $row['name'];
        $stats['year'] = $row['year'];
        $stats['teamname'] = $row['team'];
        $stats['teamid'] = $row['teamid'];
        
        // Raw stats
        $stats['games'] = $row['games'];
        $stats['minutes'] = $row['minutes'];
        $stats['fgm'] = $row['fgm'];
        $stats['fga'] = $row['fga'];
        $stats['ftm'] = $row['ftm'];
        $stats['fta'] = $row['fta'];
        $stats['tgm'] = $row['tgm'];
        $stats['tga'] = $row['tga'];
        $stats['orb'] = $row['orb'];
        $stats['reb'] = $row['reb'];
        $stats['ast'] = $row['ast'];
        $stats['stl'] = $row['stl'];
        $stats['tvr'] = $row['tvr'];
        $stats['blk'] = $row['blk'];
        $stats['pf'] = $row['pf'];
        
        // Calculate totals
        $stats['points'] = StatsFormatter::calculatePoints($stats['fgm'], $stats['ftm'], $stats['tgm']);
        
        // Format percentages
        $stats['fgp'] = StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']);
        $stats['ftp'] = StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']);
        $stats['tgp'] = StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']);
        
        // Format per-game averages
        $stats['mpg'] = StatsFormatter::formatPerGameAverage($stats['minutes'], $stats['games']);
        $stats['fgmpg'] = StatsFormatter::formatPerGameAverage($stats['fgm'], $stats['games']);
        $stats['fgapg'] = StatsFormatter::formatPerGameAverage($stats['fga'], $stats['games']);
        $stats['ftmpg'] = StatsFormatter::formatPerGameAverage($stats['ftm'], $stats['games']);
        $stats['ftapg'] = StatsFormatter::formatPerGameAverage($stats['fta'], $stats['games']);
        $stats['tgmpg'] = StatsFormatter::formatPerGameAverage($stats['tgm'], $stats['games']);
        $stats['tgapg'] = StatsFormatter::formatPerGameAverage($stats['tga'], $stats['games']);
        $stats['orbpg'] = StatsFormatter::formatPerGameAverage($stats['orb'], $stats['games']);
        $stats['rpg'] = StatsFormatter::formatPerGameAverage($stats['reb'], $stats['games']);
        $stats['apg'] = StatsFormatter::formatPerGameAverage($stats['ast'], $stats['games']);
        $stats['spg'] = StatsFormatter::formatPerGameAverage($stats['stl'], $stats['games']);
        $stats['tpg'] = StatsFormatter::formatPerGameAverage($stats['tvr'], $stats['games']);
        $stats['bpg'] = StatsFormatter::formatPerGameAverage($stats['blk'], $stats['games']);
        $stats['fpg'] = StatsFormatter::formatPerGameAverage($stats['pf'], $stats['games']);
        $stats['ppg'] = StatsFormatter::formatPerGameAverage($stats['points'], $stats['games']);
        
        // Calculate Quality Assessment (QA)
        $stats['qa'] = $this->calculateQualityAssessment($stats);
        
        return $stats;
    }

    /**
     * Calculate Quality Assessment metric
     * Formula: (pts + reb + 2*ast + 2*stl + 2*blk - (fga-fgm) - (fta-ftm) - tvr - pf) / games
     * 
     * @param array $stats Player statistics
     * @return string Formatted QA value
     */
    private function calculateQualityAssessment(array $stats): string
    {
        if ($stats['games'] == 0) {
            return "0.0";
        }
        
        $positives = $stats['points'] + $stats['reb'] + (2 * $stats['ast']) + (2 * $stats['stl']) + (2 * $stats['blk']);
        $negatives = ($stats['fga'] - $stats['fgm']) + ($stats['fta'] - $stats['ftm']) + $stats['tvr'] + $stats['pf'];
        $qa = ($positives - $negatives) / $stats['games'];
        
        return number_format($qa, 1);
    }

    /**
     * Get sort option labels for display
     * 
     * @return array Array of sort option labels
     */
    public function getSortOptions(): array
    {
        return [
            "PPG", "REB", "OREB", "AST", "STL", "BLK", "TO", "FOUL", 
            "QA", "FGM", "FGA", "FG%", "FTM", "FTA", "FT%", 
            "TGM", "TGA", "TG%", "GAMES", "MIN"
        ];
    }
}
