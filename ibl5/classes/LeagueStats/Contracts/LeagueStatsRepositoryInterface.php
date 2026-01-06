<?php

declare(strict_types=1);

namespace LeagueStats\Contracts;

/**
 * Interface for League Stats data access
 *
 * Provides methods for fetching team statistics across the entire league.
 * Implementations should use bulk queries rather than per-team queries
 * for performance optimization.
 *
 * @see \LeagueStats\LeagueStatsRepository for implementation
 */
interface LeagueStatsRepositoryInterface
{
    /**
     * Get all team statistics (offense and defense) in a single bulk query
     *
     * Uses a JOIN across ibl_team_info, ibl_team_offense_stats, and
     * ibl_team_defense_stats to fetch all data in one query instead of
     * 30 individual queries.
     *
     * @return array<int, array{
     *     teamid: int,
     *     team_city: string,
     *     team_name: string,
     *     color1: string,
     *     color2: string,
     *     offense_games: int|null,
     *     offense_fgm: int|null,
     *     offense_fga: int|null,
     *     offense_ftm: int|null,
     *     offense_fta: int|null,
     *     offense_tgm: int|null,
     *     offense_tga: int|null,
     *     offense_orb: int|null,
     *     offense_reb: int|null,
     *     offense_ast: int|null,
     *     offense_stl: int|null,
     *     offense_tvr: int|null,
     *     offense_blk: int|null,
     *     offense_pf: int|null,
     *     defense_games: int|null,
     *     defense_fgm: int|null,
     *     defense_fga: int|null,
     *     defense_ftm: int|null,
     *     defense_fta: int|null,
     *     defense_tgm: int|null,
     *     defense_tga: int|null,
     *     defense_orb: int|null,
     *     defense_reb: int|null,
     *     defense_ast: int|null,
     *     defense_stl: int|null,
     *     defense_tvr: int|null,
     *     defense_blk: int|null,
     *     defense_pf: int|null
     * }> Array of team statistics rows ordered by team city
     */
    public function getAllTeamStats(): array;

    /**
     * Get team offense statistics by team name
     *
     * @param string $teamName Team name
     * @return array|null Team offense statistics
     */
    public function getTeamOffenseStats(string $teamName): ?array;

    /**
     * Get team defense statistics by team name
     *
     * @param string $teamName Team name
     * @return array|null Team defense statistics
     */
    public function getTeamDefenseStats(string $teamName): ?array;
}
