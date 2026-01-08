<?php

declare(strict_types=1);

namespace LeagueStats;

use LeagueStats\Contracts\LeagueStatsServiceInterface;
use BasketballStats\StatsFormatter;

/**
 * Service for processing league-wide team statistics
 *
 * Transforms raw database results into formatted statistics including:
 * - Per-game averages and shooting percentages for each team
 * - League-wide totals and averages
 * - Offense/defense differentials for comparative analysis
 *
 * Uses Statistics\StatsFormatter for consistent formatting across the application.
 *
 * @see LeagueStatsServiceInterface for method documentation
 */
class LeagueStatsService implements LeagueStatsServiceInterface
{
    /**
     * Stat keys for offense/defense data (excluding games)
     */
    private const STAT_KEYS = ['fgm', 'fga', 'ftm', 'fta', 'tgm', 'tga', 'orb', 'reb', 'ast', 'stl', 'tvr', 'blk', 'pf'];

    /**
     * Process raw team statistics into formatted data
     *
     * @see LeagueStatsServiceInterface::processTeamStats()
     * @param array $rawStats Raw statistics from repository
     * @return array Processed team statistics with formatted values
     */
    public function processTeamStats(array $rawStats): array
    {
        $processed = [];

        foreach ($rawStats as $row) {
            $offenseGames = (int) ($row['offense_games'] ?? 0);
            $defenseGames = (int) ($row['defense_games'] ?? 0);

            // Extract raw offense values
            $rawOffense = $this->extractRawStats($row, 'offense_');
            $rawDefense = $this->extractRawStats($row, 'defense_');

            // Calculate points using StatsFormatter
            $rawOffense['pts'] = StatsFormatter::calculatePoints(
                $rawOffense['fgm'],
                $rawOffense['ftm'],
                $rawOffense['tgm']
            );
            $rawDefense['pts'] = StatsFormatter::calculatePoints(
                $rawDefense['fgm'],
                $rawDefense['ftm'],
                $rawDefense['tgm']
            );

            // Format totals
            $offenseTotals = $this->formatTotals($rawOffense, $offenseGames);
            $defenseTotals = $this->formatTotals($rawDefense, $defenseGames);

            // Format per-game averages
            $offenseAverages = $this->formatAverages($rawOffense, $offenseGames);
            $defenseAverages = $this->formatAverages($rawDefense, $defenseGames);

            $processed[] = [
                'teamid' => (int) $row['teamid'],
                'team_city' => $row['team_city'],
                'team_name' => $row['team_name'],
                'color1' => $row['color1'],
                'color2' => $row['color2'],
                'offense_totals' => $offenseTotals,
                'offense_averages' => $offenseAverages,
                'defense_totals' => $defenseTotals,
                'defense_averages' => $defenseAverages,
                'raw_offense' => $rawOffense,
                'raw_defense' => $rawDefense,
                'offense_games' => $offenseGames,
                'defense_games' => $defenseGames,
            ];
        }

        return $processed;
    }

    /**
     * Calculate league-wide totals and averages
     *
     * @see LeagueStatsServiceInterface::calculateLeagueTotals()
     * @param array $processedStats Processed team statistics from processTeamStats()
     * @return array League totals and averages
     */
    public function calculateLeagueTotals(array $processedStats): array
    {
        // Initialize league totals
        $leagueTotals = array_fill_keys(self::STAT_KEYS, 0);
        $leagueTotals['pts'] = 0;
        $totalGames = 0;

        // Sum all team offense stats
        foreach ($processedStats as $team) {
            $totalGames += $team['offense_games'];
            foreach (self::STAT_KEYS as $key) {
                $leagueTotals[$key] += $team['raw_offense'][$key] ?? 0;
            }
            $leagueTotals['pts'] += $team['raw_offense']['pts'] ?? 0;
        }

        // Format league totals
        $formattedTotals = [
            'games' => StatsFormatter::formatTotal($totalGames),
        ];
        foreach (self::STAT_KEYS as $key) {
            $formattedTotals[$key] = StatsFormatter::formatTotal($leagueTotals[$key]);
        }
        $formattedTotals['pts'] = StatsFormatter::formatTotal($leagueTotals['pts']);

        // Calculate league averages (per-game and percentages)
        $formattedAverages = $this->formatAverages($leagueTotals, $totalGames);

        return [
            'totals' => $formattedTotals,
            'averages' => $formattedAverages,
            'games' => $totalGames,
        ];
    }

