<?php

declare(strict_types=1);

namespace TeamOffDefStats\Contracts;

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
 * @see \TeamOffDefStats\TeamOffDefStatsService for implementation
 *
 * @phpstan-import-type AllTeamStatsRow from TeamOffDefStatsRepositoryInterface
 *
 * @phpstan-type RawStatValues array{fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}
 * @phpstan-type FormattedStatTotals array{games: string, fgm: string, fga: string, ftm: string, fta: string, tgm: string, tga: string, orb: string, reb: string, ast: string, stl: string, tvr: string, blk: string, pf: string, pts: string}
 * @phpstan-type FormattedStatAverages array{fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tvr: string, blk: string, pf: string, pts: string}
 * @phpstan-type DifferentialStats array{fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tvr: string, blk: string, pf: string, pts: string}
 * @phpstan-type ProcessedTeamStats array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, offense_totals: FormattedStatTotals, offense_averages: FormattedStatAverages, defense_totals: FormattedStatTotals, defense_averages: FormattedStatAverages, raw_offense: RawStatValues, raw_defense: RawStatValues, offense_games: int, defense_games: int}
 * @phpstan-type LeagueTotals array{totals: FormattedStatTotals, averages: FormattedStatAverages, games: int}
 * @phpstan-type DifferentialTeam array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, differentials: DifferentialStats}
 */
interface TeamOffDefStatsServiceInterface
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
     * @param list<AllTeamStatsRow> $rawStats Raw statistics from repository
     * @return list<ProcessedTeamStats> Processed team statistics
     */
    public function processTeamStats(array $rawStats): array;

    /**
     * Calculate league-wide totals and averages
     *
     * Sums all team offense statistics and calculates league-wide averages.
     * Uses the same formatting as individual team stats for consistency.
     *
     * @param list<ProcessedTeamStats> $processedStats Processed team statistics from processTeamStats()
     * @return LeagueTotals League totals and averages
     */
    public function calculateLeagueTotals(array $processedStats): array;

    /**
     * Calculate offense/defense differentials for each team
     *
     * Subtracts defense per-game values from offense per-game values.
     * Positive values indicate offensive strength, negative indicate weakness.
     *
     * @param list<ProcessedTeamStats> $processedStats Processed team statistics from processTeamStats()
     * @return list<DifferentialTeam> Differential data for each team
     */
    public function calculateDifferentials(array $processedStats): array;
}
