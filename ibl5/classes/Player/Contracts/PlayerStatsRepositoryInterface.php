<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerStatsRepositoryInterface - Contract for player statistics data access
 * 
 * Defines database operations for fetching player statistics including
 * current season stats, historical stats, box scores, and sim-based stats.
 * All methods use prepared statements internally for SQL injection prevention.
 */
interface PlayerStatsRepositoryInterface
{
    /**
     * Get current season statistics for a player
     * 
     * @param int $playerID Player ID (pid from ibl_plr table)
     * @return array<string, mixed>|null Complete stats row or null if not found
     * 
     * Returns raw ibl_plr row with all stats_* columns:
     *  - stats_gs, stats_gm (games started/played)
     *  - stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta
     *  - stats_3gm, stats_3ga, stats_orb, stats_drb
     *  - stats_ast, stats_stl, stats_to, stats_blk, stats_pf
     *  - sh_* (season highs), sp_* (season playoff highs)
     *  - ch_* (career highs), cp_* (career playoff highs)
     *  - s_dd, s_td (season double/triple doubles)
     *  - c_dd, c_td (career double/triple doubles)
     *  - car_* (career totals)
     */
    public function getPlayerStats(int $playerID): ?array;

    /**
     * Get historical season statistics for a player
     * 
     * @param int $playerID Player ID
     * @param string $statsType Type of stats: 'regular', 'playoff', 'heat', 'olympic'
     * @return array<int, array<string, mixed>> Array of historical stat rows ordered by year DESC
     * 
     * Each row contains:
     *  - year, team (team abbreviation)
     *  - gm, min, fgm, fga, ftm, fta, 3gm, 3ga
     *  - orb, reb (drb calculated as reb - orb)
     *  - ast, stl, blk, tvr (turnovers), pf
     */
    public function getHistoricalStats(int $playerID, string $statsType = 'regular'): array;

    /**
     * Get box score entries for a player within a date range
     * 
     * @param int $playerID Player ID
     * @param string $startDate Start date (YYYY-MM-DD format)
     * @param string $endDate End date (YYYY-MM-DD format)
     * @return array<int, array<string, mixed>> Array of box score rows ordered by Date ASC
     * 
     * Each row contains:
     *  - Date, homeTID, visitorTID
     *  - gameMIN, game2GM, game2GA, gameFTM, gameFTA
     *  - game3GM, game3GA, gameORB, gameDRB
     *  - gameAST, gameSTL, gameTOV, gameBLK, gamePF
     */
    public function getPlayerBoxScores(int $playerID, string $startDate, string $endDate): array;

    /**
     * Get sim date ranges for displaying sim-based statistics
     * 
     * @param int $limit Maximum number of sims to return (default 20)
     * @return array<int, array{Sim: int, Start Date: string, End Date: string}> Sim date ranges ordered by sim DESC
     */
    public function getSimDateRanges(int $limit = 20): array;

    /**
     * Get aggregated box score statistics for a player within a sim period
     * 
     * @param int $playerID Player ID
     * @param string $startDate Sim start date (YYYY-MM-DD format)
     * @param string $endDate Sim end date (YYYY-MM-DD format)
     * @return array{
     *     games: int,
     *     minutes: int,
     *     fg2Made: int,
     *     fg2Attempted: int,
     *     ftMade: int,
     *     ftAttempted: int,
     *     fg3Made: int,
     *     fg3Attempted: int,
     *     offRebounds: int,
     *     defRebounds: int,
     *     assists: int,
     *     steals: int,
     *     turnovers: int,
     *     blocks: int,
     *     fouls: int,
     *     points: int
     * } Aggregated statistics for the sim period
     */
    public function getSimAggregatedStats(int $playerID, string $startDate, string $endDate): array;

    /**
     * Get career totals for a player from ibl_plr table
     * 
     * @param int $playerID Player ID
     * @return array<string, mixed>|null Career totals or null if not found
     * 
     * Returns:
     *  - car_gm, car_min, car_fgm, car_fga, car_ftm, car_fta
     *  - car_tgm, car_tga, car_orb, car_drb, car_reb
     *  - car_ast, car_stl, car_to, car_blk, car_pf
     */
    public function getCareerTotals(int $playerID): ?array;
}