    /**
     * Calculate offense/defense differentials for each team
     *
     * @see LeagueStatsServiceInterface::calculateDifferentials()
     * @param array $processedStats Processed team statistics from processTeamStats()
     * @return array Differential data for each team
     */
    public function calculateDifferentials(array $processedStats): array
    {
        $differentials = [];
        $statKeys = array_merge(self::STAT_KEYS, ['pts']);

        foreach ($processedStats as $team) {
            $teamDiffs = [];
            $offenseGames = $team['offense_games'];
            $defenseGames = $team['defense_games'];

            foreach ($statKeys as $key) {
                // Calculate per-game values for offense and defense
                $offensePerGame = StatsFormatter::safeDivide(
                    $team['raw_offense'][$key] ?? 0,
                    $offenseGames
                );
                $defensePerGame = StatsFormatter::safeDivide(
                    $team['raw_defense'][$key] ?? 0,
                    $defenseGames
                );

                // Differential = offense - defense
                $diff = $offensePerGame - $defensePerGame;
                $teamDiffs[$key] = StatsFormatter::formatAverage($diff);
            }

            // Add percentage differentials
            $teamDiffs['fgp'] = $this->calculatePercentageDifferential(
                $team['raw_offense']['fgm'],
                $team['raw_offense']['fga'],
                $team['raw_defense']['fgm'],
                $team['raw_defense']['fga']
            );
            $teamDiffs['ftp'] = $this->calculatePercentageDifferential(
                $team['raw_offense']['ftm'],
                $team['raw_offense']['fta'],
                $team['raw_defense']['ftm'],
                $team['raw_defense']['fta']
            );
            $teamDiffs['tgp'] = $this->calculatePercentageDifferential(
                $team['raw_offense']['tgm'],
                $team['raw_offense']['tga'],
                $team['raw_defense']['tgm'],
                $team['raw_defense']['tga']
            );

            $differentials[] = [
                'teamid' => $team['teamid'],
                'team_city' => $team['team_city'],
                'team_name' => $team['team_name'],
                'color1' => $team['color1'],
                'color2' => $team['color2'],
                'differentials' => $teamDiffs,
            ];
        }

        return $differentials;
    }

    /**
     * Extract raw stat values from a row with a given prefix
     *
     * @param array $row Database row
     * @param string $prefix Column prefix (e.g., 'offense_', 'defense_')
     * @return array<string, int> Raw stat values
     */
    private function extractRawStats(array $row, string $prefix): array
    {
        $stats = [];
        foreach (self::STAT_KEYS as $key) {
            $stats[$key] = (int) ($row[$prefix . $key] ?? 0);
        }
        return $stats;
    }

    /**
     * Format totals with games count
     *
     * @param array $rawStats Raw stat values
     * @param int $games Number of games played
     * @return array<string, string> Formatted totals
     */
    private function formatTotals(array $rawStats, int $games): array
    {
        $formatted = [
            'games' => StatsFormatter::formatTotal($games),
        ];

        foreach (self::STAT_KEYS as $key) {
            $formatted[$key] = StatsFormatter::formatTotal($rawStats[$key] ?? 0);
        }

        $formatted['pts'] = StatsFormatter::formatTotal($rawStats['pts'] ?? 0);

        return $formatted;
    }

    /**
     * Format per-game averages and shooting percentages
     *
     * @param array $rawStats Raw stat values
     * @param int $games Number of games played
     * @return array<string, string> Formatted averages
     */
    private function formatAverages(array $rawStats, int $games): array
    {
        return [
            'fgm' => StatsFormatter::formatPerGameAverage($rawStats['fgm'] ?? 0, $games),
            'fga' => StatsFormatter::formatPerGameAverage($rawStats['fga'] ?? 0, $games),
            'fgp' => StatsFormatter::formatPercentage($rawStats['fgm'] ?? 0, $rawStats['fga'] ?? 0),
            'ftm' => StatsFormatter::formatPerGameAverage($rawStats['ftm'] ?? 0, $games),
            'fta' => StatsFormatter::formatPerGameAverage($rawStats['fta'] ?? 0, $games),
            'ftp' => StatsFormatter::formatPercentage($rawStats['ftm'] ?? 0, $rawStats['fta'] ?? 0),
            'tgm' => StatsFormatter::formatPerGameAverage($rawStats['tgm'] ?? 0, $games),
            'tga' => StatsFormatter::formatPerGameAverage($rawStats['tga'] ?? 0, $games),
            'tgp' => StatsFormatter::formatPercentage($rawStats['tgm'] ?? 0, $rawStats['tga'] ?? 0),
            'orb' => StatsFormatter::formatPerGameAverage($rawStats['orb'] ?? 0, $games),
            'reb' => StatsFormatter::formatPerGameAverage($rawStats['reb'] ?? 0, $games),
            'ast' => StatsFormatter::formatPerGameAverage($rawStats['ast'] ?? 0, $games),
            'stl' => StatsFormatter::formatPerGameAverage($rawStats['stl'] ?? 0, $games),
            'tvr' => StatsFormatter::formatPerGameAverage($rawStats['tvr'] ?? 0, $games),
            'blk' => StatsFormatter::formatPerGameAverage($rawStats['blk'] ?? 0, $games),
            'pf' => StatsFormatter::formatPerGameAverage($rawStats['pf'] ?? 0, $games),
            'pts' => StatsFormatter::formatPerGameAverage($rawStats['pts'] ?? 0, $games),
        ];
    }

    /**
     * Calculate the differential between two shooting percentages
     *
     * @param int $offenseMade Offense shots made
     * @param int $offenseAttempted Offense shots attempted
     * @param int $defenseMade Defense shots made
     * @param int $defenseAttempted Defense shots attempted
     * @return string Formatted percentage differential
     */
    private function calculatePercentageDifferential(
        int $offenseMade,
        int $offenseAttempted,
        int $defenseMade,
        int $defenseAttempted
    ): string {
        $offensePercentage = StatsFormatter::safeDivide($offenseMade, $offenseAttempted);
        $defensePercentage = StatsFormatter::safeDivide($defenseMade, $defenseAttempted);
        $diff = $offensePercentage - $defensePercentage;

        return StatsFormatter::formatWithDecimals($diff, 3);
    }
}
