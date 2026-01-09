<?php

declare(strict_types=1);

namespace LeagueStats\Contracts;

/**
 * Interface for League Stats business logic
 *
 * Processes raw team statistics data and calculates:
 * - Per-game averages and shooting percentages for each team
 * - League-wide totals and averages
 * - Offense/defense differentials for each team
 *
 * Uses Statistics\StatsFormatter for consistent formatting.
 *
 * @see \LeagueStats\LeagueStatsService for implementation
 */
interface LeagueStatsServiceInterface
{
    /**
     * Process raw team statistics into formatted data
     *
     * For each team, calculates:
     * - Total points using StatsFormatter::calculatePoints()
     * - Per-game averages using StatsFormatter::formatPerGameAverage()
     * - Shooting percentages using StatsFormatter::formatPercentage()
     * - Formatted totals using StatsFormatter::formatTotal()
     *
     * @param array $rawStats Raw statistics from repository
     * @return array<int, array{
     *     teamid: int,
     *     team_city: string,
     *     team_name: string,
     *     color1: string,
     *     color2: string,
     *     offense_totals: array<string, string>,
     *     offense_averages: array<string, string>,
     *     defense_totals: array<string, string>,
     *     defense_averages: array<string, string>,
     *     raw_offense: array<string, int|float>,
     *     raw_defense: array<string, int|float>
     * }> Processed team statistics
     */
    public function processTeamStats(array $rawStats): array;

    /**
     * Calculate league-wide totals and averages
     *
     * Sums all team offense statistics and calculates league-wide averages.
     * Uses the same formatting as individual team stats for consistency.
     *
     * @param array $processedStats Processed team statistics from processTeamStats()
     * @return array{
     *     totals: array<string, string>,
     *     averages: array<string, string>,
     *     games: int
     * } League totals and averages
     */
    public function calculateLeagueTotals(array $processedStats): array;

    /**
     * Calculate offense/defense differentials for each team
     *
     * Subtracts defense per-game values from offense per-game values.
     * Positive values indicate offensive strength, negative indicate weakness.
     *
     * @param array $processedStats Processed team statistics from processTeamStats()
     * @return array<int, array{
     *     teamid: int,
     *     team_city: string,
     *     team_name: string,
     *     color1: string,
     *     color2: string,
     *     differentials: array<string, string>
     * }> Differential data for each team
     */
    public function calculateDifferentials(array $processedStats): array;
}
