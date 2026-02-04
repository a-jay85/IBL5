<?php

declare(strict_types=1);

namespace SeasonLeaderboards\Contracts;

/**
 * SeasonLeaderboardsRepositoryInterface - Season leaders database operations
 *
 * Handles all database operations for historical season statistics.
 *
 * @phpstan-type LeaderboardFilters array{year?: string, team?: int|string, sortby?: string, limit?: int|string}
 * @phpstan-type HistRow array{pid: int, name: string, year: string, team: string, teamid: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, team_city: string|null, color1: string|null, color2: string|null}
 * @phpstan-type LeaderboardResult array{result: list<HistRow>, count: int}
 * @phpstan-type TeamRow array{TeamID: int, Team: string}
 */
interface SeasonLeaderboardsRepositoryInterface
{
    /**
     * Get season leaders based on filters
     *
     * Retrieves player statistics from ibl_hist table with optional
     * filtering by year and team, sorted by specified stat category.
     *
     * @param LeaderboardFilters $filters Filter parameters
     * @param int $limit Maximum number of records to return (0 for unlimited)
     * @return LeaderboardResult Result with rows and count
     *
     * **Sort Options:**
     * 1=PPG, 2=REB, 3=OREB, 4=AST, 5=STL, 6=BLK, 7=TO, 8=FOUL,
     * 9=QA, 10=FGM, 11=FGA, 12=FG%, 13=FTM, 14=FTA, 15=FT%,
     * 16=TGM, 17=TGA, 18=TG%, 19=GAMES, 20=MIN
     *
     * **Behaviors:**
     * - Filters out null names
     * - Per-game stats calculated in ORDER BY clause
     * - Invalid sortBy defaults to PPG (option 1)
     */
    public function getSeasonLeaders(array $filters, int $limit = 0): array;

    /**
     * Get all teams for dropdown
     *
     * Retrieves teams from ibl_power table for filter dropdown.
     *
     * @return list<TeamRow> Array of team rows
     *
     * **Returned Columns:**
     * - TeamID
     * - Team (name)
     *
     * **Behaviors:**
     * - Only returns TeamID 1-32 (excludes special entries)
     * - Ordered by TeamID ASC
     */
    public function getTeams(): array;

    /**
     * Get all distinct years from history
     *
     * Retrieves unique years that have historical data.
     *
     * @return list<string> Array of year values ordered DESC (newest first)
     */
    public function getYears(): array;
}
